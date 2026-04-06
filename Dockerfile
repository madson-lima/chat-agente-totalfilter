FROM php:8.2-cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libssl-dev pkg-config libzip-dev libpng-dev libxml2-dev libonig-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install pdo pdo_mysql zip gd mbstring xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p /app/storage/logs /app/storage/sessions

EXPOSE 8080

CMD ["sh", "-c", "php -d memory_limit=512M -d upload_max_filesize=64M -d post_max_size=72M -d max_execution_time=300 -S 0.0.0.0:${PORT:-8080} -t public"]
