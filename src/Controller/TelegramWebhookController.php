<?php

namespace App\Controller;

use App\Application\Telegram\HandleTelegramUpdateService;
use JsonException;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Attribute\AsController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsController]
final readonly class TelegramWebhookController
{
    public function __construct(
        private HandleTelegramUpdateService $handleTelegramUpdateService,
    ) {
    }

    public function receive(Request $request): Response
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new Response('', Response::HTTP_OK);
        }

        if (!is_array($payload)) {
            return new Response('', Response::HTTP_OK);
        }

        try {
            $this->handleTelegramUpdateService->handle($payload);
        } catch (Throwable) {
            return new Response('', Response::HTTP_OK);
        }

        return new Response('', Response::HTTP_OK);
    }
}
