<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Core\Database;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\FinancialPeriodRepository;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use App\Modules\Finance\Repositories\FinanceSettingsRepository;
use App\Modules\Finance\Repositories\JournalEntryRepository;
use App\Modules\Finance\Requests\JournalEntryRequest;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class JournalEntryService
{
    public function __construct(
        private readonly Database $db,
        private readonly JournalEntryRepository $journalRepository,
        private readonly AccountRepository $accountRepository,
        private readonly FinancialYearRepository $financialYearRepository,
        private readonly FinancialPeriodRepository $financialPeriodRepository,
        private readonly FinanceSettingsRepository $settingsRepository,
        private readonly JournalEntryRequest $request
    ) {
    }

    /**
     * Create a balanced draft journal for one farm within a company.
     *
     * This method never posts the journal or changes account balances.
     *
     * @return array{journal: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    public function createForCompany(
        int $companyId,
        array $input,
        int $actorId
    ): array {
        if ($companyId < 1 || $actorId < 1) {
            throw new InvalidArgumentException(
                'Company ID and actor ID must be positive integers.'
            );
        }

        $data = $this->request->validate($input);

        $company = $this->db->fetch(
            'SELECT id
             FROM companies
             WHERE id = :company_id
               AND status = :status
             LIMIT 1',
            [
                'company_id' => $companyId,
                'status' => 'active',
            ]
        );

        if ($company === null) {
            throw new RuntimeException(
                'The company does not exist or is not active.'
            );
        }

        $farm = $this->db->fetch(
            'SELECT f.id
             FROM farms f
             INNER JOIN company_farms cf ON cf.farm_id = f.id
             WHERE cf.company_id = :company_id
               AND f.id = :farm_id
               AND f.status = :status
             LIMIT 1',
            [
                'company_id' => $companyId,
                'farm_id' => $data['farm_id'],
                'status' => 'active',
            ]
        );

        if ($farm === null) {
            throw new RuntimeException(
                'The farm is inactive or does not belong to this company.'
            );
        }

        $financialYear = $this->financialYearRepository->findByCompanyAndDate(
            $companyId,
            $data['journal_date']
        );

        if (
            $financialYear === null
            || strtoupper((string) ($financialYear['status'] ?? '')) !== 'OPEN'
        ) {
            throw new RuntimeException(
                'No open financial year covers the journal date.'
            );
        }

        $financialPeriod = $this->financialPeriodRepository->findByCompanyAndDate(
            $companyId,
            $data['journal_date']
        );

        if (
            $financialPeriod === null
            || (int) ($financialPeriod['financial_year_id'] ?? 0)
                !== (int) $financialYear['id']
            || strtoupper((string) ($financialPeriod['status'] ?? '')) !== 'OPEN'
        ) {
            throw new RuntimeException(
                'No matching open financial period covers the journal date.'
            );
        }

        $settings = $this->settingsRepository->findByCompanyId($companyId);

        if ($settings === null) {
            throw new RuntimeException(
                'Finance settings must exist for this company before journals can be created.'
            );
        }

        if (
            (int) ($settings['allow_backdated_entries'] ?? 0) !== 1
            && $data['journal_date'] < date('Y-m-d')
        ) {
            throw new RuntimeException(
                'Backdated journal entries are disabled for this company.'
            );
        }

        foreach ($data['lines'] as $index => $line) {
            $account = $this->accountRepository->findByCompanyAndId(
                $companyId,
                (int) $line['account_id']
            );

            if ($account === null) {
                throw new RuntimeException(
                    'Account on journal line ' . ($index + 1)
                    . ' does not belong to this company.'
                );
            }

            if ((int) $account['is_active'] !== 1) {
                throw new RuntimeException(
                    'Account on journal line ' . ($index + 1)
                    . ' is inactive.'
                );
            }

            if ((int) $account['allow_posting'] !== 1) {
                throw new RuntimeException(
                    'Account on journal line ' . ($index + 1)
                    . ' does not allow posting.'
                );
            }
        }

        $documentType = $this->db->fetch(
            'SELECT id
             FROM finance_document_types
             WHERE company_id = :company_id
               AND code = :code
               AND is_active = 1
             LIMIT 1',
            [
                'company_id' => $companyId,
                'code' => 'JOURNAL',
            ]
        );

        if ($documentType === null) {
            throw new RuntimeException(
                'An active JOURNAL document type must exist for this company.'
            );
        }

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $financialYearNumber = (int) substr($data['journal_date'], 0, 4);

            $number = $this->journalRepository->allocateDocumentNumber(
                $companyId,
                (int) $documentType['id'],
                $financialYearNumber
            );

            if (
                (int) $number['document_type_id'] !== (int) $documentType['id']
                || (int) $number['financial_year'] !== $financialYearNumber
            ) {
                throw new RuntimeException(
                    'The allocated journal number does not match the requested document type and year.'
                );
            }

            if (
                $this->journalRepository->journalNumberExists(
                    $companyId,
                    (string) $number['journal_no']
                )
            ) {
                throw new RuntimeException(
                    'The allocated journal number already exists.'
                );
            }

            $journalId = $this->journalRepository->createJournal([
                'uuid' => generateUuid(),
                'company_id' => $companyId,
                'farm_id' => (int) $data['farm_id'],
                'financial_year_id' => (int) $financialYear['id'],
                'financial_period_id' => (int) $financialPeriod['id'],
                'document_sequence_id' => (int) $number['sequence_id'],
                'document_type_id' => (int) $number['document_type_id'],
                'journal_no' => (string) $number['journal_no'],
                'journal_date' => $data['journal_date'],
                'transaction_code' => $data['transaction_code'],
                'source_module' => $data['source_module'],
                'source_table' => $data['source_table'],
                'source_id' => $data['source_id'],
                'reference_no' => $data['reference_no'],
                'narration' => $data['narration'],
                'status' => 'DRAFT',
                'total_debit' => $data['total_debit'],
                'total_credit' => $data['total_credit'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            foreach ($data['lines'] as $index => $line) {
                $this->journalRepository->createLine([
                    'uuid' => generateUuid(),
                    'journal_entry_id' => $journalId,
                    'account_id' => (int) $line['account_id'],
                    'line_no' => $index + 1,
                    'entry_type' => $line['entry_type'],
                    'amount' => $line['amount'],
                    'description' => $line['description'],
                    'posting_rule_line_id' => null,
                ]);
            }

            $journal = $this->journalRepository->findByCompanyAndId(
                $companyId,
                $journalId
            );

            if ($journal === null) {
                throw new RuntimeException(
                    'The journal was created but could not be reloaded.'
                );
            }

            $lines = $this->journalRepository->findLinesByJournalId(
                $companyId,
                $journalId
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return [
                'journal' => $journal,
                'lines' => $lines,
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $e;
        }
    }
    /**
     * Post a draft journal atomically and update its account balances.
     *
     * Draft creation remains separate; this method must be invoked explicitly.
     *
     * @return array{journal: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    public function postForCompany(
        int $companyId,
        int $journalId,
        int $actorId
    ): array {
        if ($companyId < 1 || $journalId < 1 || $actorId < 1) {
            throw new InvalidArgumentException(
                'Company ID, journal ID and actor ID must be positive integers.'
            );
        }

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $journal = $this->journalRepository->findByCompanyAndIdForUpdate(
                $companyId,
                $journalId
            );

            if ($journal === null) {
                throw new RuntimeException(
                    'The journal does not exist in this company.'
                );
            }

            if (strtoupper((string) $journal['status']) !== 'DRAFT') {
                throw new RuntimeException(
                    'Only a DRAFT journal can be posted.'
                );
            }

            $company = $this->db->fetch(
                'SELECT id
                 FROM companies
                 WHERE id = :company_id
                   AND status = :status
                 LIMIT 1',
                [
                    'company_id' => $companyId,
                    'status' => 'active',
                ]
            );

            if ($company === null) {
                throw new RuntimeException(
                    'The company does not exist or is not active.'
                );
            }

            $farm = $this->db->fetch(
                'SELECT f.id
                 FROM farms f
                 INNER JOIN company_farms cf ON cf.farm_id = f.id
                 WHERE cf.company_id = :company_id
                   AND f.id = :farm_id
                   AND f.status = :status
                 LIMIT 1',
                [
                    'company_id' => $companyId,
                    'farm_id' => (int) $journal['farm_id'],
                    'status' => 'active',
                ]
            );

            if ($farm === null) {
                throw new RuntimeException(
                    'The journal farm is inactive or does not belong to this company.'
                );
            }

            $journalDate = (string) $journal['journal_date'];

            $year = $this->db->fetch(
                'SELECT *
                 FROM financial_years
                 WHERE company_id = :company_id
                   AND id = :year_id
                 LIMIT 1
                 FOR UPDATE',
                [
                    'company_id' => $companyId,
                    'year_id' => (int) $journal['financial_year_id'],
                ]
            );

            if (
                $year === null
                || strtoupper((string) $year['status']) !== 'OPEN'
                || $journalDate < (string) $year['start_date']
                || $journalDate > (string) $year['end_date']
            ) {
                throw new RuntimeException(
                    'The journal financial year is not open or does not cover its date.'
                );
            }

            $period = $this->db->fetch(
                'SELECT *
                 FROM financial_periods
                 WHERE company_id = :company_id
                   AND id = :period_id
                   AND financial_year_id = :year_id
                 LIMIT 1
                 FOR UPDATE',
                [
                    'company_id' => $companyId,
                    'period_id' => (int) $journal['financial_period_id'],
                    'year_id' => (int) $year['id'],
                ]
            );

            if (
                $period === null
                || strtoupper((string) $period['status']) !== 'OPEN'
                || $journalDate < (string) $period['start_date']
                || $journalDate > (string) $period['end_date']
            ) {
                throw new RuntimeException(
                    'The journal financial period is not open or does not cover its date.'
                );
            }

            $settings = $this->settingsRepository->findByCompanyId($companyId);

            if ($settings === null) {
                throw new RuntimeException(
                    'Finance settings must exist before a journal can be posted.'
                );
            }

            if (
                (int) ($settings['allow_backdated_entries'] ?? 0) !== 1
                && $journalDate < date('Y-m-d')
            ) {
                throw new RuntimeException(
                    'Backdated journals are not allowed by the current finance settings.'
                );
            }

            $lines = $this->journalRepository->findLinesByJournalId(
                $companyId,
                $journalId
            );

            if (count($lines) < 2) {
                throw new RuntimeException(
                    'A journal must contain at least two lines before posting.'
                );
            }

            $debitCents = 0;
            $creditCents = 0;
            $accountIds = [];

            foreach ($lines as $index => $line) {
                $entryType = strtoupper((string) $line['entry_type']);
                $amountCents = $this->financeAmountToCents($line['amount']);

                if (
                    !in_array($entryType, ['DEBIT', 'CREDIT'], true)
                    || $amountCents <= 0
                ) {
                    throw new RuntimeException(
                        'Journal line ' . ($index + 1)
                        . ' has an invalid entry type or amount.'
                    );
                }

                if ($entryType === 'DEBIT') {
                    $debitCents = $this->financeSafeAdd(
                        $debitCents,
                        $amountCents
                    );
                } else {
                    $creditCents = $this->financeSafeAdd(
                        $creditCents,
                        $amountCents
                    );
                }

                $accountIds[] = (int) $line['account_id'];
            }

            if (
                $debitCents !== $creditCents
                || $debitCents !== $this->financeAmountToCents(
                    $journal['total_debit']
                )
                || $creditCents !== $this->financeAmountToCents(
                    $journal['total_credit']
                )
            ) {
                throw new RuntimeException(
                    'Stored journal lines and header totals do not balance.'
                );
            }

            $accountIds = array_values(array_unique($accountIds));
            sort($accountIds, SORT_NUMERIC);

            $lockedAccounts = [];

            foreach ($accountIds as $accountId) {
                $account = $this->accountRepository->findByCompanyAndIdForUpdate(
                    $companyId,
                    $accountId
                );

                if ($account === null) {
                    throw new RuntimeException(
                        'A journal account does not belong to this company.'
                    );
                }

                if ((int) $account['is_active'] !== 1) {
                    throw new RuntimeException(
                        'Account ' . $accountId . ' is inactive.'
                    );
                }

                if ((int) $account['allow_posting'] !== 1) {
                    throw new RuntimeException(
                        'Account ' . $accountId . ' does not allow posting.'
                    );
                }

                $normalBalance = strtolower(
                    (string) $account['normal_balance']
                );

                if (!in_array($normalBalance, ['debit', 'credit'], true)) {
                    throw new RuntimeException(
                        'Account ' . $accountId . ' has an invalid normal balance.'
                    );
                }

                if (
                    (
                        (int) $account['is_cash_account'] === 1
                        || (int) $account['is_bank_account'] === 1
                    )
                    && $normalBalance !== 'debit'
                ) {
                    throw new RuntimeException(
                        'Cash and bank accounts must have a debit normal balance.'
                    );
                }

                $lockedAccounts[$accountId] = $account;
            }

            $movements = [];

            foreach ($lines as $line) {
                $accountId = (int) $line['account_id'];
                $entryType = strtoupper((string) $line['entry_type']);
                $amountCents = $this->financeAmountToCents($line['amount']);
                $normalBalance = strtolower(
                    (string) $lockedAccounts[$accountId]['normal_balance']
                );

                $increasesNormalBalance =
                    ($entryType === 'DEBIT' && $normalBalance === 'debit')
                    || ($entryType === 'CREDIT' && $normalBalance === 'credit');

                $movement = $increasesNormalBalance
                    ? $amountCents
                    : -$amountCents;

                $movements[$accountId] = $this->financeSafeAdd(
                    $movements[$accountId] ?? 0,
                    $movement
                );
            }

            foreach ($accountIds as $accountId) {
                $account = $lockedAccounts[$accountId];
                $currentCents = $this->financeAmountToCents(
                    $account['current_balance']
                );

                $newCents = $this->financeSafeAdd(
                    $currentCents,
                    $movements[$accountId] ?? 0
                );

                $isCashOrBank =
                    (int) $account['is_cash_account'] === 1
                    || (int) $account['is_bank_account'] === 1;

                if (
                    $isCashOrBank
                    && (int) ($settings['allow_negative_cash'] ?? 0) !== 1
                    && $newCents < 0
                ) {
                    throw new RuntimeException(
                        'Posting would make cash or bank account '
                        . $accountId . ' negative.'
                    );
                }

                $this->accountRepository->updateCurrentBalanceForPosting(
                    $companyId,
                    $accountId,
                    $this->financeCentsToAmount($newCents)
                );
            }

            $this->journalRepository->transitionStatus(
                $companyId,
                $journalId,
                'DRAFT',
                'POSTED',
                $actorId
            );

            $postedJournal = $this->journalRepository->findByCompanyAndId(
                $companyId,
                $journalId
            );

            if (
                $postedJournal === null
                || strtoupper((string) $postedJournal['status']) !== 'POSTED'
            ) {
                throw new RuntimeException(
                    'The posted journal could not be verified.'
                );
            }

            $postedLines = $this->journalRepository->findLinesByJournalId(
                $companyId,
                $journalId
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return [
                'journal' => $postedJournal,
                'lines' => $postedLines,
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $e;
        }
    }

    /**
     * Reverse a posted journal by creating and posting an auditable counter-entry.
     * The reversal date defaults to today.
     */
    public function reverseForCompany(
        int $companyId,
        int $journalId,
        int $actorId,
        ?string $reversalDate = null
    ): array {
        if ($companyId <= 0 || $journalId <= 0 || $actorId <= 0) {
            throw new InvalidArgumentException(
                'Company, journal and actor IDs must be positive integers.'
            );
        }

        $reversalDate ??= date('Y-m-d');

        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $reversalDate)
            || !checkdate(
                (int) substr($reversalDate, 5, 2),
                (int) substr($reversalDate, 8, 2),
                (int) substr($reversalDate, 0, 4)
            )
        ) {
            throw new InvalidArgumentException(
                'The reversal date must be a valid date in YYYY-MM-DD format.'
            );
        }

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $original = $this->journalRepository->findByCompanyAndIdForUpdate(
                $companyId,
                $journalId
            );

            if ($original === null) {
                throw new RuntimeException(
                    'The original journal was not found for this company.'
                );
            }

            if (($original['status'] ?? null) !== 'POSTED') {
                throw new RuntimeException(
                    'Only a POSTED journal can be reversed.'
                );
            }

            if (!empty($original['reversal_journal_id'])) {
                throw new RuntimeException(
                    'This journal already has a linked reversal.'
                );
            }

            $originalLines = $this->journalRepository->findLinesByJournalId(
                $companyId,
                $journalId
            );

            if (count($originalLines) < 2) {
                throw new RuntimeException(
                    'The original journal does not contain enough lines to reverse.'
                );
            }

            $reversalLines = [];

            foreach ($originalLines as $line) {
                $entryType = strtoupper((string) ($line['entry_type'] ?? ''));

                if ($entryType !== 'DEBIT' && $entryType !== 'CREDIT') {
                    throw new RuntimeException(
                        'The original journal contains an invalid entry type.'
                    );
                }

                $reversalLines[] = [
                    'account_id' => (int) $line['account_id'],
                    'entry_type' => $entryType === 'DEBIT' ? 'CREDIT' : 'DEBIT',
                    'amount' => (string) $line['amount'],
                    'description' => $line['description'] ?? null,
                ];
            }

            $originalNumber = (string) $original['journal_no'];

            $createdReversal = $this->createForCompany(
                $companyId,
                [
                    'farm_id' => (int) $original['farm_id'],
                    'journal_date' => $reversalDate,
                    'transaction_code' => 'REVERSAL',
                    'source_module' => 'finance',
                    'source_table' => 'journal_entries',
                    'source_id' => $journalId,
                    'reference_no' => $originalNumber,
                    'narration' => 'Reversal of journal ' . $originalNumber,
                    'lines' => $reversalLines,
                ],
                $actorId
            );

            $reversalId = (int) ($createdReversal['journal']['id'] ?? 0);

            if ($reversalId <= 0) {
                throw new RuntimeException(
                    'The reversal journal could not be created.'
                );
            }

            $postedReversal = $this->postForCompany(
                $companyId,
                $reversalId,
                $actorId
            );

            $this->journalRepository->transitionStatus(
                $companyId,
                $journalId,
                'POSTED',
                'REVERSED',
                $actorId,
                ['reversal_journal_id' => $reversalId]
            );

            $reversedOriginal = $this->journalRepository->findByCompanyAndId(
                $companyId,
                $journalId
            );

            if (
                $reversedOriginal === null
                || ($reversedOriginal['status'] ?? null) !== 'REVERSED'
                || (int) ($reversedOriginal['reversal_journal_id'] ?? 0) !== $reversalId
            ) {
                throw new RuntimeException(
                    'The original journal reversal link could not be verified.'
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return [
                'original' => $reversedOriginal,
                'original_lines' => $originalLines,
                'reversal' => $postedReversal,
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $e;
        }
    }
    /**
     * Convert a database decimal amount to integer cents without floats.
     */
    private function financeAmountToCents(mixed $amount): int
    {
        $value = trim((string) $amount);

        if (!preg_match('/^(-?)(\d{1,16})(?:\.(\d{1,2}))?$/D', $value, $matches)) {
            throw new RuntimeException(
                'An accounting amount is not a valid decimal with at most two fractional digits.'
            );
        }

        $whole = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '', 2, '0');

        if ($whole > intdiv(PHP_INT_MAX - $fraction, 100)) {
            throw new RuntimeException(
                'An accounting amount exceeds the supported integer range.'
            );
        }

        $cents = ($whole * 100) + $fraction;

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    /**
     * Add integer-cent amounts while checking for integer overflow.
     */
    private function financeSafeAdd(int $left, int $right): int
    {
        if (
            ($right > 0 && $left > PHP_INT_MAX - $right)
            || ($right < 0 && $left < PHP_INT_MIN - $right)
        ) {
            throw new RuntimeException(
                'The accumulated accounting balance exceeds the supported integer range.'
            );
        }

        return $left + $right;
    }

    /**
     * Convert integer cents to a fixed two-decimal database amount.
     */
    private function financeCentsToAmount(int $cents): string
    {
        if ($cents === PHP_INT_MIN) {
            throw new RuntimeException(
                'The resulting account balance exceeds the supported integer range.'
            );
        }

        $negative = $cents < 0;
        $absolute = $negative ? -$cents : $cents;
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        if ($whole > 9999999999999999) {
            throw new RuntimeException(
                'The resulting account balance exceeds the database decimal range.'
            );
        }

        return ($negative ? '-' : '')
            . (string) $whole
            . '.'
            . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
