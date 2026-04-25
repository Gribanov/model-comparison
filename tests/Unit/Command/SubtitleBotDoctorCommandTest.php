<?php

namespace App\Tests\Unit\Command;

use App\Command\SubtitleBotDoctorCommand;
use App\Infrastructure\Filesystem\SubtitleWorkspaceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtitleBotDoctorCommandTest extends TestCase
{
    public function testReportsHealthyConfiguration(): void
    {
        $root = $this->createTempDirectory();
        $workspaceManager = new SubtitleWorkspaceManager($root.'/subtitles');
        $ytdlpBin = $root.'/yt-dlp';
        file_put_contents($ytdlpBin, "#!/bin/sh\nexit 0\n");
        chmod($ytdlpBin, 0755);

        $command = new SubtitleBotDoctorCommand(
            $workspaceManager,
            'bot-token',
            'https://example.com',
            'secret',
            'redis://:password@redis:6379/messages',
            $ytdlpBin,
            1800,
            3600,
            300,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Bot configuration and temp directory look healthy.', $tester->getDisplay());

        $this->removePath($root);
    }

    private function createTempDirectory(): string
    {
        $dir = sys_get_temp_dir().'/telegram-bot-doctor-'.bin2hex(random_bytes(4));
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
}
