<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'messenger' => [
            'transports' => [
                'async' => env('MESSENGER_TRANSPORT_DSN'),
            ],
        ],
    ],
]);

