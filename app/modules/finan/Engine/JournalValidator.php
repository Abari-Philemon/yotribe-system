<?php

declare(strict_types=1);

namespace App\Modules\Finance\Engine;

use App\Modules\Finance\Entities\JournalEntry;
use App\Modules\Finance\Services\FinanceSettingsService;
use App\Modules\Finance\Services\FinancialPeriodService;
use App\Modules\Finance\Services\JournalLineService;

final class JournalValidator
{
    public function __construct(
        private readonly JournalLineService $journalLineService,
        private readonly FinancialPeriodService $financialPeriodService,
        private readonly FinanceSettingsService $financeSettingsService,
    ) {
    }

    public function validate(JournalEntry $journal): void
    {
        $this->validateStatus($journal);
        $this->validateJournalLines($journal);
        $this->validateBalancedJournal($journal);
        $this->validateFinancialPeriod($journal);
    }

    private function validateStatus(JournalEntry $journal): void
    {
        if (!$journal->status->canPost()) {
            throw new \RuntimeException(
                'Journal cannot be posted in its current status.'
            );
        }
    }

    private function validateJournalLines(JournalEntry $journal): void
    {
        $lines = $this->journalLineService->findByJournal($journal->id);

        if (count($lines) < 2) {
            throw new \RuntimeException(
                'A journal must contain at least two journal lines.'
            );
        }
    }

    private function validateBalancedJournal(JournalEntry $journal): void
    {
        $lines = $this->journalLineService->findByJournal($journal->id);

        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $debit += $line->debit_amount;
            $credit += $line->credit_amount;
        }

        if (round($debit, 2) !== round($credit, 2)) {
            throw new \RuntimeException(
                'Journal is not balanced.'
            );
        }
    }

    private function validateFinancialPeriod(JournalEntry $journal): void
    {
        $period = $this->financialPeriodService
            ->getById($journal->financial_period_id);

        if ($period === null) {
            throw new \RuntimeException(
                'Financial period not found.'
            );
        }

        if (!$period->is_open) {
            throw new \RuntimeException(
                'Financial period is closed.'
            );
        }
    }
}