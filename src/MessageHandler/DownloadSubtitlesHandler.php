<?php

namespace App\MessageHandler;

use App\Message\DownloadSubtitlesMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DownloadSubtitlesHandler
{
    public function __invoke(DownloadSubtitlesMessage $message): void
    {
        // Placeholder for the future yt-dlp subtitle download workflow.
    }
}
