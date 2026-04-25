<?php

namespace App\Tests\Unit\Infrastructure\Lock;

use App\Infrastructure\Lock\RedisConnectionFactory;
use App\Infrastructure\Lock\UserActiveJobLockService;
use PHPUnit\Framework\TestCase;
use Redis;

final class UserActiveJobLockServiceTest extends TestCase
{
    public function testAcquireUsesNxAndExSemantics(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('set')
            ->with(
                'bot:user:123:active_job',
                '123',
                self::callback(static function (array $options): bool {
                    return $options['ex'] === 1800 && $options['nx'] === true;
                }),
            )
            ->willReturn(true);

        $factory = $this->createMock(RedisConnectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($redis);

        $service = new UserActiveJobLockService($factory, 1800);

        self::assertTrue($service->acquire(123));
    }

    public function testAcquireReturnsFalseWhenLockExists(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('set')
            ->willReturn(false);

        $factory = $this->createMock(RedisConnectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($redis);

        $service = new UserActiveJobLockService($factory, 1800);

        self::assertFalse($service->acquire(123));
    }

    public function testReleaseDeletesLockKey(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('del')
            ->with('bot:user:123:active_job');

        $factory = $this->createMock(RedisConnectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($redis);

        $service = new UserActiveJobLockService($factory, 1800);

        $service->release(123);
    }

    public function testIsActiveChecksLockKey(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('exists')
            ->with('bot:user:123:active_job')
            ->willReturn(1);

        $factory = $this->createMock(RedisConnectionFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($redis);

        $service = new UserActiveJobLockService($factory, 1800);

        self::assertTrue($service->isActive(123));
    }
}
