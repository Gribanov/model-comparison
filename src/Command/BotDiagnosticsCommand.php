<?php

namespace App\Command;

use App\Infrastructure\Diagnostics\BotDiagnosticsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bot:diagnostics',
    description: 'Run a lightweight operational smoke test for the Telegram subtitle bot.',
)]
final class BotDiagnosticsCommand extends Command
{
    public function __construct(
        private BotDiagnosticsService $diagnosticsService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->diagnosticsService->run();

        $io->title('Subtitle bot smoke test');
        $io->table(
            ['Check', 'Status', 'Details'],
            array_map(
                static fn (array $check): array => [$check['label'], $check['status'], $check['details']],
                $result->all(),
            ),
        );

        if ($result->hasFailures()) {
            $io->error('One or more operational checks failed.');

            return Command::FAILURE;
        }

        $io->success('Bot container, Redis, temp directory, yt-dlp, and webhook status look healthy.');

        return Command::SUCCESS;
    }
}
