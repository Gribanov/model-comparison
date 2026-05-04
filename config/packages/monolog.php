<?php

use Symfony\Config\MonologConfig;

return static function (MonologConfig $monolog): void {
    // Log application errors/exceptions to stderr so `docker compose logs` shows full context in prod.
    $monolog->handler('main')
        ->type('stream')
        ->path('php://stderr')
        ->level('error');
};

