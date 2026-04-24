# AGENTS.md

## Project overview

This project is a Telegram bot that receives a YouTube video URL from a user and sends back subtitles for that video as an `.srt` file.

### Main stack

* Symfony
* Docker / Docker Compose
* Redis
* Symfony Messenger
* `yt-dlp`
* Telegram Bot API

### High-level flow

1. Telegram sends updates to the bot via webhook.
2. Symfony receives the webhook request.
3. The bot extracts and validates the YouTube URL.
4. The bot ensures there is no other active job for the same Telegram user.
5. The job is pushed to a Redis-backed queue.
6. A worker processes the job in the background.
7. The worker runs `yt-dlp` inside Docker to fetch subtitles.
8. The bot sends the resulting `.srt` file back to the user in Telegram.
9. Temporary files are removed after upload.
10. A periodic cleanup removes any leftover temp files older than the configured TTL.

---

## Business rules

Agents must preserve these rules unless a task explicitly changes them.

* Only YouTube links are supported.
* Allowed domains: `youtube.com`, `www.youtube.com`, `youtu.be`.
* Only regular YouTube video links are supported.
* Do not support playlists, channels, or Shorts unless a task explicitly adds that support.
* Subtitles must be returned as an `.srt` file.
* The bot should first try to fetch regular subtitles.
* If regular subtitles are not available, the bot should try auto-generated subtitles.
* If no subtitles are available, the bot should send a user-friendly error message.
* Only one active job is allowed per Telegram user at any moment.
* There is no persistent job history in the initial version.
* Uploaded subtitle files must be removed after successful Telegram upload.
* Leftover temp files must be cleaned up automatically once per hour.

---

## Environment and execution rules

### Important

All PHP commands must be executed inside the application container, not on the host machine.

Do not assume PHP, Composer, Symfony CLI, or project dependencies are available on the host.
Always prefer container execution.

### Preferred command style

Use commands in the style below, adapting service names if needed:

```bash
docker compose exec app php bin/console
docker compose exec app composer install
docker compose exec app php bin/phpunit
docker compose exec worker php bin/console messenger:consume async -vv
```

If the actual PHP service is not named `app`, detect the correct service name from `docker-compose.yml` and use that.

### Docker-first principle

* Run Symfony commands inside Docker.
* Run tests inside Docker.
* Run worker processes inside Docker.
* Run `yt-dlp` inside Docker.
* Do not propose host-level PHP execution unless the task explicitly requires it.

---

## Architecture expectations

Agents should follow these architectural boundaries.

### Recommended layers

* **Controller layer**: receives Telegram webhook requests, keeps logic minimal.
* **Application layer**: orchestrates bot actions, validation flow, lock checks, queue dispatch.
* **Infrastructure layer**: Telegram API client, Redis-based locking, `yt-dlp` execution, filesystem operations.
* **Message / Handler layer**: async background processing via Symfony Messenger.

### Expected responsibilities

* Controllers should not contain `yt-dlp` execution logic.
* Controllers should not perform long-running work.
* Background jobs should be handled by Messenger handlers.
* Telegram API interaction should be encapsulated in a dedicated client/service.
* `yt-dlp` invocation should be encapsulated in a dedicated service.
* URL validation should be encapsulated in a dedicated validator/parser service.
* User active-job checks should be encapsulated in a lock service.

---

## Suggested project structure

Agents should prefer a structure close to this unless the existing codebase already uses another consistent structure.

```text
src/
  Controller/
    TelegramWebhookController.php

  Application/
    Telegram/
      HandleTelegramUpdateService.php
      EnqueueSubtitleJobService.php
      RegisterWebhookService.php

  Message/
    DownloadSubtitlesMessage.php

  MessageHandler/
    DownloadSubtitlesHandler.php

  Infrastructure/
    Telegram/
      TelegramBotClient.php
    Youtube/
      YoutubeUrlValidator.php
      YtDlpSubtitleDownloader.php
    Lock/
      UserActiveJobLockService.php
    Filesystem/
      SubtitleTempFileManager.php

  Command/
    TelegramWebhookSyncCommand.php
    SubtitleTempCleanupCommand.php
```

This is guidance, not a rigid rule. Respect the existing project structure if it already has clear conventions.

---

## Telegram-specific requirements

### Webhook

The project must include a console command that synchronizes the configured webhook with Telegram.

Expected behavior:

* Read the expected webhook URL from configuration.
* Call Telegram `getWebhookInfo`.
* Compare the currently registered webhook to the expected value.
* If the webhook is already correct, do nothing except report success.
* If it is different, register the expected webhook using `setWebhook`.
* Re-check state after registration when appropriate.

### Security

Use a secret webhook path segment or equivalent secret validation mechanism.
Do not expose a predictable public webhook path if the project already uses a secretized route design.

### User-facing behavior

Agents should preserve these UX rules:

* If a valid new URL is accepted, tell the user the job has been queued.
* If the same user already has an active job, tell them the previous request is still processing.
* If the URL is invalid, tell the user to send a valid regular YouTube video link.
* If subtitles are unavailable, send a clear user-friendly failure message.

---

## Queue and concurrency rules

### Redis usage

Redis is used for:

* Symfony Messenger transport
* active job locking per Telegram user

### One active job per user

This is a core business rule.
Agents must not accidentally break it.

Preferred implementation:

* use Redis lock-like semantics
* key example: `bot:user:{telegramUserId}:active_job`
* set with TTL
* refuse new jobs while the lock exists
* remove the lock when processing finishes
* rely on TTL to prevent dead locks after crashes

### Queueing

* Webhook handling must stay fast.
* Subtitle downloads must happen asynchronously.
* Long-running work belongs in Messenger handlers, not controllers.

---

## `yt-dlp` rules

Agents must treat `yt-dlp` as an infrastructure dependency invoked from the worker/application container.

### Required behavior

* Do not download the video itself.
* Fetch subtitles only.
* Prefer regular subtitles first.
* Fallback to auto-generated subtitles only if regular subtitles are unavailable.
* Produce `.srt` output.
* Store temporary output in a controlled temp directory.
* Clean up files after successful Telegram upload.

### Invocation design

Keep `yt-dlp` invocation behind a dedicated service such as `YtDlpSubtitleDownloader`.
Do not scatter shell command construction across controllers or unrelated services.

### Error handling

Capture and log:

* command exit code
* stderr
* stdout when useful
* timeout conditions
* missing subtitle cases

Convert low-level `yt-dlp` failures into application-level results or exceptions.

---

## Temporary files and cleanup

Agents must assume subtitle files are temporary artifacts.

### Requirements

* Create temporary working directories in a dedicated configured location.
* Remove files immediately after successful upload to Telegram.
* Provide a cleanup command that removes stale temp files.
* Cleanup must be designed to run once per hour.

### Cleanup command

Prefer a console command such as:

* `app:subtitle-temp:cleanup`

This command should:

* scan the temp directory
* remove files/directories older than the configured TTL
* log or report how many files were deleted

---

## Configuration rules

Environment variables are expected for configuration.
Agents should prefer configuration through Symfony parameters / env vars rather than hardcoded values.

Typical variables include:

```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_BASE_URL=
TELEGRAM_WEBHOOK_SECRET=
MESSENGER_TRANSPORT_DSN=
REDIS_PASSWORD=
YTDLP_BIN=/usr/local/bin/yt-dlp
APP_SUBTITLE_TEMP_DIR=/tmp/subtitles
USER_JOB_LOCK_TTL=1800
TEMP_FILE_TTL=3600
```

### Rules

* Never hardcode secrets.
* Never commit real secrets.
* Keep defaults safe for local development when possible.
* Prefer explicit env-driven configuration for binary paths, temp directories, and TTL values.

---

## Testing expectations

Agents should add or update tests when implementing non-trivial behavior.

### Priority areas for tests

* YouTube URL validation
* webhook synchronization logic
* active job lock behavior
* queue dispatch behavior
* fallback from regular subtitles to auto-subs
* temp file cleanup behavior
* Telegram webhook controller behavior

### Test style

Prefer focused automated tests:

* unit tests for parsing, validation, and orchestration logic
* integration tests for service wiring when useful
* avoid unnecessary end-to-end complexity for every change

Run tests inside Docker.

Example:

```bash
docker compose exec app php bin/phpunit
```

---

## Logging and observability

Agents should preserve useful operational visibility.

At minimum, log:

* webhook processing failures
* invalid URLs
* lock conflicts for active users
* queue dispatch failures
* `yt-dlp` execution failures
* Telegram API upload failures
* temp file cleanup failures

Avoid leaking secrets or full sensitive URLs in logs when not necessary.

---

## How to use MCP tools in this project

This Codex environment has MCP servers for `context7` and `filesystem`.
Agents should use them deliberately.

### `context7`

Use `context7` for up-to-date library and framework documentation, especially when working with:

* Symfony components and configuration
* Symfony Messenger
* Redis integration
* Telegram Bot API wrappers/libraries
* Docker-related framework integration
* `yt-dlp` usage patterns if relevant documentation is available

Use it when you need authoritative docs, exact config syntax, or version-specific guidance.

### `filesystem`

Use `filesystem` to inspect and modify the actual repository files.
Prefer reading the real project structure before proposing changes.

Typical uses:

* inspect current directory structure
* read existing Symfony config
* inspect Docker files
* inspect service names in `docker-compose.yml`
* check whether commands, handlers, or services already exist
* modify files consistently with the current codebase

### MCP usage principle

Do not guess project structure if `filesystem` can confirm it.
Do not guess framework syntax if `context7` can verify it.

---

## Agent workflow expectations

Before implementing changes, agents should:

1. Inspect the repository with `filesystem`.
2. Identify the real Docker service names.
3. Identify the actual Symfony structure and conventions.
4. Check whether similar services/commands already exist.
5. Use `context7` when exact framework/library behavior matters.
6. Only then implement changes.

When making changes:

* prefer small, coherent patches
* preserve existing conventions
* avoid unrelated refactors
* keep responsibilities separated
* add or update tests where appropriate

After changes:

* run relevant tests inside Docker
* run relevant Symfony commands inside Docker if needed
* summarize what changed
* note any assumptions or follow-up work

---

## Implementation preferences

Agents should prefer:

* explicit services over hidden magic
* small focused classes
* constructor injection
* clear naming
* framework-native solutions
* config via env variables
* deterministic cleanup behavior
* predictable error messages for users

Agents should avoid:

* putting business logic in controllers
* host-only execution instructions
* tight coupling between Telegram handling and `yt-dlp` shell logic
* introducing a database unless the task explicitly requires it
* storing unnecessary job history in the MVP
* broad speculative refactors

---

## Definition of done for most tasks

A task is usually complete when:

* code follows the project architecture
* business rules are preserved
* configuration is environment-driven
* Docker-based execution assumptions are respected
* tests were added or updated where appropriate
* commands/examples use container execution
* no secrets are hardcoded
* the change is documented clearly in the response

---

## Commit message requirement

For any implementation task, agents should end their final response with a concise English commit message suggestion.
Use imperative style.

Examples:

* `Add Telegram webhook sync command`
* `Implement Redis-based per-user job locking`
* `Add yt-dlp subtitle download worker flow`

If a task includes multiple coherent changes, provide one compact commit message that covers them all.

---

## Notes for agents

If project reality differs from this file, prefer:

1. the actual codebase,
2. explicit user instructions,
3. then this file.

This file is a working guide, not a substitute for reading the repository.
