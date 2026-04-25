# Model Comparison

Docker-first Symfony 8 bootstrap for a Telegram bot that will later download YouTube subtitles.

## Requirements

- PHP 8.4
- Docker and Docker Compose

## Build Containers

```bash
docker compose build
```

## Start Services

```bash
docker compose up -d
```

The HTTP entrypoint is exposed through Caddy on `http://localhost:8080`.

## Run Symfony Commands

Run all Symfony and Composer commands inside the `app` container:

```bash
docker compose exec app php bin/console about
docker compose exec app composer install
docker compose exec app php bin/console cache:clear
```

## Start Worker

The worker service uses the same PHP image as `app` and consumes the `async` Messenger transport:

```bash
docker compose up -d worker
```

To watch worker logs:

```bash
docker compose logs -f worker
```

## Telegram Webhook

Synchronize Telegram's registered webhook from inside the `app` container:

```bash
docker compose exec app php bin/console app:telegram:webhook:sync
```

The expected webhook URL shape is:

```text
{TELEGRAM_WEBHOOK_BASE_URL}/telegram/webhook/{TELEGRAM_WEBHOOK_SECRET}
```

The secret segment is URL-encoded before registration and route matching.

## Local Testing

Telegram must be able to reach the webhook URL over HTTPS. For local development, point `TELEGRAM_WEBHOOK_BASE_URL` at a public tunnel or test host, then run the sync command again inside Docker.

## Environment

The project ships with example values in `.env` for:

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_WEBHOOK_BASE_URL`
- `TELEGRAM_WEBHOOK_SECRET`
- `MESSENGER_TRANSPORT_DSN`
- `REDIS_PASSWORD`
- `YTDLP_BIN`
- `APP_SUBTITLE_TEMP_DIR`

The subtitle temp directory is resolved inside the container and defaults to `/app/var/subtitles`.
