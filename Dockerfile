FROM php:8.4-cli-alpine

RUN apk add --no-cache postgresql-dev icu-dev libzip-dev git curl tesseract-ocr tesseract-ocr-data-eng tesseract-ocr-data-rus \
    && docker-php-ext-install pdo_pgsql intl zip bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /app

ENTRYPOINT ["entrypoint"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
