<?php

namespace App\Tests\Unit\Infrastructure\Diagnostics;

use App\Application\Telegram\TelegramWebhookUrlFactory;
use App\Infrastructure\Diagnostics\BotDiagnosticsService;
use App\Infrastructure\Filesystem\SubtitleWorkspaceManager;
use App\Infrastructure\Lock\RedisConnectionFactory;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Infrastructure\Telegram\TelegramWebhookInfo;
use PHPUnit\Framework\TestCase;
use Redis;

final class BotDiagnosticsServiceTest extends TestCase
{
    public function testRunReportsHealthySystem(): void
    {
        $root = $this->createTempDirectory();
        $workspaceManager = new SubtitleWorkspaceManager($root.'/subtitles');
        $ytdlpBin = $root.'/yt-dlp';
        file_put_contents($ytdlpBin, "#!/bin/sh\necho yt-dlp 2025.01.01\n");
        chmod($ytdlpBin, 0755);

        $redis = $this->createMock(Redis::class);
        $redis->method('ping')->willReturn('PONG');

        $redisFactory = new class($redis) extends RedisConnectionFactory {
            public function __construct(private Redis $redis)
            {
                parent::__construct('redis://:password@redis:6379/messages');
            }

            public function create(): Redis
            {
                return $this->redis;
            }
        };

        $telegramClient = $this->createMock(TelegramBotClient::class);
        $telegramClient->method('getWebhookInfo')->willReturn(new TelegramWebhookInfo('https://example.com/telegram/webhook/secret'));

        $service = new BotDiagnosticsService(
            $redisFactory,
            $telegramClient,
            new TelegramWebhookUrlFactory('https://example.com', 'secret'),
            $workspaceManager,
            'bot-token',
            'https://example.com',
            'secret',
            'redis://:password@redis:6379/messages',
            $ytdlpBin,
            10,
            false,
        );

        $result = $service->run();

        self::assertFalse($result->hasFailures());
        self::assertCount(8, $result->all());
        self::assertSame('OK', $this->statusFor($result->all(), 'TELEGRAM_BOT_TOKEN'));
        self::assertSame('OK', $this->statusFor($result->all(), 'Redis connectivity'));
        self::assertSame('SKIP', $this->statusFor($result->all(), 'Webhook registration'));

        $this->removePath($root);
    }

    public function testRunReportsFailuresForBrokenRedisAndWebhook(): void
    {
        $root = $this->createTempDirectory();
        $workspaceManager = new SubtitleWorkspaceManager($root.'/subtitles');
        $ytdlpBin = $root.'/yt-dlp';
        file_put_contents($ytdlpBin, "#!/bin/sh\nexit 1\n");
        chmod($ytdlpBin, 0755);

        $redisFactory = new class extends RedisConnectionFactory {
            public function __construct()
            {
                parent::__construct('redis://:password@redis:6379/messages');
            }

            public function create(): Redis
            {
                throw new \RuntimeException('Redis unavailable');
            }
        };

        $telegramClient = $this->createMock(TelegramBotClient::class);
        $telegramClient->method('getWebhookInfo')->willReturn(new TelegramWebhookInfo('https://example.com/wrong'));

        $service = new BotDiagnosticsService(
            $redisFactory,
            $telegramClient,
            new TelegramWebhookUrlFactory('https://example.com', 'secret'),
            $workspaceManager,
            'bot-token',
            'https://example.com',
            'secret',
            'redis://:password@redis:6379/messages',
            $ytdlpBin,
            10,
            true,
        );

        $result = $service->run();

        self::assertTrue($result->hasFailures());
        self::assertSame('FAIL', $this->statusFor($result->all(), 'Redis connectivity'));
        self::assertSame('FAIL', $this->statusFor($result->all(), 'Webhook registration'));

        $this->removePath($root);
    }

    private function createTempDirectory(): string
    {
        $dir = sys_get_temp_dir().'/telegram-bot-diagnostics-'.bin2hex(random_bytes(4));
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            self::fail(sprintf('Unable to create temp directory: %s', $dir));
        }

        return $dir;
    }

    private function removePath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    /**
     * @param list<array{label:string,status:string,details:string}> $checks
     */
    private function statusFor(array $checks, string $label): string
    {
        foreach ($checks as $check) {
            if ($check['label'] === $label) {
                return $check['status'];
            }
        }

        self::fail(sprintf('Missing diagnostic check: %s', $label));
    }
}
