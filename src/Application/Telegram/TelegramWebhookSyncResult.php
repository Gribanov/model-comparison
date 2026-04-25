<?php

namespace App\Application\Telegram;

final readonly class TelegramWebhookSyncResult
{
    public function __construct(
        public bool $changed,
        public string $expectedUrl,
        public ?string $currentUrl,
        public string $finalUrl,
    ) {
    }
}

