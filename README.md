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

The worker downloads subtitles with `yt-dlp`, first trying regular subtitles and then auto-generated subtitles if needed. After a successful Telegram upload, the temporary `.srt` file and its job directory are deleted.

The hourly cleanup scheduler runs in a dedicated Docker container and executes `app:subtitle-temp:cleanup` once per hour against the same mounted temp volume.

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

## Subtitle Jobs

The bot allows only one active subtitle job per Telegram user at a time.

Valid YouTube links are queued asynchronously through Symfony Messenger using the Redis-backed `async` transport. The user gets a confirmation when the job is accepted, and a friendly status message if a previous request is still running.

## Accepted YouTube Links

This version accepts regular video URLs only:

- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`

Shorts, playlists, channels, and non-YouTube URLs are rejected.

## Local Testing

Telegram must be able to reach the webhook URL over HTTPS. For local development, point `TELEGRAM_WEBHOOK_BASE_URL` at a public tunnel or test host, then run the sync command again inside Docker.

To run the cleanup manually inside Docker:

```bash
docker compose exec app php bin/console app:subtitle-temp:cleanup
```

To start the hourly scheduler container:

```bash
docker compose up -d scheduler
```

## Environment

The project ships with example values in `.env` for:

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_WEBHOOK_BASE_URL`
- `TELEGRAM_WEBHOOK_SECRET`
- `MESSENGER_TRANSPORT_DSN`
- `REDIS_PASSWORD`
- `USER_JOB_LOCK_TTL`
- `YTDLP_TIMEOUT`
- `YTDLP_BIN`
- `APP_SUBTITLE_TEMP_DIR`

The subtitle temp directory is resolved inside the container and defaults to `/app/var/subtitles`.

Temporary leftovers are cleaned up once per hour by a dedicated scheduler container that runs `app:subtitle-temp:cleanup` inside Docker.
