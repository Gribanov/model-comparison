<?php

namespace App\Tests\Unit\Controller;

use App\Application\Telegram\HandleTelegramUpdateService;
use App\Controller\TelegramWebhookController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class TelegramWebhookControllerTest extends TestCase
{
    public function testReturnsOkAndDelegatesPayload(): void
    {
        $handler = $this->createMock(HandleTelegramUpdateService::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with([
                'message' => [
                    'chat' => ['id' => 123],
                    'from' => ['id' => 456],
                    'text' => 'hello',
                ],
            ]);

        $controller = new TelegramWebhookController($handler);
        $response = $controller->receive(Request::create(
            '/telegram/webhook/secret',
            'POST',
            server: [],
            content: json_encode([
                'message' => [
                    'chat' => ['id' => 123],
                    'from' => ['id' => 456],
                    'text' => 'hello',
                ],
            ], JSON_THROW_ON_ERROR),
        ));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testIgnoresInvalidJsonGracefully(): void
    {
        $handler = $this->createMock(HandleTelegramUpdateService::class);
        $handler->expects(self::never())->method('handle');

        $controller = new TelegramWebhookController($handler);
        $response = $controller->receive(Request::create('/telegram/webhook/secret', 'POST', content: '{invalid'));

        self::assertSame(200, $response->getStatusCode());
    }
}

