<?php

namespace App\Command;

use App\Infrastructure\Filesystem\SubtitleTempCleanupService;
use App\Infrastructure\Filesystem\SubtitleTempCleanupResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:subtitle-temp:cleanup',
    description: 'Remove stale subtitle temp files and job directories.',
)]
final class SubtitleTempCleanupCommand extends Command
{
    public function __construct(
        private SubtitleTempCleanupService $cleanupService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->cleanupService->cleanup();

        if (!$result->directoryExists) {
            $io->warning('Subtitle temp directory does not exist; nothing to clean.');

            return Command::SUCCESS;
        }

        if ($result->deletedCount === 0) {
            $io->success('No stale subtitle temp items found.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('Deleted %d stale subtitle temp item(s).', $result->deletedCount));

        return Command::SUCCESS;
    }
}
