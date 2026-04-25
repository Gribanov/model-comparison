<?php

namespace App\Tests\Unit\Infrastructure\Filesystem;

use App\Infrastructure\Filesystem\SubtitleTempCleanupService;
use App\Infrastructure\Filesystem\SubtitleWorkspaceManager;
use PHPUnit\Framework\TestCase;

final class SubtitleTempCleanupServiceTest extends TestCase
{
    public function testDeletesStaleFilesAndDirectoriesAndKeepsFreshItems(): void
    {
        $root = $this->createTempDirectory();
        $workspaceManager = new SubtitleWorkspaceManager($root);
        $service = new SubtitleTempCleanupService($workspaceManager, $root, 3600);

        $now = time();
        $freshFile = $root.'/fresh.srt';
        file_put_contents($freshFile, 'fresh');
        touch($freshFile, $now);

        $staleFile = $root.'/stale.srt';
        file_put_contents($staleFile, 'stale');
        touch($staleFile, $now - 7200);

        $freshDir = $root.'/fresh-job';
        mkdir($freshDir);
        file_put_contents($freshDir.'/keep.srt', 'keep');
        touch($freshDir, $now);
        touch($freshDir.'/keep.srt', $now);

        $staleDir = $root.'/stale-job';
        mkdir($staleDir);
        file_put_contents($staleDir.'/remove.srt', 'remove');
        touch($staleDir.'/remove.srt', $now - 7200);
        touch($staleDir, $now - 7200);

        $result = $service->cleanup();

        self::assertTrue($result->directoryExists);
        self::assertSame(2, $result->deletedCount);
        self::assertFileExists($freshFile);
        self::assertFileExists($freshDir.'/keep.srt');
        self::assertFileDoesNotExist($staleFile);
        self::assertFileDoesNotExist($staleDir);

        $this->removePath($root);
    }

    public function testHandlesMissingDirectoryGracefully(): void
    {
        $root = sys_get_temp_dir().'/telegram-bot-missing-'.bin2hex(random_bytes(4));
        $workspaceManager = new SubtitleWorkspaceManager($root);
        $service = new SubtitleTempCleanupService($workspaceManager, $root, 3600);

        $result = $service->cleanup();

        self::assertFalse($result->directoryExists);
        self::assertSame(0, $result->deletedCount);
    }

    private function createTempDirectory(): string
    {
        $dir = sys_get_temp_dir().'/telegram-bot-cleanup-'.bin2hex(random_bytes(4));
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
