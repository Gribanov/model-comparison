<?php

namespace App\Tests\Unit\Application\Telegram;

use App\Application\Telegram\HandleTelegramUpdateService;
use App\Application\Telegram\TelegramUpdateParser;
use App\Application\Telegram\YouTubeVideoUrlParser;
use App\Infrastructure\Telegram\TelegramBotClient;
use PHPUnit\Framework\TestCase;

final class HandleTelegramUpdateServiceTest extends TestCase
{
    public function testSendsFriendlyReplyForInvalidYoutubeUrl(): void
    {
        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::once())
            ->method('sendMessage')
            ->with(
                123,
                'Please send a regular YouTube video link, such as https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID. Shorts, playlists, channels, and non-YouTube links are not supported yet.',
            );

        $service = new HandleTelegramUpdateService(
            new TelegramUpdateParser(),
            new YouTubeVideoUrlParser(),
            $telegramBotClient,
        );

        $service->handle([
            'message' => [
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
                'text' => 'https://www.youtube.com/playlist?list=PL1234567890',
            ],
        ]);
    }

    public function testIgnoresUnsupportedUpdateTypes(): void
    {
        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::never())->method('sendMessage');

        $service = new HandleTelegramUpdateService(
            new TelegramUpdateParser(),
            new YouTubeVideoUrlParser(),
            $telegramBotClient,
        );

        $service->handle([
            'callback_query' => [
                'id' => '1',
            ],
        ]);
    }
}
