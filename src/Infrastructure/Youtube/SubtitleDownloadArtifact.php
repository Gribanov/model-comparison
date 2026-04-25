<?php

namespace App\Infrastructure\Youtube;

final readonly class SubtitleDownloadArtifact
{
    public function __construct(
        public string $subtitleFilePath,
        public string $workspacePath,
    ) {
    }
}
