<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (isset($_POST['body'])) {
  // POSTで送られてくるフォームパラメータ body がある場合

  $image_filename = null;
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    // アップロードされた画像がある場合
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) !== 1) {
      // アップロードされたものが画像ではなかった場合処理を強制的に終了
      header("HTTP/1.1 302 Found");
      header("Location: ./bbsimagetest.php");
      return;
    }

    // 元のファイル名から拡張子を取得
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];
    // 新しいファイル名を決める。他の投稿の画像ファイルと重複しないように時間+乱数で決める。
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.' . $extension;
    $filepath =  '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // 処理が終わったらリダイレクトする
  // リダイレクトしないと，リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 302 Found");
  header("Location: ./bbsimagetest.php");
  return;
}

// いままで保存してきたものを取得
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>画像付き掲示板</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      font-family: sans-serif;
      background-color: #f9f9f9;
      margin: 0;
      padding: 1em;
    }

    form {
      background: #fff;
      padding: 1em;
      border-radius: 6px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      margin-bottom: 2em;
    }

    textarea {
      width: 100%;
      height: 6em;
      font-size: 1em;
      padding: 0.5em;
      box-sizing: border-box;
    }

    button {
      margin-top: 1em;
      padding: 0.5em 1em;
      font-size: 1em;
    }

    dl {
      background: #fff;
      padding: 1em;
      border: 1px solid #ccc;
      border-radius: 6px;
      margin-bottom: 1em;
      word-break: break-word;
    }

    img {
      max-width: 100%;
      height: auto;
      margin-top: 0.5em;
      border-radius: 4px;
    }

    @media screen and (max-width: 600px) {
      body {
        padding: 0.5em;
      }

      textarea {
        height: 5em;
        font-size: 0.95em;
      }

      button {
        width: 100%;
      }
    }
  </style>
</head>
<body>

<!-- フォームのPOST先はこのファイル自身にする -->
<form method="POST" action="./bbsimagetest.php" enctype="multipart/form-data">
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>
  <button type="submit">送信</button>
</form>

<hr>

<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>ID</dt>
    <dd><?= $entry['id'] ?></dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= nl2br(htmlspecialchars($entry['body'])) // 必ず htmlspecialchars() すること ?>
      <?php if(!empty($entry['image_filename'])): // 画像がある場合は img 要素を使って表示 ?>
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("form");
  const imageInput = document.getElementById("imageInput");

  form.addEventListener("submit", async (e) => {
    e.preventDefault(); // 通常の送信を止める

    const file = imageInput.files[0];
    const bodyText = form.body.value;

    if (!file) {
      form.submit(); // 画像なしならそのまま送信
      return;
    }

    if (!file.type.startsWith("image/")) {
      alert("画像ファイルを選択してください。");
      return;
    }

    const resizedBlob = await resizeImage(file, 1024); // 最大幅1024pxで縮小

    if (resizedBlob.size > 5 * 1024 * 1024) {
      alert("縮小後も5MBを超えています。別の画像を選んでください。");
      return;
    }

    const formData = new FormData();
    formData.append("body", bodyText);
    formData.append("image", resizedBlob, file.name);

    fetch(form.action, {
      method: "POST",
      body: formData,
    }).then(() => {
      window.location.href = "./bbsimagetest.php";
    });
  });

  async function resizeImage(file, maxWidth) {
    return new Promise((resolve) => {
      const img = new Image();
      const reader = new FileReader();

      reader.onload = (e) => {
        img.onload = () => {
          const scale = Math.min(1, maxWidth / img.width);
          const canvas = document.createElement("canvas");
          canvas.width = img.width * scale;
          canvas.height = img.height * scale;

          const ctx = canvas.getContext("2d");
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

          canvas.toBlob((blob) => {
            resolve(blob);
          }, "image/jpeg", 0.85); // JPEGで圧縮率85%
        };
        img.src = e.target.result;
      };

      reader.readAsDataURL(file);
    });
  }
});
</script>
