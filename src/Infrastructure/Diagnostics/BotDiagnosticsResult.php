<?php

namespace App\Infrastructure\Diagnostics;

final class BotDiagnosticsResult
{
    /**
     * @var list<array{label:string,status:string,details:string}>
     */
    private array $checks = [];

    public function addCheck(string $label, string $status, string $details = ''): void
    {
        $this->checks[] = [
            'label' => $label,
            'status' => $status,
            'details' => $details,
        ];
    }

    /**
     * @return list<array{label:string,status:string,details:string}>
     */
    public function all(): array
    {
        return $this->checks;
    }

    public function hasFailures(): bool
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === 'FAIL') {
                return true;
            }
        }

        return false;
    }
}
