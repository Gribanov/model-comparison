<?php

namespace App\Tests\Unit\MessageHandler;

use App\Infrastructure\Filesystem\SubtitleWorkspaceManager;
use App\Infrastructure\Lock\UserActiveJobLockService;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Infrastructure\Youtube\SubtitleDownloadArtifact;
use App\Infrastructure\Youtube\SubtitleDownloadException;
use App\Infrastructure\Youtube\SubtitlesUnavailableException;
use App\Infrastructure\Youtube\YtDlpSubtitleDownloader;
use App\Message\DownloadSubtitlesMessage;
use App\MessageHandler\DownloadSubtitlesHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DownloadSubtitlesHandlerTest extends TestCase
{
    public function testSendsDocumentAndCleansUpOnSuccess(): void
    {
        $downloader = $this->createMock(YtDlpSubtitleDownloader::class);
        $downloader->expects(self::once())
            ->method('download')
            ->with('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->willReturn(new SubtitleDownloadArtifact('/tmp/job/subtitles.srt', '/tmp/job'));

        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::once())
            ->method('sendDocument')
            ->with(123, '/tmp/job/subtitles.srt');

        $lock = $this->createMock(UserActiveJobLockService::class);
        $lock->expects(self::once())->method('release')->with(456);

        $workspaceManager = $this->createMock(SubtitleWorkspaceManager::class);
        $expectedPaths = ['/tmp/job/subtitles.srt', '/tmp/job'];
        $workspaceManager->expects(self::exactly(2))
            ->method('removePath')
            ->willReturnCallback(static function (string $path) use (&$expectedPaths): void {
                self::assertSame(array_shift($expectedPaths), $path);
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');
        $logger->expects(self::never())->method('error');

        $handler = new DownloadSubtitlesHandler($downloader, $telegramBotClient, $lock, $workspaceManager, $logger);
        $handler(new DownloadSubtitlesMessage(456, 123, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function testSendsNoSubtitlesMessageAndReleasesLock(): void
    {
        $downloader = $this->createMock(YtDlpSubtitleDownloader::class);
        $downloader->expects(self::once())
            ->method('download')
            ->willThrowException(new SubtitlesUnavailableException('No subtitles were found for this video.'));

        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::once())
            ->method('sendMessage')
            ->with(123, 'I could not find subtitles for that video. Please try another regular YouTube video link.');

        $lock = $this->createMock(UserActiveJobLockService::class);
        $lock->expects(self::once())->method('release')->with(456);

        $workspaceManager = $this->createMock(SubtitleWorkspaceManager::class);
        $workspaceManager->expects(self::never())->method('removePath');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger->expects(self::never())->method('error');

        $handler = new DownloadSubtitlesHandler($downloader, $telegramBotClient, $lock, $workspaceManager, $logger);
        $handler(new DownloadSubtitlesMessage(456, 123, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function testSendsGenericFailureAndReleasesLockOnUnexpectedError(): void
    {
        $downloader = $this->createMock(YtDlpSubtitleDownloader::class);
        $downloader->expects(self::once())
            ->method('download')
            ->willThrowException(new SubtitleDownloadException('yt-dlp failed'));

        $telegramBotClient = $this->createMock(TelegramBotClient::class);
        $telegramBotClient->expects(self::once())
            ->method('sendMessage')
            ->with(123, 'I could not process that video right now. Please try again later.');

        $lock = $this->createMock(UserActiveJobLockService::class);
        $lock->expects(self::once())->method('release')->with(456);

        $workspaceManager = $this->createMock(SubtitleWorkspaceManager::class);
        $workspaceManager->expects(self::never())->method('removePath');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');
        $logger->expects(self::once())->method('error');

        $handler = new DownloadSubtitlesHandler($downloader, $telegramBotClient, $lock, $workspaceManager, $logger);
        $handler(new DownloadSubtitlesMessage(456, 123, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }
}
