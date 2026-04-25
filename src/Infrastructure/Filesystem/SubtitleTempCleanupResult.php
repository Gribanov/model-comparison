<?php

namespace App\Infrastructure\Filesystem;

final readonly class SubtitleTempCleanupResult
{
    public function __construct(
        public bool $directoryExists,
        public int $deletedCount,
    ) {
    }
}
