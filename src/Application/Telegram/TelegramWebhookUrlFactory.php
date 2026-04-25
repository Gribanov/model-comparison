<?php

namespace App\Application\Telegram;

final readonly class TelegramWebhookUrlFactory
{
    public function __construct(
        private string $telegramWebhookBaseUrl,
        private string $telegramWebhookSecretPathSegment,
    ) {
    }

    public function buildExpectedUrl(): string
    {
        return rtrim($this->telegramWebhookBaseUrl, '/').'/telegram/webhook/'.$this->telegramWebhookSecretPathSegment;
    }
}

