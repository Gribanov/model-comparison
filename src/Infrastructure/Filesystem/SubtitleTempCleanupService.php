<?php

namespace App\Infrastructure\Filesystem;

class SubtitleTempCleanupService
{
    public function __construct(
        private SubtitleWorkspaceManager $workspaceManager,
        private string $subtitleTempDir,
        private int $tempFileTtl,
    ) {
    }

    public function cleanup(): SubtitleTempCleanupResult
    {
        $rootDir = rtrim($this->subtitleTempDir, '/');

        if ($rootDir === '' || !is_dir($rootDir)) {
            return new SubtitleTempCleanupResult(false, 0);
        }

        $cutoff = time() - max(1, $this->tempFileTtl);
        $deletedCount = 0;

        $iterator = new \DirectoryIterator($rootDir);
        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            $deletedCount += $this->cleanupEntry($item->getPathname(), $cutoff);
        }

        return new SubtitleTempCleanupResult(true, $deletedCount);
    }

    private function cleanupEntry(string $path, int $cutoff): int
    {
        $mtime = @filemtime($path);

        if ($mtime === false) {
            return 0;
        }

        if (is_file($path) || is_link($path)) {
            if ($mtime <= $cutoff) {
                $this->workspaceManager->removePath($path);

                return 1;
            }

            return 0;
        }

        if (!is_dir($path)) {
            return 0;
        }

        if ($mtime <= $cutoff) {
            $this->workspaceManager->removePath($path);

            return 1;
        }

        $deletedCount = 0;
        $iterator = new \DirectoryIterator($path);
        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            $deletedCount += $this->cleanupEntry($item->getPathname(), $cutoff);
        }

        return $deletedCount;
    }
}
