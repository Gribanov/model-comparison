<?php

namespace App\Infrastructure\Telegram;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class TelegramBotClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $telegramBotToken,
    ) {
    }

    public function getWebhookInfo(): TelegramWebhookInfo
    {
        $response = $this->request('getWebhookInfo');
        $data = $this->decodeResponse($response, 'getWebhookInfo');

        return new TelegramWebhookInfo((string) ($data['result']['url'] ?? ''));
    }

    public function setWebhook(string $url): void
    {
        $response = $this->request('setWebhook', ['url' => $url]);
        $this->decodeResponse($response, 'setWebhook');
    }

    private function request(string $method, array $payload = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        if (trim($this->telegramBotToken) === '') {
            throw new TelegramApiException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        try {
            return $this->httpClient->request(
                'POST',
                sprintf('https://api.telegram.org/bot%s/%s', $this->telegramBotToken, $method),
                [
                    'json' => $payload,
                ],
            );
        } catch (Throwable $exception) {
            throw new TelegramApiException(sprintf('Telegram API request "%s" failed: %s', $method, $exception->getMessage()), 0, $exception);
        }
    }

    private function decodeResponse(\Symfony\Contracts\HttpClient\ResponseInterface $response, string $method): array
    {
        try {
            $data = $response->toArray(false);
        } catch (Throwable $exception) {
            throw new TelegramApiException(sprintf('Telegram API response decoding for "%s" failed: %s', $method, $exception->getMessage()), 0, $exception);
        }

        if (!is_array($data) || !($data['ok'] ?? false)) {
            $description = is_array($data) ? (string) ($data['description'] ?? 'Unknown Telegram API error') : 'Unknown Telegram API error';

            throw new TelegramApiException(sprintf('Telegram API "%s" failed: %s', $method, $description));
        }

        return $data;
    }
}
