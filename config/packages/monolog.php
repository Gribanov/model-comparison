<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'monolog' => [
        'handlers' => [
            // Keep console/container logs concise.
            'stderr' => [
                'type' => 'stream',
                'path' => 'php://stderr',
                'level' => 'error',
                'formatter' => 'app.monolog.formatter.stderr',
            ],

            // Write full context to a file under var/log (volume-backed in production compose).
            'file' => [
                'type' => 'stream',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'level' => 'info',
                'formatter' => 'app.monolog.formatter.file',
                'include_stacktraces' => true,
            ],
        ],
    ],
]);
