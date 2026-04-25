<?php

namespace App\Command;

use App\Application\Telegram\TelegramWebhookSynchronizer;
use App\Infrastructure\Telegram\TelegramApiException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:telegram:webhook:sync')]
final class TelegramWebhookSyncCommand extends Command
{
    public function __construct(
        private TelegramWebhookSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->synchronizer->synchronize();
        } catch (TelegramApiException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($result->changed) {
            $io->success(sprintf('Webhook registered: %s', $result->finalUrl));

            return Command::SUCCESS;
        }

        $io->success(sprintf('Webhook already synchronized: %s', $result->currentUrl));

        return Command::SUCCESS;
    }
}
