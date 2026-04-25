<?php

namespace App\Tests\Unit\Application\Telegram;

use App\Application\Telegram\TelegramWebhookSynchronizer;
use App\Application\Telegram\TelegramWebhookUrlFactory;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Infrastructure\Telegram\TelegramWebhookInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TelegramWebhookSynchronizerTest extends TestCase
{
    public function testDoesNothingWhenWebhookAlreadyMatches(): void
    {
        $client = $this->createMock(TelegramBotClient::class);
        $client->expects(self::once())
            ->method('getWebhookInfo')
            ->willReturn(new TelegramWebhookInfo('https://example.com/telegram/webhook/secret'));
        $client->expects(self::never())
            ->method('setWebhook');

        $synchronizer = new TelegramWebhookSynchronizer(
            $client,
            new TelegramWebhookUrlFactory('https://example.com', 'secret'),
        );

        $result = $synchronizer->synchronize();

        self::assertFalse($result->changed);
        self::assertSame('https://example.com/telegram/webhook/secret', $result->expectedUrl);
        self::assertSame('https://example.com/telegram/webhook/secret', $result->currentUrl);
        self::assertSame('https://example.com/telegram/webhook/secret', $result->finalUrl);
    }

    public function testRegistersWebhookWhenItDiffers(): void
    {
        $client = $this->createMock(TelegramBotClient::class);
        $client->expects(self::exactly(2))
            ->method('getWebhookInfo')
            ->willReturnOnConsecutiveCalls(
                new TelegramWebhookInfo('https://old.example.com/telegram/webhook/other'),
                new TelegramWebhookInfo('https://example.com/telegram/webhook/secret'),
            );
        $client->expects(self::once())
            ->method('setWebhook')
            ->with('https://example.com/telegram/webhook/secret');

        $synchronizer = new TelegramWebhookSynchronizer(
            $client,
            new TelegramWebhookUrlFactory('https://example.com/', 'secret'),
        );

        $result = $synchronizer->synchronize();

        self::assertTrue($result->changed);
        self::assertSame('https://example.com/telegram/webhook/secret', $result->expectedUrl);
        self::assertSame('https://old.example.com/telegram/webhook/other', $result->currentUrl);
        self::assertSame('https://example.com/telegram/webhook/secret', $result->finalUrl);
    }
}
