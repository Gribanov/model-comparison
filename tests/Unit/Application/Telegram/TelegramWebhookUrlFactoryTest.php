<?php

namespace App\Tests\Unit\Application\Telegram;

use App\Application\Telegram\TelegramWebhookUrlFactory;
use PHPUnit\Framework\TestCase;

final class TelegramWebhookUrlFactoryTest extends TestCase
{
    public function testBuildsExpectedWebhookUrl(): void
    {
        $factory = new TelegramWebhookUrlFactory('https://example.com/', 'secret-path');

        self::assertSame(
            'https://example.com/telegram/webhook/secret-path',
            $factory->buildExpectedUrl(),
        );
    }
}

