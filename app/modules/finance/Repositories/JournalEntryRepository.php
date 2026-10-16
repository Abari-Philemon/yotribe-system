<?php
declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

final class JournalEntryRepository
{
    public function __construct(
        private readonly Database $db
    ) {}

    public function findByCompanyAndId(
        int $companyId,
        int $journalId
    ): ?array {
        $this->validateIds($companyId, $journalId);

        return $this->db->fetch(
            'SELECT *
             FROM journal_entries
             WHERE company_id = :company_id
               AND id = :journal_id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'journal_id' => $journalId,
            ]
        ) ?: null;
    }

    /**
     * Lock a journal belonging to the specified company.
     * The caller must already have an active transaction.
     */
    public function findByCompanyAndIdForUpdate(
        int $companyId,
        int $journalId
    ): ?array {
        $this->validateIds($companyId, $journalId);

        if (!$this->db->inTransaction()) {
            throw new RuntimeException(
                'Journal row locking requires an active transaction.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM journal_entries
             WHERE company_id = :company_id
               AND id = :journal_id
             LIMIT 1
             FOR UPDATE',
            [
                'company_id' => $companyId,
                'journal_id' => $journalId,
            ]
        ) ?: null;
    }

    /**
     * Change journal status only if its current status matches expectation.
     * The caller must manage the transaction.
     */
    public function transitionStatus(
        int $companyId,
        int $journalId,
        string $expectedStatus,
        string $newStatus,
        int $actorId,
        array $auditFields = []
    ): void {
        $this->validateIds($companyId, $journalId);

        if (!$this->db->inTransaction()) {
            throw new RuntimeException(
                'Journal status changes require an active transaction.'
            );
        }

        if ($actorId < 1) {
            throw new InvalidArgumentException(
                'Actor ID must be a positive integer.'
            );
        }

        $allowedTransitions = [
            'DRAFT' => ['POSTED', 'CANCELLED'],
            'POSTED' => ['REVERSED'],
        ];

        if (
            !in_array(
                $newStatus,
                $allowedTransitions[$expectedStatus] ?? [],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported journal status transition.'
            );
        }

        $sets = [
            'status = :new_status',
            'updated_by = :updated_by',
            'updated_at = CURRENT_TIMESTAMP',
        ];

        $params = [
            'company_id' => $companyId,
            'journal_id' => $journalId,
            'expected_status' => $expectedStatus,
            'new_status' => $newStatus,
            'updated_by' => $actorId,
        ];

        if ($newStatus === 'POSTED') {
            $sets[] = 'posted_by = :posted_by';
            $sets[] = 'posted_at = CURRENT_TIMESTAMP';
            $params['posted_by'] = $actorId;
        }

        if ($newStatus === 'REVERSED') {
            $sets[] = 'reversed_by = :reversed_by';
            $sets[] = 'reversed_at = CURRENT_TIMESTAMP';
            $params['reversed_by'] = $actorId;

            if (!array_key_exists('reversal_journal_id', $auditFields)) {
                throw new InvalidArgumentException(
                    'A reversal journal ID is required when reversing a journal.'
                );
            }

            $reversalJournalId = filter_var(
                $auditFields['reversal_journal_id'],
                FILTER_VALIDATE_INT
            );

            if ($reversalJournalId === false || $reversalJournalId < 1) {
                throw new InvalidArgumentException(
                    'Reversal journal ID must be a positive integer.'
                );
            }

            $sets[] = 'reversal_journal_id = :reversal_journal_id';
            $params['reversal_journal_id'] = $reversalJournalId;
        }

        $statement = $this->db->query(
            'UPDATE journal_entries
             SET ' . implode(', ', $sets) . '
             WHERE company_id = :company_id
               AND id = :journal_id
               AND status = :expected_status',
            $params
        );

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'Journal status transition failed; the journal may have changed or no longer exists.'
            );
        }
    }
    public function findByCompanyAndJournalNo(
        int $companyId,
        string $journalNo
    ): ?array {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Company ID must be positive.');
        }

        $journalNo = trim($journalNo);

        if ($journalNo === '') {
            throw new InvalidArgumentException('Journal number is required.');
        }

        return $this->db->fetch(
            'SELECT *
             FROM journal_entries
             WHERE company_id = :company_id
               AND journal_no = :journal_no
             LIMIT 1',
            [
                'company_id' => $companyId,
                'journal_no' => $journalNo,
            ]
        ) ?: null;
    }

    public function findAllByCompany(
        int $companyId,
        array $filters = [],
        int $limit = 100,
        int $offset = 0
    ): array {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Company ID must be positive.');
        }

        if ($limit < 1 || $limit > 500 || $offset < 0) {
            throw new InvalidArgumentException(
                'Limit must be 1ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ500 and offset cannot be negative.'
            );
        }

        $allowedFilters = [
            'farm_id',
            'financial_year_id',
            'financial_period_id',
            'status',
            'date_from',
            'date_to',
        ];

        foreach ($filters as $key => $value) {
            if (!in_array($key, $allowedFilters, true)) {
                throw new InvalidArgumentException(
                    'Unsupported journal filter: ' . $key
                );
            }
        }

        $where = ['company_id = :company_id'];
        $params = ['company_id' => $companyId];

        foreach (
            ['farm_id', 'financial_year_id', 'financial_period_id']
            as $field
        ) {
            if (array_key_exists($field, $filters)) {
                $value = filter_var($filters[$field], FILTER_VALIDATE_INT);

                if ($value === false || $value < 1) {
                    throw new InvalidArgumentException(
                        ucfirst(str_replace('_', ' ', $field))
                        . ' must be a positive integer.'
                    );
                }

                $where[] = $field . ' = :' . $field;
                $params[$field] = $value;
            }
        }

        if (array_key_exists('status', $filters)) {
            $status = strtoupper(trim((string) $filters['status']));
            $allowedStatuses = [
                'DRAFT',
                'POSTED',
                'REVERSED',
                'CANCELLED',
            ];

            if (!in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException('Invalid journal status.');
            }

            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        foreach (['date_from', 'date_to'] as $field) {
            if (array_key_exists($field, $filters)) {
                $date = (string) $filters[$field];
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                $errors = \DateTimeImmutable::getLastErrors();

                if (
                    $parsed === false
                    || ($errors !== false
                        && ($errors['warning_count'] > 0
                            || $errors['error_count'] > 0))
                    || $parsed->format('Y-m-d') !== $date
                ) {
                    throw new InvalidArgumentException(
                        ucfirst(str_replace('_', ' ', $field))
                        . ' must be a valid YYYY-MM-DD date.'
                    );
                }

                $parameter = $field;
                $operator = $field === 'date_from' ? '>=' : '<=';

                $where[] = 'journal_date ' . $operator . ' :' . $parameter;
                $params[$parameter] = $date;
            }
        }

        if (
            isset($filters['date_from'], $filters['date_to'])
            && $filters['date_from'] > $filters['date_to']
        ) {
            throw new InvalidArgumentException(
                'Start date cannot be after end date.'
            );
        }

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $sql = 'SELECT *
                FROM journal_entries
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY journal_date DESC, id DESC
                LIMIT :limit OFFSET :offset';

        return $this->db->fetchAll($sql, $params);
    }

    public function findLinesByJournalId(
        int $companyId,
        int $journalId
    ): array {
        $this->validateIds($companyId, $journalId);

        $journal = $this->findByCompanyAndId($companyId, $journalId);

        if ($journal === null) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT
                jel.id,
                jel.uuid,
                jel.journal_entry_id,
                jel.account_id,
                jel.line_no,
                jel.entry_type,
                jel.amount,
                jel.description,
                jel.posting_rule_line_id,
                jel.created_at,
                jel.updated_at,
                a.account_code,
                a.account_name
             FROM journal_entry_lines jel
             INNER JOIN accounts a
                ON a.id = jel.account_id
             WHERE jel.journal_entry_id = :journal_id
             ORDER BY jel.line_no ASC',
            ['journal_id' => $journalId]
        );
    }

    public function journalNumberExists(
        int $companyId,
        string $journalNo,
        ?int $excludeJournalId = null
    ): bool {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Company ID must be positive.');
        }

        $journalNo = trim($journalNo);

        if ($journalNo === '') {
            throw new InvalidArgumentException('Journal number is required.');
        }

        $sql = 'SELECT id
                FROM journal_entries
                WHERE company_id = :company_id
                  AND journal_no = :journal_no';

        $params = [
            'company_id' => $companyId,
            'journal_no' => $journalNo,
        ];

        if ($excludeJournalId !== null) {
            if ($excludeJournalId < 1) {
                throw new InvalidArgumentException(
                    'Excluded journal ID must be positive.'
                );
            }

            $sql .= ' AND id <> :exclude_journal_id';
            $params['exclude_journal_id'] = $excludeJournalId;
        }

        return $this->db->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    /**
     * Create a journal entry header.
     *
     * The caller must manage the transaction.
     */
    public function createJournal(array $data): int
    {
        $required = [
            'uuid',
            'company_id',
            'farm_id',
            'financial_year_id',
            'financial_period_id',
            'document_sequence_id',
            'document_type_id',
            'journal_no',
            'journal_date',
            'transaction_code',
            'source_module',
            'source_table',
            'source_id',
            'reference_no',
            'narration',
            'status',
            'total_debit',
            'total_credit',
            'created_by',
            'updated_by',
        ];

        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    'Missing journal field: ' . $field
                );
            }
        }

        $this->db->execute(
            'INSERT INTO journal_entries (
                uuid,
                company_id,
                farm_id,
                financial_year_id,
                financial_period_id,
                document_sequence_id,
                document_type_id,
                journal_no,
                journal_date,
                transaction_code,
                source_module,
                source_table,
                source_id,
                reference_no,
                narration,
                status,
                total_debit,
                total_credit,
                created_by,
                updated_by
            ) VALUES (
                :uuid,
                :company_id,
                :farm_id,
                :financial_year_id,
                :financial_period_id,
                :document_sequence_id,
                :document_type_id,
                :journal_no,
                :journal_date,
                :transaction_code,
                :source_module,
                :source_table,
                :source_id,
                :reference_no,
                :narration,
                :status,
                :total_debit,
                :total_credit,
                :created_by,
                :updated_by
            )',
            $data
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Create one journal entry line.
     *
     * The caller must manage the transaction.
     */
    public function createLine(array $data): int
    {
        $required = [
            'uuid',
            'journal_entry_id',
            'account_id',
            'line_no',
            'entry_type',
            'amount',
            'description',
            'posting_rule_line_id',
        ];

        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(
                    'Missing journal line field: ' . $field
                );
            }
        }

        $this->db->execute(
            'INSERT INTO journal_entry_lines (
                uuid,
                journal_entry_id,
                account_id,
                line_no,
                entry_type,
                amount,
                description,
                posting_rule_line_id
            ) VALUES (
                :uuid,
                :journal_entry_id,
                :account_id,
                :line_no,
                :entry_type,
                :amount,
                :description,
                :posting_rule_line_id
            )',
            $data
        );

        return (int) $this->db->lastInsertId();
    }
    /**
     * Allocate the next number from a financial document sequence.
     *
     * The caller must already have an active transaction.
     *
     * @return array<string, mixed>
     */
    public function allocateDocumentNumber(
        int $companyId,
        int $documentTypeId,
        int $financialYear
    ): array {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'Company ID must be positive.'
            );
        }

        if ($documentTypeId < 1) {
            throw new InvalidArgumentException(
                'Document type ID must be positive.'
            );
        }

        if ($financialYear < 1) {
            throw new InvalidArgumentException(
                'Financial year must be positive.'
            );
        }

        if (!$this->db->inTransaction()) {
            throw new InvalidArgumentException(
                'Document number allocation requires an active transaction.'
            );
        }

        $sequence = $this->db->fetch(
            'SELECT
                id,
                company_id,
                document_type_id,
                financial_year,
                prefix,
                last_number,
                number_length,
                `separator`,
                reset_annually,
                is_active
             FROM finance_document_sequences
             WHERE company_id = :company_id
               AND document_type_id = :document_type_id
               AND financial_year = :financial_year
               AND is_active = 1
             LIMIT 1
             FOR UPDATE',
            [
                'company_id' => $companyId,
                'document_type_id' => $documentTypeId,
                'financial_year' => $financialYear,
            ]
        );

        if ($sequence === null) {
            throw new InvalidArgumentException(
                'No active document sequence exists for this company, document type and financial year.'
            );
        }

        $nextNumber = (int) $sequence['last_number'] + 1;
        $numberLength = (int) $sequence['number_length'];

        if ($nextNumber < 1) {
            throw new InvalidArgumentException(
                'The document sequence number is invalid.'
            );
        }

        if ($numberLength < 1 || $numberLength > 18) {
            throw new InvalidArgumentException(
                'The document sequence number length is invalid.'
            );
        }

        $this->db->execute(
            'UPDATE finance_document_sequences
             SET last_number = :last_number,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND company_id = :company_id
               AND document_type_id = :document_type_id
               AND financial_year = :financial_year',
            [
                'last_number' => $nextNumber,
                'id' => (int) $sequence['id'],
                'company_id' => $companyId,
                'document_type_id' => $documentTypeId,
                'financial_year' => $financialYear,
            ]
        );

        $separator = (string) ($sequence['separator'] ?? '-');
        $prefix = trim((string) $sequence['prefix']);

        if ($prefix === '') {
            throw new InvalidArgumentException(
                'The document sequence prefix cannot be empty.'
            );
        }

        $formattedNumber = str_pad(
            (string) $nextNumber,
            $numberLength,
            '0',
            STR_PAD_LEFT
        );

        $journalNumber = $prefix . $separator . $formattedNumber;

        return [
            'sequence_id' => (int) $sequence['id'],
            'document_type_id' => (int) $sequence['document_type_id'],
            'financial_year' => (int) $sequence['financial_year'],
            'prefix' => $prefix,
            'separator' => $separator,
            'number' => $nextNumber,
            'number_length' => $numberLength,
            'journal_no' => $journalNumber,
        ];
    }
    private function validateIds(int $companyId, int $journalId): void
    {
        if ($companyId < 1 || $journalId < 1) {
            throw new InvalidArgumentException(
                'Company ID and journal ID must be positive.'
            );
        }
    }
}