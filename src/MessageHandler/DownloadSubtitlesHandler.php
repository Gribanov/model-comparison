<?php

namespace App\MessageHandler;

use App\Infrastructure\Filesystem\SubtitleWorkspaceManager;
use App\Infrastructure\Telegram\TelegramApiException;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Infrastructure\Youtube\SubtitleDownloadArtifact;
use App\Infrastructure\Youtube\SubtitlesUnavailableException;
use App\Infrastructure\Youtube\YtDlpSubtitleDownloader;
use App\Infrastructure\Lock\UserActiveJobLockService;
use App\Message\DownloadSubtitlesMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final class DownloadSubtitlesHandler
{
    private const NO_SUBTITLES_MESSAGE = 'I could not find subtitles for that video. Please try another regular YouTube video link.';
    private const GENERIC_FAILURE_MESSAGE = 'I could not process that video right now. Please try again later.';

    public function __construct(
        private YtDlpSubtitleDownloader $downloader,
        private TelegramBotClient $telegramBotClient,
        private UserActiveJobLockService $userActiveJobLockService,
        private SubtitleWorkspaceManager $workspaceManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DownloadSubtitlesMessage $message): void
    {
        $artifact = null;

        try {
            $artifact = $this->downloader->download($message->youtubeUrl);
            $this->telegramBotClient->sendDocument($message->chatId, $artifact->subtitleFilePath);
        } catch (SubtitlesUnavailableException $exception) {
            $this->logger->info('No subtitles available for requested video.', [
                'telegram_user_id' => $message->telegramUserId,
                'chat_id' => $message->chatId,
                'youtube_url' => $message->youtubeUrl,
            ]);
            $this->safeSendMessage($message->chatId, self::NO_SUBTITLES_MESSAGE);
        } catch (\Throwable $exception) {
            $this->logger->error('Subtitle download or Telegram upload failed. youtube_url="{youtube_url}" telegram_user_id="{telegram_user_id}" chat_id="{chat_id}" exception="{exception_class}: {exception_message}"', [
                'exception' => $exception,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'telegram_user_id' => $message->telegramUserId,
                'chat_id' => $message->chatId,
                'youtube_url' => $message->youtubeUrl,
            ]);
            $this->safeSendMessage($message->chatId, self::GENERIC_FAILURE_MESSAGE);
        } finally {
            if ($artifact instanceof SubtitleDownloadArtifact) {
                $this->workspaceManager->removePath($artifact->subtitleFilePath);
                $this->workspaceManager->removePath($artifact->workspacePath);
            }

            $this->userActiveJobLockService->release($message->telegramUserId);
        }
    }

    private function safeSendMessage(int $chatId, string $text): void
    {
        try {
            $this->telegramBotClient->sendMessage($chatId, $text);
        } catch (TelegramApiException $exception) {
            $this->logger->error('Failed to send Telegram status/error message. chat_id="{chat_id}" exception="{exception_class}: {exception_message}"', [
                'exception' => $exception,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'chat_id' => $chatId,
            ]);
        }
    }
}
