<?php

namespace App\Infrastructure\Lock;

use Redis;
use RuntimeException;

class RedisConnectionFactory
{
    public function __construct(
        private string $redisDsn,
    ) {
    }

    public function create(): Redis
    {
        $parts = parse_url($this->redisDsn);

        if (!is_array($parts)) {
            throw new RuntimeException(sprintf('Invalid Redis DSN: "%s".', $this->redisDsn));
        }

        $host = (string) ($parts['host'] ?? 'redis');
        $port = (int) ($parts['port'] ?? 6379);
        $password = isset($parts['pass']) ? (string) $parts['pass'] : null;
        $database = $this->extractDatabaseIndex($parts['path'] ?? null);

        $redis = new Redis();

        if (!$redis->connect($host, $port, 2.0)) {
            throw new RuntimeException(sprintf('Unable to connect to Redis at %s:%d.', $host, $port));
        }

        if ($password !== null && $password !== '' && !$redis->auth($password)) {
            throw new RuntimeException('Unable to authenticate to Redis.');
        }

        if ($database !== null && !$redis->select($database)) {
            throw new RuntimeException(sprintf('Unable to select Redis database %d.', $database));
        }

        return $redis;
    }

    /**
     * @param mixed $path
     */
    private function extractDatabaseIndex(mixed $path): ?int
    {
        if (!is_string($path)) {
            return null;
        }

        $database = trim($path, '/');

        if ($database === '' || !ctype_digit($database)) {
            return null;
        }

        return (int) $database;
    }
}
