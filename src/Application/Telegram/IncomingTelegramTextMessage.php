<?php

namespace App\Application\Telegram;

final readonly class IncomingTelegramTextMessage
{
    public function __construct(
        public int $chatId,
        public int $userId,
        public string $text,
    ) {
    }
}

