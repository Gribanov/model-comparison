<?php

namespace App\Tests\Unit\Application\Telegram;

use App\Application\Telegram\TelegramUpdateParser;
use PHPUnit\Framework\TestCase;

final class TelegramUpdateParserTest extends TestCase
{
    public function testParsesTextMessage(): void
    {
        $parser = new TelegramUpdateParser();

        $message = $parser->parse([
            'message' => [
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
                'text' => 'hello world',
            ],
        ]);

        self::assertNotNull($message);
        self::assertSame(123, $message->chatId);
        self::assertSame(456, $message->userId);
        self::assertSame('hello world', $message->text);
    }

    public function testIgnoresUnsupportedUpdateTypes(): void
    {
        $parser = new TelegramUpdateParser();

        self::assertNull($parser->parse([
            'callback_query' => [
                'id' => '1',
            ],
        ]));
    }
}

