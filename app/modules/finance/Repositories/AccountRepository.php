<?php
declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Core\Repository;
use InvalidArgumentException;
use RuntimeException;

final class AccountRepository extends Repository
{
    protected string $table = 'accounts';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Find an account by ID within a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndId(
        int $companyId,
        int $accountId
    ): ?array {
        $this->validateIds($companyId, $accountId);

        return $this->db->fetch(
            'SELECT *
             FROM accounts
             WHERE company_id = :company_id
               AND id = :account_id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'account_id' => $accountId,
            ]
        );
    }

    /**
     * Find an account by its code within a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndCode(
        int $companyId,
        string $accountCode
    ): ?array {
        $this->validateCompanyId($companyId);
        $accountCode = trim($accountCode);

        if ($accountCode === '') {
            throw new InvalidArgumentException(
                'Account code cannot be empty.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM accounts
             WHERE company_id = :company_id
               AND account_code = :account_code
             LIMIT 1',
            [
                'company_id' => $companyId,
                'account_code' => $accountCode,
            ]
        );
    }

    /**
     * Find an account by its system key within a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndSystemKey(
        int $companyId,
        string $systemKey
    ): ?array {
        $this->validateCompanyId($companyId);
        $systemKey = trim($systemKey);

        if ($systemKey === '') {
            throw new InvalidArgumentException(
                'Account system key cannot be empty.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM accounts
             WHERE company_id = :company_id
               AND system_key = :system_key
             LIMIT 1',
            [
                'company_id' => $companyId,
                'system_key' => $systemKey,
            ]
        );
    }

    /**
     * List accounts for a company, optionally filtering active records.
     *
     * @return array<array<string, mixed>>
     */
    public function findAllByCompany(
        int $companyId,
        bool $activeOnly = true
    ): array {
        $this->validateCompanyId($companyId);

        $sql = '
            SELECT *
            FROM accounts
            WHERE company_id = :company_id
        ';

        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY account_code, id';

        return $this->db->fetchAll($sql, [
            'company_id' => $companyId,
        ]);
    }

    /**
     * Verify that an account belongs to the specified company.
     */
    public function belongsToCompany(
        int $companyId,
        int $accountId
    ): bool {
        return $this->findByCompanyAndId(
            $companyId,
            $accountId
        ) !== null;
    }

    /**
     * Validate that account type and class belong to the same company
     * and that the class belongs to the supplied account type.
     */
    public function validateClassification(
        int $companyId,
        int $accountTypeId,
        int $accountClassId
    ): bool {
        $this->validateCompanyId($companyId);

        if ($accountTypeId < 1 || $accountClassId < 1) {
            return false;
        }

        return $this->db->fetch(
            'SELECT c.id
             FROM account_classes c
             INNER JOIN account_types t
                ON t.id = c.account_type_id
               AND t.company_id = c.company_id
             WHERE c.company_id = :company_id
               AND c.id = :class_id
               AND c.account_type_id = :type_id
               AND c.is_active = 1
               AND t.is_active = 1
             LIMIT 1',
            [
                'company_id' => $companyId,
                'class_id' => $accountClassId,
                'type_id' => $accountTypeId,
            ]
        ) !== null;
    }

    /**
     * Validate a proposed parent account.
     */
    public function validateParentAccount(
        int $companyId,
        int $parentAccountId
    ): bool {
        $parent = $this->findByCompanyAndId(
            $companyId,
            $parentAccountId
        );

        return $parent !== null
            && (int) $parent['is_active'] === 1
            && (int) $parent['allow_posting'] === 0;
    }

    /**
     * Update explicitly permitted fields for an account in one company.
     *
     * This method does not permit changes to account identity,
     * classification, balances, or system-account status.
     *
     * @param array<string, mixed> $fields
     */
    /**
     * Lock a company-owned account row for a posting transaction.
     */
    public function findByCompanyAndIdForUpdate(
        int $companyId,
        int $accountId
    ): ?array {
        $this->validateIds($companyId, $accountId);

        if (!$this->db->inTransaction()) {
            throw new RuntimeException(
                'Account row locking requires an active transaction.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM accounts
             WHERE company_id = :company_id
               AND id = :account_id
             LIMIT 1
             FOR UPDATE',
            [
                'company_id' => $companyId,
                'account_id' => $accountId,
            ]
        );
    }

    /**
     * Update only the current balance during a posting transaction.
     */
    public function updateCurrentBalanceForPosting(
        int $companyId,
        int $accountId,
        string $balance
    ): void {
        $this->validateIds($companyId, $accountId);

        if (!$this->db->inTransaction()) {
            throw new RuntimeException(
                'Account balance updates require an active transaction.'
            );
        }

        if (!preg_match('/^-?\d{1,16}(?:\.\d{1,2})?$/', $balance)) {
            throw new InvalidArgumentException(
                'Account balance must be a valid amount with at most two decimal places.'
            );
        }

        $statement = $this->db->query(
            'UPDATE accounts
             SET current_balance = :balance,
                 updated_at = CURRENT_TIMESTAMP
             WHERE company_id = :company_id
               AND id = :account_id',
            [
                'balance' => $balance,
                'company_id' => $companyId,
                'account_id' => $accountId,
            ]
        );

        if ($statement->rowCount() === 1) {
            return;
        }

        $existing = $this->db->fetch(
            'SELECT id
             FROM accounts
             WHERE company_id = :company_id
               AND id = :account_id
               AND current_balance = :expected_balance
             LIMIT 1',
            [
                'company_id' => $companyId,
                'account_id' => $accountId,
                'expected_balance' => $balance,
            ]
        );

        if ($existing === null) {
            throw new RuntimeException(
                'Account balance update failed for account ID '
                . $accountId . '.'
            );
        }
    }

    public function updateByCompanyAndId(
        int $companyId,
        int $accountId,
        array $fields
    ): bool {
        $this->validateIds($companyId, $accountId);

        $allowedFields = [
            'account_code',
            'account_name',
            'short_name',
            'description',
            'allow_posting',
            'is_control_account',
            'is_cash_account',
            'is_bank_account',
            'updated_by',
        ];

        if ($fields === []) {
            throw new InvalidArgumentException(
                'At least one account field must be supplied.'
            );
        }

        foreach (array_keys($fields) as $field) {
            if (!in_array($field, $allowedFields, true)) {
                throw new InvalidArgumentException(
                    'Unsupported account update field: ' . $field
                );
            }
        }

        $sets = [];
        $params = [
            'company_id' => $companyId,
            'account_id' => $accountId,
        ];

        foreach ($fields as $field => $value) {
            $sets[] = $field . ' = :' . $field;
            $params[$field] = $value;
        }

        $sql = 'UPDATE accounts SET '
            . implode(', ', $sets)
            . ', updated_at = CURRENT_TIMESTAMP'
            . ' WHERE company_id = :company_id AND id = :account_id';

        $this->db->execute($sql, $params);

        // A false result can also mean no values changed, so verify existence.
        return $this->findByCompanyAndId($companyId, $accountId) !== null;
    }
    private function validateIds(
        int $companyId,
        int $recordId
    ): void {
        $this->validateCompanyId($companyId);

        if ($recordId < 1) {
            throw new InvalidArgumentException(
                'Record ID must be a positive integer.'
            );
        }
    }

    private function validateCompanyId(int $companyId): void
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'Company ID must be a positive integer.'
            );
        }
    }
}