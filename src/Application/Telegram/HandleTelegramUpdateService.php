<?php

namespace App\Application\Telegram;

use App\Infrastructure\Lock\UserActiveJobLockService;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Message\DownloadSubtitlesMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

class HandleTelegramUpdateService
{
    private const INVALID_YOUTUBE_URL_MESSAGE = 'Please send a regular YouTube video link, such as https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID. Shorts, playlists, channels, and non-YouTube links are not supported yet.';
    private const ACTIVE_JOB_MESSAGE = 'Your previous request is still being processed. Please wait for it to finish before sending another YouTube link.';
    private const QUEUED_MESSAGE = 'Your subtitle request has been queued. I will reply again when it is ready.';

    public function __construct(
        private TelegramUpdateParser $parser,
        private YouTubeVideoUrlParser $youtubeVideoUrlParser,
        private TelegramBotClient $telegramBotClient,
        private UserActiveJobLockService $userActiveJobLockService,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function handle(array $update): void
    {
        $textMessage = $this->parser->parse($update);

        if ($textMessage === null) {
            return;
        }

        $youtubeVideoUrl = $this->youtubeVideoUrlParser->parse($textMessage->text);

        if ($youtubeVideoUrl === null) {
            $this->telegramBotClient->sendMessage($textMessage->chatId, self::INVALID_YOUTUBE_URL_MESSAGE);

            return;
        }

        if (!$this->userActiveJobLockService->acquire($textMessage->userId)) {
            $this->telegramBotClient->sendMessage($textMessage->chatId, self::ACTIVE_JOB_MESSAGE);

            return;
        }

        try {
            $this->messageBus->dispatch(new DownloadSubtitlesMessage(
                $textMessage->userId,
                $textMessage->chatId,
                $youtubeVideoUrl->canonicalUrl,
            ));
            $this->telegramBotClient->sendMessage($textMessage->chatId, self::QUEUED_MESSAGE);
        } catch (Throwable $exception) {
            $this->userActiveJobLockService->release($textMessage->userId);

            throw $exception;
        }
    }
}
