<?php

namespace App\Application\Telegram;

use App\Infrastructure\Telegram\TelegramApiException;
use App\Infrastructure\Telegram\TelegramBotClient;

final readonly class TelegramWebhookSynchronizer
{
    public function __construct(
        private TelegramBotClient $telegramBotClient,
        private TelegramWebhookUrlFactory $webhookUrlFactory,
    ) {
    }

    public function synchronize(): TelegramWebhookSyncResult
    {
        $expectedUrl = $this->webhookUrlFactory->buildExpectedUrl();
        $currentWebhookInfo = $this->telegramBotClient->getWebhookInfo();
        $currentUrl = $this->normalizeWebhookUrl($currentWebhookInfo->url);

        if ($currentUrl === $expectedUrl) {
            return new TelegramWebhookSyncResult(false, $expectedUrl, $currentUrl, $currentUrl);
        }

        $this->telegramBotClient->setWebhook($expectedUrl);

        $finalUrl = $this->normalizeWebhookUrl($this->telegramBotClient->getWebhookInfo()->url);

        if ($finalUrl !== $expectedUrl) {
            throw new TelegramApiException(sprintf(
                'Telegram webhook sync did not converge. Expected "%s", got "%s".',
                $expectedUrl,
                $finalUrl ?? '',
            ));
        }

        return new TelegramWebhookSyncResult(true, $expectedUrl, $currentUrl, $finalUrl);
    }

    private function normalizeWebhookUrl(string $url): ?string
    {
        return $url === '' ? null : $url;
    }
}

