<?php

declare(strict_types=1);

namespace App\Modules\Finance\Engine;

use App\Modules\Finance\Entities\JournalLine;
use App\Modules\Finance\Entities\LedgerBalance;
use App\Modules\Finance\Services\JournalLineService;
use App\Modules\Finance\Services\LedgerBalanceService;

final class LedgerBalanceUpdater
{
    public function __construct(
        private readonly JournalLineService $journalLineService,
        private readonly LedgerBalanceService $ledgerBalanceService
    ) {
    }

    public function apply(int|string $journalId): void
    {
        $lines = $this->journalLineService->findByJournal($journalId);

        foreach ($lines as $line) {
            $this->updateBalance($line, false);
        }
    }

    public function reverse(int|string $journalId): void
    {
        $lines = $this->journalLineService->findByJournal($journalId);

        foreach ($lines as $line) {
            $this->updateBalance($line, true);
        }
    }

    private function updateBalance(
        JournalLine $line,
        bool $reverse
    ): void {
        $balance = $this->findOrCreateBalance($line);

        $multiplier = $reverse ? -1 : 1;

        $balance->period_debit +=
            $line->debit_amount * $multiplier;

        $balance->period_credit +=
            $line->credit_amount * $multiplier;

        $balance->closing_debit =
            $balance->opening_debit +
            $balance->period_debit;

        $balance->closing_credit =
            $balance->opening_credit +
            $balance->period_credit;

        $this->ledgerBalanceService->save($balance);
    }

    private function findOrCreateBalance(
        JournalLine $line
    ): LedgerBalance {

        $balance = $this->ledgerBalanceService
            ->findByFinancialPeriod(
                $line->financial_period_id,
                $line->account_id
            );

        if ($balance !== null) {
            return $balance;
        }

        $balance = new LedgerBalance();

        $balance->company_id = $line->company_id;
        $balance->financial_year_id = $line->financial_year_id;
        $balance->financial_period_id = $line->financial_period_id;
        $balance->account_id = $line->account_id;

        $balance->opening_debit = 0;
        $balance->opening_credit = 0;

        $balance->period_debit = 0;
        $balance->period_credit = 0;

        $balance->closing_debit = 0;
        $balance->closing_credit = 0;

        return $this->ledgerBalanceService->create($balance);
    }
}