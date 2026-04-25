<?php

namespace App\Tests\Unit\Infrastructure\Telegram;

use App\Infrastructure\Telegram\TelegramBotClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TelegramBotClientTest extends TestCase
{
    public function testSendMessageUsesJsonPayload(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = compact('method', 'url', 'options');

            return new MockResponse(json_encode(['ok' => true, 'result' => []], JSON_THROW_ON_ERROR));
        });

        $client = new TelegramBotClient($httpClient, 'token');
        $client->sendMessage(123, 'hello');

        self::assertSame('POST', $captured['method']);
        self::assertStringContainsString('/sendMessage', $captured['url']);
        self::assertArrayHasKey('body', $captured['options']);
        self::assertSame(['chat_id' => 123, 'text' => 'hello'], json_decode($captured['options']['body'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSendDocumentUsesMultipartBody(): void
    {
        $captured = null;
        $tempFile = tempnam(sys_get_temp_dir(), 'telegram-doc-');
        file_put_contents($tempFile, 'test');

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = compact('method', 'url', 'options');

            return new MockResponse(json_encode(['ok' => true, 'result' => []], JSON_THROW_ON_ERROR));
        });

        $client = new TelegramBotClient($httpClient, 'token');
        $client->sendDocument(123, $tempFile);

        self::assertSame('POST', $captured['method']);
        self::assertStringContainsString('/sendDocument', $captured['url']);
        self::assertArrayHasKey('headers', $captured['options']);
        self::assertArrayHasKey('body', $captured['options']);
        self::assertArrayNotHasKey('json', $captured['options']);

        @unlink($tempFile);
    }
}
