<?php

namespace App\Application\Telegram;

final readonly class YouTubeVideoUrl
{
    public function __construct(
        public string $videoId,
        public string $canonicalUrl,
    ) {
    }
}
