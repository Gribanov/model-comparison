<?php

namespace App\Application\Telegram;

class HandleTelegramUpdateService
{
    public function __construct(
        private TelegramUpdateParser $parser,
    ) {
    }

    public function handle(array $update): void
    {
        $textMessage = $this->parser->parse($update);

        if ($textMessage === null) {
            return;
        }

        // Placeholder for future queue dispatch and bot workflow orchestration.
    }
}
