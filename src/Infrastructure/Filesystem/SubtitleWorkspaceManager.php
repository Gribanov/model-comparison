<?php

namespace App\Infrastructure\Filesystem;

use RuntimeException;

class SubtitleWorkspaceManager
{
    public function __construct(
        private string $subtitleTempDir,
    ) {
    }

    public function createWorkspace(string $prefix = 'job_'): string
    {
        $baseDir = rtrim($this->subtitleTempDir, '/');
        if ($baseDir === '') {
            throw new RuntimeException('APP_SUBTITLE_TEMP_DIR is not configured.');
        }

        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new RuntimeException(sprintf('Unable to create subtitle temp directory "%s".', $baseDir));
        }

        $workspace = $baseDir.'/'.sprintf('%s%s_%s', $prefix, date('Ymd_His'), bin2hex(random_bytes(4)));

        if (!mkdir($workspace, 0775, true) && !is_dir($workspace)) {
            throw new RuntimeException(sprintf('Unable to create subtitle workspace "%s".', $workspace));
        }

        return $workspace;
    }

    public function removePath(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }

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
