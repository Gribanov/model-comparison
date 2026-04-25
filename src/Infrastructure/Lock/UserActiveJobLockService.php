<?php

namespace App\Infrastructure\Lock;

use Redis;

class UserActiveJobLockService
{
    private ?Redis $redis = null;

    public function __construct(
        private RedisConnectionFactory $redisConnectionFactory,
        private int $userJobLockTtl,
    ) {
    }

    public function acquire(int $userId): bool
    {
        return (bool) $this->redis()->set(
            $this->key($userId),
            (string) $userId,
            ['nx' => true, 'ex' => $this->ttl()],
        );
    }

    public function release(int $userId): void
    {
        $this->redis()->del($this->key($userId));
    }

    public function isActive(int $userId): bool
    {
        return $this->redis()->exists($this->key($userId)) > 0;
    }

    private function key(int $userId): string
    {
        return sprintf('bot:user:%d:active_job', $userId);
    }

    private function ttl(): int
    {
        return max(1, $this->userJobLockTtl);
    }

    private function redis(): Redis
    {
        return $this->redis ??= $this->redisConnectionFactory->create();
    }
}
