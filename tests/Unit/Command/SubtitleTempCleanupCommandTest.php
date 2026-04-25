<?php

namespace App\Tests\Unit\Command;

use App\Command\SubtitleTempCleanupCommand;
use App\Infrastructure\Filesystem\SubtitleTempCleanupResult;
use App\Infrastructure\Filesystem\SubtitleTempCleanupService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SubtitleTempCleanupCommandTest extends TestCase
{
    public function testReportsDeletedCount(): void
    {
        $service = $this->createMock(SubtitleTempCleanupService::class);
        $service->expects(self::once())
            ->method('cleanup')
            ->willReturn(new SubtitleTempCleanupResult(true, 3));

        $command = new SubtitleTempCleanupCommand($service);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Deleted 3 stale subtitle temp item(s).', $tester->getDisplay());
    }
}
