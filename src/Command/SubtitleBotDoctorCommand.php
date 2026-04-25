<?php

namespace App\Command;

use App\Infrastructure\Filesystem\SubtitleWorkspaceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:subtitle-bot:doctor',
    description: 'Check bot configuration and temp directory accessibility.',
)]
final class SubtitleBotDoctorCommand extends Command
{
    public function __construct(
        private SubtitleWorkspaceManager $workspaceManager,
        private string $telegramBotToken,
        private string $telegramWebhookBaseUrl,
        private string $telegramWebhookSecret,
        private string $redisDsn,
        private string $ytdlpBin,
        private int $userJobLockTtl,
        private int $tempFileTtl,
        private int $processTimeoutSeconds,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $issues = [];

        $io->title('Subtitle bot diagnostics');

        $io->table(
            ['Check', 'Status'],
            [
                ['TELEGRAM_BOT_TOKEN', trim($this->telegramBotToken) === '' ? 'missing' : 'configured'],
                ['TELEGRAM_WEBHOOK_BASE_URL', trim($this->telegramWebhookBaseUrl) === '' ? 'missing' : $this->telegramWebhookBaseUrl],
                ['TELEGRAM_WEBHOOK_SECRET', trim($this->telegramWebhookSecret) === '' ? 'missing' : 'configured'],
                ['MESSENGER_TRANSPORT_DSN', str_starts_with(trim($this->redisDsn), 'redis://') ? 'redis' : 'unexpected'],
                ['YTDLP_BIN', is_file($this->ytdlpBin) && is_executable($this->ytdlpBin) ? 'executable' : 'not executable'],
                ['USER_JOB_LOCK_TTL', (string) $this->userJobLockTtl],
                ['TEMP_FILE_TTL', (string) $this->tempFileTtl],
                ['YTDLP_TIMEOUT', (string) $this->processTimeoutSeconds],
            ],
        );

        $parsedWebhookUrl = parse_url($this->telegramWebhookBaseUrl);
        if (is_array($parsedWebhookUrl) && (($parsedWebhookUrl['scheme'] ?? null) !== 'https')) {
            $io->warning('Webhook base URL should use HTTPS in production so Telegram can reach it.');
        }

        if (trim($this->telegramBotToken) === '') {
            $issues[] = 'Telegram bot token is missing.';
        }

        if (trim($this->telegramWebhookBaseUrl) === '') {
            $issues[] = 'Telegram webhook base URL is missing.';
        }

        if (trim($this->telegramWebhookSecret) === '') {
            $issues[] = 'Telegram webhook secret is missing.';
        }

        if (!str_starts_with(trim($this->redisDsn), 'redis://')) {
            $issues[] = 'Messenger transport DSN is not a Redis DSN.';
        }

        if (!is_file($this->ytdlpBin) || !is_executable($this->ytdlpBin)) {
            $issues[] = sprintf('yt-dlp binary is not executable: %s', $this->ytdlpBin);
        }

        $probeWorkspace = null;
        try {
            $probeWorkspace = $this->workspaceManager->createWorkspace('doctor_');
        } catch (\Throwable $exception) {
            $issues[] = sprintf('Subtitle temp directory is not accessible: %s', $exception->getMessage());
        } finally {
            if (is_string($probeWorkspace)) {
                $this->workspaceManager->removePath($probeWorkspace);
            }
        }

        if ($issues !== []) {
            $io->error($issues);

            return Command::FAILURE;
        }

        $io->success('Bot configuration and temp directory look healthy.');

        return Command::SUCCESS;
    }
}
