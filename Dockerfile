FROM composer:2.9 AS composer

FROM php:8.4-cli-bookworm AS app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    APP_DIR=/app

ARG APP_UID=1000
ARG APP_GID=1000

RUN groupadd -g "${APP_GID}" app \
    && useradd -m -u "${APP_UID}" -g "${APP_GID}" -s /bin/bash app

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
        python3-venv \
        python3 \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install intl mbstring opcache pcntl xml zip \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer /usr/bin/composer /usr/bin/composer

ARG YTDLP_VERSION=2026.03.17

# Install yt-dlp via pip so optional extras (like curl-cffi impersonation) are available.
# The GitHub "yt-dlp" unix zipimport binary does not include these extras.
RUN python3 -m venv /opt/yt-dlp \
    && /opt/yt-dlp/bin/pip install --no-cache-dir --upgrade pip \
    && /opt/yt-dlp/bin/pip install --no-cache-dir "yt-dlp[default,curl-cffi]==${YTDLP_VERSION}" \
    && ln -sf /opt/yt-dlp/bin/yt-dlp /usr/local/bin/yt-dlp

# YouTube extraction increasingly requires a JavaScript runtime. Install Deno (supported by yt-dlp).
RUN curl -fsSL https://github.com/denoland/deno/releases/latest/download/deno-x86_64-unknown-linux-gnu.zip -o /tmp/deno.zip \
    && unzip /tmp/deno.zip -d /usr/local/bin \
    && chmod +x /usr/local/bin/deno \
    && rm -f /tmp/deno.zip

WORKDIR /app

COPY --chown=app:app composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist

COPY --chown=app:app . .

RUN mkdir -p /app/var/log /app/var/subtitles /app/var/subtitles_tmp \
    && chmod +x /app/bin/console \
    && chown -R app:app /app/var /app/vendor

USER app

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
