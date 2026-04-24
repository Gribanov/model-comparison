You are working on a Symfony project for a Telegram bot.

Goal: perform a focused refactor pass to improve code quality, consistency, and maintainability without changing the bot’s external behavior.

Context:
- The MVP is already implemented:
    - Docker / Docker Compose
    - Symfony application
    - Telegram webhook endpoint
    - webhook sync command
    - YouTube URL validation
    - Redis-backed Messenger queue
    - per-user active job locking in Redis
    - async worker using yt-dlp to fetch subtitles
    - Telegram document upload
    - hourly cleanup of stale temp files
    - diagnostics / smoke-test support
- The bot accepts only regular YouTube video links and returns subtitles as an .srt file.
- All PHP/Symfony commands must be run inside Docker containers.
- Follow AGENTS.md if present.
- Use the real repository structure and service names instead of guessing.

Goal of this step:
Improve maintainability and consistency while preserving the current feature set and business rules.

Requirements:

1. Review the current implementation for code quality issues.
    - Look for duplicated logic
    - Look for unclear naming
    - Look for classes with too many responsibilities
    - Look for infrastructure details leaking into controllers or application services
    - Look for inconsistent error handling or message formatting

2. Refactor only where it adds clear value.
    - Extract small focused services/helpers when needed
    - Improve type declarations and return types
    - Improve DTO/message/value object clarity if useful
    - Improve exception naming and structure if useful
    - Improve config parameter naming consistency
    - Keep constructor injection and framework-native patterns

3. Preserve behavior.
    - Do not change supported URL rules
    - Do not change queue semantics
    - Do not change one-active-job-per-user behavior
    - Do not add retries
    - Do not add database
    - Do not add new features unless a tiny internal helper is clearly needed

4. Improve tests where useful.
    - Update tests that become clearer after refactoring
    - Add focused tests for refactored logic if needed
    - Avoid rewriting the whole test suite without reason

5. Improve developer-facing documentation if needed.
    - If class responsibilities or important internal flows became clearer, update README or internal docs briefly
    - Keep documentation concise

6. Validate the refactor.
    - Run relevant tests inside Docker
    - Ensure commands/examples still use Docker container execution only
    - Ensure the app still behaves the same from the user’s perspective

Deliverables:
- focused maintainability refactors
- any necessary test updates
- a short summary of what was cleaned up and why

Important constraints:
- no new business features
- no broad redesign
- no speculative abstractions
- no host-only execution instructions

At the end, generate a commit message in English describing the changes.
