<?php

namespace App\Tests\Unit\Application\Telegram;

use App\Application\Telegram\HandleTelegramUpdateService;
use App\Application\Telegram\TelegramUpdateParser;
use App\Application\Telegram\YouTubeVideoUrlParser;
use App\Infrastructure\Lock\UserActiveJobLockService;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Message\DownloadSubtitlesMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class HandleTelegramUpdateServiceTest extends TestCase
{
    public function testQueuesValidYoutubeUrlWhenUserHasNoActiveJob(): void
    {
        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::once())
            ->method('sendMessage')
            ->with(123, 'Your subtitle request has been queued. I will reply again when it is ready.');

        $userActiveJobLockService = $this->createMock(UserActiveJobLockService::class);
        $userActiveJobLockService->expects(self::once())
            ->method('acquire')
            ->with(456)
            ->willReturn(true);
        $userActiveJobLockService->expects(self::never())->method('release');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (DownloadSubtitlesMessage $message): bool {
                self::assertSame(456, $message->telegramUserId);
                self::assertSame(123, $message->chatId);
                self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $message->youtubeUrl);

                return true;
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $service = new HandleTelegramUpdateService(
            new TelegramUpdateParser(),
            new YouTubeVideoUrlParser(),
            $telegramBotClient,
            $userActiveJobLockService,
            $messageBus,
        );

        $service->handle([
            'message' => [
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
                'text' => 'https://youtu.be/dQw4w9WgXcQ',
            ],
        ]);
    }

    public function testSendsBusyMessageWhenUserAlreadyHasActiveJob(): void
    {
        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::once())
            ->method('sendMessage')
            ->with(123, 'Your previous request is still being processed. Please wait for it to finish before sending another YouTube link.');

        $userActiveJobLockService = $this->createMock(UserActiveJobLockService::class);
        $userActiveJobLockService->expects(self::once())
            ->method('acquire')
            ->with(456)
            ->willReturn(false);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $service = new HandleTelegramUpdateService(
            new TelegramUpdateParser(),
            new YouTubeVideoUrlParser(),
            $telegramBotClient,
            $userActiveJobLockService,
            $messageBus,
        );

        $service->handle([
            'message' => [
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
                'text' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ],
        ]);
    }

    public function testInvalidYoutubeUrlDoesNotAcquireLockOrDispatchJob(): void
    {
        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::once())
            ->method('sendMessage')
            ->with(123, 'Please send a regular YouTube video link, such as https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID. Shorts, playlists, channels, and non-YouTube links are not supported yet.');

        $userActiveJobLockService = $this->createMock(UserActiveJobLockService::class);
        $userActiveJobLockService->expects(self::never())->method('acquire');
        $userActiveJobLockService->expects(self::never())->method('release');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $service = new HandleTelegramUpdateService(
            new TelegramUpdateParser(),
            new YouTubeVideoUrlParser(),
            $telegramBotClient,
            $userActiveJobLockService,
            $messageBus,
        );

        $service->handle([
            'message' => [
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
                'text' => 'https://example.com/watch?v=dQw4w9WgXcQ',
            ],
        ]);
    }

    public function testIgnoresUnsupportedUpdateTypes(): void
    {
        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::never())->method('sendMessage');

        $userActiveJobLockService = $this->createMock(UserActiveJobLockService::class);
        $userActiveJobLockService->expects(self::never())->method('acquire');
        $userActiveJobLockService->expects(self::never())->method('release');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $service = new HandleTelegramUpdateService(
            new TelegramUpdateParser(),
            new YouTubeVideoUrlParser(),
            $telegramBotClient,
            $userActiveJobLockService,
            $messageBus,
        );

        $service->handle([
            'callback_query' => [
                'id' => '1',
            ],
        ]);
    }
}
