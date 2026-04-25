<?php

namespace App\Infrastructure\Telegram;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
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

    public function sendMessage(int|string $chatId, string $text): void
    {
        $response = $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
        $this->decodeResponse($response, 'sendMessage');
    }

    public function sendDocument(int|string $chatId, string $documentPath, ?string $caption = null): void
    {
        if (!is_file($documentPath) || !is_readable($documentPath)) {
            throw new TelegramApiException(sprintf('Telegram document file is not readable: "%s".', $documentPath));
        }

        $fields = [
            'chat_id' => (string) $chatId,
            'document' => DataPart::fromPath($documentPath),
        ];

        if ($caption !== null && $caption !== '') {
            $fields['caption'] = $caption;
        }

        $formData = new FormDataPart($fields);

        $response = $this->request('sendDocument', null, [
            'headers' => $formData->getPreparedHeaders()->toArray(),
            'body' => $formData->bodyToString(),
        ]);
        $this->decodeResponse($response, 'sendDocument');
    }

    private function request(string $method, ?array $payload = [], array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        if (trim($this->telegramBotToken) === '') {
            throw new TelegramApiException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        try {
            if ($payload !== null) {
                $options['json'] = $payload;
            }

            return $this->httpClient->request(
                'POST',
                sprintf('https://api.telegram.org/bot%s/%s', $this->telegramBotToken, $method),
                $options,
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
