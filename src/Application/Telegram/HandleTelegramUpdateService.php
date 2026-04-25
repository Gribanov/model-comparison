<?php

namespace App\Application\Telegram;

use App\Infrastructure\Telegram\TelegramBotClient;

class HandleTelegramUpdateService
{
    private const INVALID_YOUTUBE_URL_MESSAGE = 'Please send a regular YouTube video link, such as https://www.youtube.com/watch?v=VIDEO_ID or https://youtu.be/VIDEO_ID. Shorts, playlists, channels, and non-YouTube links are not supported yet.';

    public function __construct(
        private TelegramUpdateParser $parser,
        private YouTubeVideoUrlParser $youtubeVideoUrlParser,
        private TelegramBotClient $telegramBotClient,
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

        // Placeholder for future queue dispatch and bot workflow orchestration.
        // The canonical URL is available here for the future async job flow:
        // $youtubeVideoUrl->canonicalUrl
    }
}
