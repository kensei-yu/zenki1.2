<?php
// --- データベース接続 ---
// エラー時に例外をスローするよう設定
try {
    $dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// --- 定数定義 ---
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_DIR', './uploads/');       // 画像を保存するディレクトリ

// --- POSTリクエスト処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 本文のバリデーション
    if (!isset($_POST['body']) || trim($_POST['body']) === '') {
        die("本文を入力してください。");
    }

    $body = $_POST['body'];
    $image_filename = null;

    // --- 画像アップロード処理 ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // 【要件】5MB以上の画像をアップロードできないように（サーバーサイド）
        if ($_FILES['image']['size'] > MAX_FILE_SIZE) {
            die("ファイルサイズは5MB以下にしてください。");
        }
        
        // 画像ファイルかどうかのMIMEタイプチェック
        $mime_type = mime_content_type($_FILES['image']['tmp_name']);
        if (strpos($mime_type, 'image/') !== 0) {
            die("画像ファイルではありません。");
        }

        // ユニークなファイル名を生成
        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $filepath = UPLOAD_DIR . $image_filename;

        // ディレクトリが存在し、書き込み可能か確認
        if (!is_dir(UPLOAD_DIR) || !is_writable(UPLOAD_DIR)) {
             die("アップロードディレクトリが存在しないか、書き込み権限がありません。");
        }
        
        // ファイルを移動
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
            die("ファイルのアップロードに失敗しました。");
        }
    }

    // --- データベースへの保存（SQLインジェクション対策済み） ---
    $stmt = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)");
    $stmt->execute([
        ':body' => $body,
        ':image_filename' => $image_filename,
    ]);

    // PRGパターン: 二重投稿防止のためリダイレクト
    header("Location: " . $_SERVER['SCRIPT_NAME']);
    exit;
}

// --- 投稿データを取得 ---
$stmt = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>掲示板</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 20px auto; padding: 0 15px; }
        form { background: #f4f4f4; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        textarea { width: 100%; height: 80px; margin-bottom: 10px; box-sizing: border-box; }
        .post { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 8px; }
        .post-body { white-space: pre-wrap; word-wrap: break-word; }
        .post-meta { font-size: 0.9em; color: #777; }
        .post img { max-width: 100%; height: auto; margin-top: 10px; border-radius: 4px; }
    </style>
</head>
<body>

    <h1>掲示板</h1>

    <form method="POST" action="" enctype="multipart/form-data">
        <textarea name="body" required placeholder="投稿内容を入力"></textarea>
        <div>
            <input type="file" accept="image/*" name="image" id="imageInput">
        </div>
        <button type="submit">送信</button>
    </form>

    <hr>

    <?php foreach ($posts as $post): ?>
        <div class="post">
            <p class="post-meta">
                投稿ID: <?= htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8') ?> | 
                投稿日時: <?= htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p class="post-body"><?= nl2br(htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8')) ?></p>
            
            <?php if (!empty($post['image_filename'])): ?>
                <img src="<?= UPLOAD_DIR . htmlspecialchars($post['image_filename'], ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const imageInput = document.getElementById("imageInput");
    imageInput.addEventListener("change", () => {
        if (imageInput.files.length < 1) {
            return;
        }
        // 5MB = 5 * 1024 * 1024 bytes
        if (imageInput.files[0].size > 5 * 1024 * 1024) {
            alert("5MB以下のファイルを選択してください。");
            imageInput.value = "";
        }
    });
});
</script>

</body>
</html>
