<?php

namespace App\Message;

final readonly class DownloadSubtitlesMessage
{
    public function __construct(
        public int $telegramUserId,
        public int $chatId,
        public string $youtubeUrl,
    ) {
    }
}
