<?php

declare(strict_types=1);

namespace App\Modules\Finance\Engine;

use App\Core\TransactionManager;
use App\Modules\Finance\Entities\JournalEntry;
use App\Modules\Finance\Services\CashBookService;
use App\Modules\Finance\Services\DocumentSequenceService;
use App\Modules\Finance\Services\FinanceAuditLogService;
use App\Modules\Finance\Services\JournalService;
use App\Modules\Finance\Services\BankTransactionService;

final class JournalPostingService
{
    public function __construct(
        private readonly TransactionManager $transactionManager,
        private readonly JournalService $journalService,
        private readonly JournalValidator $journalValidator,
        private readonly DocumentNumberGenerator $documentNumberGenerator,
        private readonly DocumentSequenceService $documentSequenceService,
        private readonly LedgerBalanceUpdater $ledgerBalanceUpdater,
        private readonly CashBookService $cashBookService,
        private readonly BankTransactionService $bankTransactionService,
        private readonly FinanceAuditLogService $auditLogService,
    ) {
    }

    public function post(int|string $journalId): JournalEntry
    {
        return $this->transactionManager->transaction(function () use ($journalId) {

            $journal = $this->journalService->getById($journalId);

            if ($journal === null) {
                throw new \RuntimeException('Journal not found.');
            }

            $this->journalValidator->validate($journal);

            $this->assignDocumentNumber($journal);

            $this->ledgerBalanceUpdater->apply($journal->id);

            $this->cashBookService->postJournal($journal);

            $this->bankTransactionService->postJournal($journal);

            $journal->status = $journal->status->post();

            $journal->posted_at = date('Y-m-d H:i:s');

            $journal = $this->journalService->save($journal);

            $this->auditLogService->recordPosting($journal);

            return $journal;
        });
    }

    private function assignDocumentNumber(JournalEntry $journal): void
    {
        if (!empty($journal->document_number)) {
            return;
        }

        $sequence = $this->documentSequenceService
            ->findByDocumentType($journal->document_type_id);

        if ($sequence === null) {
            throw new \RuntimeException(
                'Document sequence not configured.'
            );
        }

        $sequence = $this->documentNumberGenerator
            ->resetIfRequired(
                $sequence,
                $journal->financial_year_id
            );

        $journal->document_number =
            $this->documentNumberGenerator->reserve($sequence);
    }
}