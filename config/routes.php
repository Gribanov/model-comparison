<?php

use App\Controller\TelegramWebhookController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $webhookSecret = rawurlencode((string) ($_SERVER['TELEGRAM_WEBHOOK_SECRET'] ?? ''));

    $routes->add('telegram_webhook_receive', '/telegram/webhook/'.$webhookSecret)
        ->controller([TelegramWebhookController::class, 'receive'])
        ->methods(['POST'])
        ->stateless();
};

