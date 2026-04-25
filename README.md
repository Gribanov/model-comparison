# Model Comparison

Docker-first Symfony 8 MVP for a Telegram bot that returns subtitles for supported YouTube videos as `.srt` files.

## MVP Flow

1. Telegram sends a webhook update to the Symfony app.
2. The webhook controller accepts the request and returns quickly.
3. The update handler accepts only text messages, extracts a YouTube URL, and validates it.
4. The app checks the Redis-backed per-user active-job lock.
5. If the user is free, the app dispatches `DownloadSubtitlesMessage` to the `async` Messenger transport.
6. The worker consumes the message in the background.
7. The worker runs `yt-dlp` inside Docker, first trying regular subtitles and then auto-generated subtitles.
8. If subtitles are found, the worker uploads the resulting `.srt` file back to Telegram.
9. The worker deletes the temporary file and working directory after upload.
10. A dedicated hourly scheduler removes any leftover temp files and directories older than the configured TTL.

## Requirements

- PHP 8.4
- Docker and Docker Compose

## Services

- `app`: Symfony HTTP application container
- `worker`: Messenger consumer for subtitle jobs
- `scheduler`: hourly cleanup loop for stale temp files
- `redis`: Redis for Messenger and active-job locking
- `caddy`: HTTP entrypoint exposed on `http://localhost:8080`

## Build Containers

```bash
docker compose build
```

## Start Services

```bash
docker compose up -d
```

If you want the worker and scheduler to run too:

```bash
docker compose up -d worker scheduler
```

## Run Symfony Commands

Run Symfony and Composer commands inside the `app` container:

```bash
docker compose exec app php bin/console about
docker compose exec app composer install
docker compose exec app php bin/console cache:clear
```

## Worker

The worker uses the same PHP image as `app` and consumes the `async` Messenger transport:

```bash
docker compose up -d worker
```

Watch worker logs with:

```bash
docker compose logs -f worker
```

## Scheduler

The scheduler container runs the stale-temp cleanup command once per hour inside Docker:

```bash
docker compose up -d scheduler
```

It loops around:

```bash
php bin/console app:subtitle-temp:cleanup
```

If the cleanup command fails, the container keeps running and retries on the next cycle.

## Webhook Sync

Synchronize Telegram's registered webhook from inside the `app` container:

```bash
docker compose exec app php bin/console app:telegram:webhook:sync
```

Expected webhook URL shape:

```text
{TELEGRAM_WEBHOOK_BASE_URL}/telegram/webhook/{TELEGRAM_WEBHOOK_SECRET}
```

The secret segment is URL-encoded before registration and route matching.

## Cleanup

Run manual temp cleanup inside Docker:

```bash
docker compose exec app php bin/console app:subtitle-temp:cleanup
```

## Diagnostics

Check configuration and temp-dir accessibility inside Docker:

```bash
docker compose exec app php bin/console app:subtitle-bot:doctor
```

This command checks:

- Telegram token presence
- webhook base URL and secret presence
- Redis Messenger DSN format
- yt-dlp binary accessibility
- subtitle temp directory accessibility
- lock and cleanup TTL values

## Supported YouTube Links

Only regular YouTube video URLs are supported.

Accepted formats:

- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`

Also accepted when pasted in surrounding text or without a scheme, if a valid video URL can be extracted.

Unsupported formats:

- playlists
- channels
- Shorts
- non-YouTube URLs
- malformed URLs

## Temp File Lifecycle

- Temporary subtitle workspaces are created under `APP_SUBTITLE_TEMP_DIR`.
- The worker deletes the subtitle file and workspace after a successful Telegram upload.
- Leftovers from crashes or unexpected failures are cleaned by the hourly scheduler.
- The cleanup policy is time-based using `TEMP_FILE_TTL`.

## Environment

Example values are provided in `.env`:

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_WEBHOOK_BASE_URL`
- `TELEGRAM_WEBHOOK_SECRET`
- `MESSENGER_TRANSPORT_DSN`
- `REDIS_PASSWORD`
- `USER_JOB_LOCK_TTL`
- `YTDLP_TIMEOUT`
- `TEMP_FILE_TTL`
- `YTDLP_BIN`
- `APP_SUBTITLE_TEMP_DIR`

Notes:

- `MESSENGER_TRANSPORT_DSN` points to the Redis-backed `async` transport.
- `YTDLP_BIN` defaults to `/usr/local/bin/yt-dlp` in the container.
- `APP_SUBTITLE_TEMP_DIR` defaults to `/app/var/subtitles` in the container.
- `USER_JOB_LOCK_TTL` and `TEMP_FILE_TTL` are in seconds.

## Local Testing

Telegram must be able to reach the webhook URL over HTTPS. For local development, point `TELEGRAM_WEBHOOK_BASE_URL` at a public tunnel or test host, then run webhook sync again inside Docker.

## Notes

- Only one active subtitle job is allowed per Telegram user at a time.
- The worker first tries regular subtitles, then auto-generated subtitles.
- When no subtitles are available, the bot sends a user-friendly failure message.
- No database is used in this MVP.
