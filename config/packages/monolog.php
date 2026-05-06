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
                'formatter' => 'monolog.formatter.line',
                'formatter_options' => [
                    // Escape `%...%` because Symfony treats it as a container parameter placeholder.
                    'format' => "[%%datetime%%] %%level_name%%: %%message%%\n",
                    'include_stacktraces' => false,
                    'ignore_empty_context_and_extra' => true,
                ],
            ],

            // Write full context to a file under var/log (volume-backed in production compose).
            'file' => [
                'type' => 'stream',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'level' => 'info',
                'formatter' => 'monolog.formatter.line',
                'formatter_options' => [
                    'format' => "[%%datetime%%] %%channel%%.%%level_name%%: %%message%% %%context%% %%extra%%\n",
                    'include_stacktraces' => true,
                ],
            ],
        ],
    ],
]);
