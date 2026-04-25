FROM composer:2.9 AS composer

FROM php:8.4-cli-bookworm AS app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    APP_DIR=/app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        ca-certificates \
        curl \
        git \
        ffmpeg \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        python3 \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install intl mbstring opcache pcntl xml zip \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o /usr/local/bin/yt-dlp \
    && chmod +x /usr/local/bin/yt-dlp

WORKDIR /app

COPY composer.json ./
RUN composer install --no-interaction --no-progress --prefer-dist

COPY . .

RUN mkdir -p /app/var/subtitles \
    && chmod +x /app/bin/console

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
