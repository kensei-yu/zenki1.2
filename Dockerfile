FROM php:8.4-fpm-alpine AS php

# pdo と pdo_mysql をインストール
RUN docker-php-ext-install pdo pdo_mysql

RUN install -o www-data -g www-data -d /var/www/upload/image/1
