<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'secret' => env('APP_SECRET'),
        'http_method_override' => false,
    ],
]);

