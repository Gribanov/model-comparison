<?php

namespace App\Infrastructure\Telegram;

final readonly class TelegramWebhookInfo
{
    public function __construct(
        public string $url,
    ) {
    }

    public function url(): string
    {
        return $this->url;
    }
}

