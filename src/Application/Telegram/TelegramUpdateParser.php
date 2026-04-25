<?php

namespace App\Application\Telegram;

final class TelegramUpdateParser
{
    public function parse(array $update): ?IncomingTelegramTextMessage
    {
        $message = $update['message'] ?? null;

        if (!is_array($message)) {
            return null;
        }

        $text = $message['text'] ?? null;
        $chatId = $message['chat']['id'] ?? null;
        $userId = $message['from']['id'] ?? null;

        if (!is_string($text) || $text === '') {
            return null;
        }

        if (!$this->isIntegerLike($chatId) || !$this->isIntegerLike($userId)) {
            return null;
        }

        return new IncomingTelegramTextMessage((int) $chatId, (int) $userId, $text);
    }

    private function isIntegerLike(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && is_numeric($value));
    }
}

