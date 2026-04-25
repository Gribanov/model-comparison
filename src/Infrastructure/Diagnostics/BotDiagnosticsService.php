<?php

namespace App\Infrastructure\Diagnostics;

use App\Application\Telegram\TelegramWebhookUrlFactory;
use App\Infrastructure\Filesystem\SubtitleWorkspaceManager;
use App\Infrastructure\Lock\RedisConnectionFactory;
use App\Infrastructure\Telegram\TelegramBotClient;
use Symfony\Component\Process\Process;

final class BotDiagnosticsService
{
    public function __construct(
        private RedisConnectionFactory $redisConnectionFactory,
        private TelegramBotClient $telegramBotClient,
        private TelegramWebhookUrlFactory $telegramWebhookUrlFactory,
        private SubtitleWorkspaceManager $workspaceManager,
        private string $telegramBotToken,
        private string $telegramWebhookBaseUrl,
        private string $telegramWebhookSecret,
        private string $redisDsn,
        private string $ytdlpBin,
        private int $processTimeoutSeconds,
        private bool $checkRemoteWebhook,
    ) {
    }

    public function run(): BotDiagnosticsResult
    {
        $result = new BotDiagnosticsResult();

        $this->checkConfig($result);
        $this->checkRedis($result);
        $this->checkTempDirectory($result);
        $this->checkYtDlp($result);
        $this->checkWebhook($result);

        return $result;
    }

    private function checkConfig(BotDiagnosticsResult $result): void
    {
        $result->addCheck('TELEGRAM_BOT_TOKEN', trim($this->telegramBotToken) !== '' ? 'OK' : 'FAIL', trim($this->telegramBotToken) === '' ? 'missing' : 'configured');
        $result->addCheck('TELEGRAM_WEBHOOK_BASE_URL', trim($this->telegramWebhookBaseUrl) !== '' ? 'OK' : 'FAIL', trim($this->telegramWebhookBaseUrl) === '' ? 'missing' : $this->telegramWebhookBaseUrl);
        $result->addCheck('TELEGRAM_WEBHOOK_SECRET', trim($this->telegramWebhookSecret) !== '' ? 'OK' : 'FAIL', trim($this->telegramWebhookSecret) === '' ? 'missing' : 'configured');

        if (trim($this->redisDsn) === '') {
            $result->addCheck('MESSENGER_TRANSPORT_DSN', 'FAIL', 'missing');
            return;
        }

        $result->addCheck('MESSENGER_TRANSPORT_DSN', str_starts_with(trim($this->redisDsn), 'redis://') ? 'OK' : 'FAIL', $this->redisDsn);
    }

    private function checkRedis(BotDiagnosticsResult $result): void
    {
        try {
            $redis = $this->redisConnectionFactory->create();
            $pong = $redis->ping();
            $healthy = $this->isPong($pong);

            $result->addCheck('Redis connectivity', $healthy ? 'OK' : 'FAIL', $healthy ? 'PONG' : sprintf('unexpected ping response: %s', $this->stringify($pong)));
        } catch (\Throwable $exception) {
            $result->addCheck('Redis connectivity', 'FAIL', $exception->getMessage());
        }
    }

    private function checkTempDirectory(BotDiagnosticsResult $result): void
    {
        try {
            $workspace = $this->workspaceManager->createWorkspace('diagnostic_');
            $this->workspaceManager->removePath($workspace);

            $result->addCheck('Subtitle temp directory', 'OK', $workspace);
        } catch (\Throwable $exception) {
            $result->addCheck('Subtitle temp directory', 'FAIL', $exception->getMessage());
        }
    }

    private function checkYtDlp(BotDiagnosticsResult $result): void
    {
        if (!is_file($this->ytdlpBin) || !is_executable($this->ytdlpBin)) {
            $result->addCheck('yt-dlp binary', 'FAIL', sprintf('not executable: %s', $this->ytdlpBin));
            return;
        }

        $process = new Process([$this->ytdlpBin, '--version']);
        $process->setTimeout($this->processTimeoutSeconds > 0 ? $this->processTimeoutSeconds : null);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = trim($process->getErrorOutput()) !== '' ? trim($process->getErrorOutput()) : trim($process->getOutput());
            $result->addCheck('yt-dlp binary', 'FAIL', $output !== '' ? $output : 'version probe failed');

            return;
        }

        $version = trim($process->getOutput());
        $result->addCheck('yt-dlp binary', 'OK', $version !== '' ? $version : $this->ytdlpBin);
    }

    private function checkWebhook(BotDiagnosticsResult $result): void
    {
        if (trim($this->telegramBotToken) === '' || trim($this->telegramWebhookBaseUrl) === '' || trim($this->telegramWebhookSecret) === '') {
            $result->addCheck('Webhook registration', 'FAIL', 'missing Telegram configuration');
            return;
        }

        $expected = $this->telegramWebhookUrlFactory->buildExpectedUrl();

        if (!$this->checkRemoteWebhook) {
            $result->addCheck('Webhook registration', 'SKIP', sprintf('expected %s', $expected));

            return;
        }

        try {
            $current = $this->telegramBotClient->getWebhookInfo()->url();

            $result->addCheck(
                'Webhook registration',
                $current === $expected ? 'OK' : 'FAIL',
                $current === $expected ? $current : sprintf('expected %s, got %s', $expected, $current),
            );
        } catch (\Throwable $exception) {
            $result->addCheck('Webhook registration', 'FAIL', $exception->getMessage());
        }
    }

    private function isPong(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return str_contains(strtoupper($value), 'PONG');
        }

        return $value !== false && $value !== null;
    }

    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
