<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Message\DownloadSubtitlesMessage;

return App::config([
    'framework' => [
        'messenger' => [
            'transports' => [
                'async' => env('MESSENGER_TRANSPORT_DSN'),
            ],
            'routing' => [
                DownloadSubtitlesMessage::class => 'async',
            ],
        ],
    ],
]);
