# Model Comparison

Docker-first Symfony 8 MVP for a Telegram bot that returns subtitles for supported YouTube videos as `.srt` files.

## Model information
```aiignore
Model:                gpt-5.4-mini (reasoning medium, summaries auto) 
Context window:       75% left (73.4K used / 258K)                            │
5h limit:             [██████████████░░░░░░] 69% left (resets 04:07) 
```
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

## Smoke Test

Run a lightweight operational check after deployment:

```bash
docker compose exec app php bin/console app:bot:diagnostics
```

This command verifies:

- Telegram configuration presence
- Redis connectivity
- subtitle temp directory write access
- yt-dlp binary availability
- expected webhook URL shape

If you want the command to call Telegram and compare the currently registered webhook, set:

```bash
BOT_DIAGNOSTICS_CHECK_REMOTE_WEBHOOK=1
```

Queue depth can be checked with the built-in Messenger command:

```bash
docker compose exec app php bin/console messenger:stats async
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

## Logs

Use Docker Compose logs to inspect the main operational paths:

```bash
docker compose logs -f app
docker compose logs -f worker
docker compose logs -f scheduler
docker compose logs -f redis
docker compose logs -f caddy
```

- `app` logs webhook reception, validation, queue dispatch, and webhook sync output.
- `worker` logs yt-dlp execution, Telegram upload failures, and subtitle fallback behavior.
- `scheduler` logs stale temp cleanup runs.
- `redis` is useful for transport and lock connectivity issues.
- `caddy` is useful for ingress and reverse-proxy issues.

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

The doctor command is a config-oriented check. The smoke-test command above is the better end-to-end readiness probe.

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
- `BOT_DIAGNOSTICS_CHECK_REMOTE_WEBHOOK`

Notes:

- `MESSENGER_TRANSPORT_DSN` points to the Redis-backed `async` transport.
- `YTDLP_BIN` defaults to `/usr/local/bin/yt-dlp` in the container.
- `APP_SUBTITLE_TEMP_DIR` defaults to `/app/var/subtitles` in the container.
- `USER_JOB_LOCK_TTL` and `TEMP_FILE_TTL` are in seconds.
- `BOT_DIAGNOSTICS_CHECK_REMOTE_WEBHOOK` defaults to `0`; set it to `1` on deployment if you want the smoke test to query Telegram directly.

## Local Testing

Telegram must be able to reach the webhook URL over HTTPS. For local development, point `TELEGRAM_WEBHOOK_BASE_URL` at a public tunnel or test host, then run webhook sync again inside Docker.

Queue flow verification usually looks like this:

1. Start the stack.
2. Run `docker compose exec app php bin/console app:bot:diagnostics`.
3. Run `docker compose exec app php bin/console app:telegram:webhook:sync`.
4. Send a real Telegram message with a supported YouTube video URL.
5. Watch `docker compose logs -f app worker`.
6. Confirm the worker uploads an `.srt` file back to Telegram.

## Notes

- Only one active subtitle job is allowed per Telegram user at a time.
- The worker first tries regular subtitles, then auto-generated subtitles.
- When no subtitles are available, the bot sends a user-friendly failure message.
- No database is used in this MVP.

## VPS Deployment via SSH

The project is designed to be deployed on a VPS over SSH with Docker installed.

Production deployment uses `docker-compose.prod.yml` (no source bind-mounts, persistent Redis and app `var/` volumes, and Caddy on ports 80/443).

Prereqs:

- Point a DNS record (e.g. `bot.example.com`) at your VPS.
- Open inbound ports `80` and `443` in the VPS firewall / security group.
- Configure the host firewall (example with UFW below).
- Create or update `.env` on the server, including at least:
  - `APP_ENV=prod`
  - `APP_DEBUG=0`
  - `APP_SECRET=<random>`
  - `REDIS_PASSWORD=<strong password>`
  - `TELEGRAM_BOT_TOKEN=...`
  - `TELEGRAM_WEBHOOK_BASE_URL=https://bot.example.com`
  - `TELEGRAM_WEBHOOK_SECRET=<random>`
  - `DOMAIN=bot.example.com`
  - `CADDY_EMAIL=admin@example.com`

UFW firewall setup (Ubuntu/Debian):

```bash
# install if needed
sudo apt-get update
sudo apt-get install -y ufw

# allow SSH first (replace 22 if you use a non-standard SSH port)
sudo ufw allow OpenSSH

# allow web traffic for Caddy (ports 80/443)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# enable firewall
sudo ufw enable

# verify rules
sudo ufw status verbose
```

Notes:

- Do NOT open Redis (`6379`) to the internet; this stack only needs Redis on the Docker network.
- If you run SSH on a custom port, allow that port instead of `OpenSSH`.

Typical production deployment flow:

```bash
ssh deploy@your-server
git clone <your-repo-url> model-comparison
cd model-comparison
# create or update .env on the server
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php bin/console app:bot:diagnostics
docker compose -f docker-compose.prod.yml exec app php bin/console app:telegram:webhook:sync
```

If you redeploy after code changes:

```bash
ssh deploy@your-server
cd model-comparison
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php bin/console app:bot:diagnostics
```

Notes for VPS deployment:

- The default `docker-compose.yml` exposes Caddy on port `8080` for local development.
- `docker-compose.prod.yml` exposes Caddy on ports `80/443` and expects `DOMAIN` and `CADDY_EMAIL` in `.env`.
- On a public VPS, the webhook URL must be reachable over HTTPS.
- After deployment, run `app:bot:diagnostics` before syncing the webhook.
- To see full exception context in production logs, tail the app container logs:
  - `docker compose -f docker-compose.prod.yml logs -f --tail=300 app`
