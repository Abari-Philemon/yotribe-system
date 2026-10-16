<?php
declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Core\Database;
use App\Modules\Finance\Entities\Account;
use App\Modules\Finance\Repositories\AccountRepository;
use App\Modules\Finance\Repositories\AccountTypeRepository;
use App\Modules\Finance\Requests\AccountRequest;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AccountService
{
    public function __construct(
        private readonly Database $db,
        private readonly AccountRepository $accounts,
        private readonly AccountTypeRepository $accountTypes,
        private readonly AccountRequest $request
    ) {
    }

    /**
     * Create an account within the specified company.
     *
     * Opening balances must be introduced through the journal-posting
     * workflow, never directly through account creation.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(
        int $companyId,
        array $input,
        ?int $actorId = null
    ): array {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'Company ID must be a positive integer.'
            );
        }

        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException(
                'Actor ID must be a positive integer.'
            );
        }

        // Validate and normalize all user-supplied fields at the service boundary.
        $input = $this->request->validate($input);

        if (isset($input['opening_balance'])
            && $input['opening_balance'] !== '0.00') {
            throw new InvalidArgumentException(
                'Opening balances must be entered through the journal-posting workflow.'
            );
        }

        $typeId = $input['account_type_id'];
        $classId = $input['account_class_id'];
        $accountCode = $input['account_code'];
        $systemKey = $input['system_key'];
        $accountName = $input['account_name'];
        $normalBalance = $input['normal_balance'];

        if (!$this->accounts->validateClassification(
            $companyId,
            $typeId,
            $classId
        )) {
            throw new InvalidArgumentException(
                'The account type and class must be active and correctly related within the selected company.'
            );
        }

        $type = $this->accountTypes->findByCompanyAndId(
            $companyId,
            $typeId
        );

        if ($type === null || (int) $type['is_active'] !== 1) {
            throw new InvalidArgumentException(
                'The selected account type is not active for this company.'
            );
        }

        if ($normalBalance !== $type['normal_balance']) {
            throw new InvalidArgumentException(
                'Normal balance must match the selected account type.'
            );
        }

        if ($this->accounts->findByCompanyAndCode(
            $companyId,
            $accountCode
        ) !== null) {
            throw new InvalidArgumentException(
                'An account with this code already exists in the company.'
            );
        }

        if ($this->accounts->findByCompanyAndSystemKey(
            $companyId,
            $systemKey
        ) !== null) {
            throw new InvalidArgumentException(
                'An account with this system key already exists in the company.'
            );
        }

        $parentId = $input['parent_account_id'] ?? null;
        $parent = null;

        if ($parentId !== null) {
            if (!$this->accounts->validateParentAccount(
                $companyId,
                $parentId
            )) {
                throw new InvalidArgumentException(
                    'The parent account must be active, belong to this company, and disallow direct posting.'
                );
            }

            $parent = $this->accounts->findByCompanyAndId(
                $companyId,
                $parentId
            );

            if ($parent === null) {
                throw new RuntimeException(
                    'The validated parent account could not be loaded.'
                );
            }

            if ((int) $parent['account_type_id'] !== $typeId
                || (int) $parent['account_class_id'] !== $classId) {
                throw new InvalidArgumentException(
                    'The parent and child accounts must use the same account type and class.'
                );
            }
        }

        $accountLevel = $parent === null
            ? 1
            : ((int) $parent['account_level'] + 1);

        if ($accountLevel > 32767) {
            throw new InvalidArgumentException(
                'The resulting account hierarchy exceeds the supported account level.'
            );
        }

        $parentPath = $parent === null
            ? ''
            : (string) (
                $parent['account_path'] ?: $parent['account_code']
            );

        $accountPath = $parent === null
            ? $accountCode
            : $parentPath . '/' . $accountCode;

        if (strlen($accountPath) > 500) {
            throw new InvalidArgumentException(
                'The resulting account path exceeds 500 characters.'
            );
        }

        $allowPosting = $input['allow_posting'] ?? true;
        $isControlAccount = $input['is_control_account'] ?? false;
        $isCashAccount = $input['is_cash_account'] ?? false;
        $isBankAccount = $input['is_bank_account'] ?? false;
        $isActive = $input['is_active'] ?? true;

        if ($isCashAccount && $isBankAccount) {
            throw new InvalidArgumentException(
                'An account cannot be both a cash account and a bank account.'
            );
        }

        if (!$allowPosting && ($isCashAccount || $isBankAccount)) {
            throw new InvalidArgumentException(
                'Cash and bank accounts must allow posting.'
            );
        }

        $account = new Account();
        $account->company_id = $companyId;
        $account->account_type_id = $typeId;
        $account->account_class_id = $classId;
        $account->parent_account_id = $parentId;
        $account->account_code = $accountCode;
        $account->system_key = $systemKey;
        $account->account_name = $accountName;
        $account->short_name = $input['short_name'] ?? null;
        $account->description = $input['description'] ?? null;
        $account->normal_balance = $normalBalance;
        $account->account_level = $accountLevel;
        $account->account_path = $accountPath;
        $account->opening_balance = '0.00';
        $account->current_balance = '0.00';
        $account->allow_posting = $allowPosting;
        $account->is_control_account = $isControlAccount;
        $account->is_cash_account = $isCashAccount;
        $account->is_bank_account = $isBankAccount;
        $account->is_system = false;
        $account->is_active = $isActive;
        $account->created_by = $actorId;
        $account->updated_by = $actorId;

        // Explicit columns keep persistence aligned with the accounts schema.
        // Database-managed timestamps are deliberately omitted.
        $data = [
            'uuid' => $account->uuid,
            'company_id' => $account->company_id,
            'account_type_id' => $account->account_type_id,
            'account_class_id' => $account->account_class_id,
            'parent_account_id' => $account->parent_account_id,
            'account_code' => $account->account_code,
            'system_key' => $account->system_key,
            'account_name' => $account->account_name,
            'short_name' => $account->short_name,
            'description' => $account->description,
            'normal_balance' => $account->normal_balance,
            'account_level' => $account->account_level,
            'account_path' => $account->account_path,
            'opening_balance' => '0.00',
            'current_balance' => '0.00',
            'allow_posting' => (int) $account->allow_posting,
            'is_control_account' => (int) $account->is_control_account,
            'is_cash_account' => (int) $account->is_cash_account,
            'is_bank_account' => (int) $account->is_bank_account,
            'is_system' => 0,
            'is_active' => (int) $account->is_active,
            'created_by' => $account->created_by,
            'updated_by' => $account->updated_by,
        ];

        $columns = array_keys($data);
        $placeholders = array_map(
            static fn (string $column): string => ':' . $column,
            $columns
        );

        $sql = sprintf(
            'INSERT INTO accounts (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        // Do not commit or roll back a transaction owned by the caller.
        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->db->execute($sql, $data);
            $accountId = (int) $this->db->lastInsertId();

            if ($accountId < 1) {
                throw new RuntimeException(
                    'The account was not assigned a valid database ID.'
                );
            }

            $created = $this->accounts->findByCompanyAndId(
                $companyId,
                $accountId
            );

            if ($created === null) {
                throw new RuntimeException(
                    'The created account could not be retrieved.'
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $created;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $exception;
        }
    }
    
    /**
     * List accounts belonging to one company.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAccounts(
        int $companyId,
        bool $activeOnly = true
    ): array {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'Company ID must be a positive integer.'
            );
        }

        return $this->accounts->findAllByCompany(
            $companyId,
            $activeOnly
        );
    }

    /**
     * Retrieve an account by ID within one company.
     *
     * Returns null when the account does not belong to that company
     * or does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function getAccount(
        int $companyId,
        int $accountId
    ): ?array {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'Company ID must be a positive integer.'
            );
        }

        if ($accountId < 1) {
            throw new InvalidArgumentException(
                'Account ID must be a positive integer.'
            );
        }

        return $this->accounts->findByCompanyAndId(
            $companyId,
            $accountId
        );
    }


    /**
     * Update permitted account fields within one company.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateAccount(
        int $companyId,
        int $accountId,
        array $input,
        ?int $actorId = null
    ): array {
        if ($companyId < 1 || $accountId < 1) {
            throw new InvalidArgumentException(
                'Company ID and account ID must be positive integers.'
            );
        }

        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException(
                'Actor ID must be a positive integer when supplied.'
            );
        }

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $current = $this->accounts->findByCompanyAndId(
                $companyId,
                $accountId
            );

            if ($current === null) {
                throw new RuntimeException(
                    'Account not found in the specified company.'
                );
            }

            if ((int) $current['is_system'] === 1) {
                throw new RuntimeException(
                    'System accounts cannot be changed through ordinary account updates.'
                );
            }

            $fields = $this->request->validateUpdate($input);

            if (
                isset($fields['account_code'])
                && $fields['account_code'] !== $current['account_code']
            ) {
                $duplicate = $this->accounts->findByCompanyAndCode(
                    $companyId,
                    $fields['account_code']
                );

                if (
                    $duplicate !== null
                    && (int) $duplicate['id'] !== $accountId
                ) {
                    throw new InvalidArgumentException(
                        'This account code is already used by another account in the company.'
                    );
                }
            }

            $allowPosting = array_key_exists('allow_posting', $fields)
                ? (bool) $fields['allow_posting']
                : (bool) $current['allow_posting'];

            $isCash = array_key_exists('is_cash_account', $fields)
                ? (bool) $fields['is_cash_account']
                : (bool) $current['is_cash_account'];

            $isBank = array_key_exists('is_bank_account', $fields)
                ? (bool) $fields['is_bank_account']
                : (bool) $current['is_bank_account'];

            if ($isCash && $isBank) {
                throw new InvalidArgumentException(
                    'An account cannot be both a cash account and a bank account.'
                );
            }

            if (!$allowPosting && ($isCash || $isBank)) {
                throw new InvalidArgumentException(
                    'Cash and bank accounts must allow posting.'
                );
            }

            if (
                !$allowPosting
                && (bool) $current['allow_posting']
            ) {
                $child = $this->db->fetch(
                    'SELECT id
                     FROM accounts
                     WHERE company_id = :company_id
                       AND parent_account_id = :account_id
                     LIMIT 1',
                    [
                        'company_id' => $companyId,
                        'account_id' => $accountId,
                    ]
                );

                if ($child !== null) {
                    throw new RuntimeException(
                        'An account with child accounts cannot be changed to non-posting.'
                    );
                }

                $references = [
                    [
                        'table' => 'journal_entry_lines',
                        'column' => 'account_id',
                        'companyScoped' => false,
                    ],
                    [
                        'table' => 'ledger_balances',
                        'column' => 'account_id',
                        'companyScoped' => true,
                    ],
                    [
                        'table' => 'cash_book',
                        'column' => 'account_id',
                        'companyScoped' => true,
                    ],
                    [
                        'table' => 'bank_accounts',
                        'column' => 'ledger_account_id',
                        'companyScoped' => true,
                    ],
                    [
                        'table' => 'income_categories',
                        'column' => 'ledger_account_id',
                        'companyScoped' => true,
                    ],
                    [
                        'table' => 'expense_categories',
                        'column' => 'ledger_account_id',
                        'companyScoped' => true,
                    ],
                    [
                        'table' => 'finance_posting_rules',
                        'column' => 'debit_account_id',
                        'companyScoped' => true,
                    ],
                    [
                        'table' => 'finance_posting_rules',
                        'column' => 'credit_account_id',
                        'companyScoped' => true,
                    ],
                ];

                foreach ($references as $reference) {
                    $sql = 'SELECT 1 FROM '
                        . $reference['table']
                        . ' WHERE '
                        . $reference['column']
                        . ' = :account_id';

                    $params = ['account_id' => $accountId];

                    if ($reference['companyScoped']) {
                        $sql .= ' AND company_id = :company_id';
                        $params['company_id'] = $companyId;
                    } elseif ($reference['table'] === 'journal_entry_lines') {
                        $sql .= ' AND journal_entry_id IN (
                            SELECT id
                            FROM journal_entries
                            WHERE company_id = :company_id
                        )';
                        $params['company_id'] = $companyId;
                    }

                    $sql .= ' LIMIT 1';

                    if ($this->db->fetch($sql, $params) !== null) {
                        throw new RuntimeException(
                            'This account has existing financial references and cannot be changed to non-posting.'
                        );
                    }
                }

                $settingsReference = $this->db->fetch(
                    'SELECT company_id
                     FROM finance_settings
                     WHERE company_id = :company_id
                       AND default_cash_account_id = :account_id
                     LIMIT 1',
                    [
                        'company_id' => $companyId,
                        'account_id' => $accountId,
                    ]
                );

                if ($settingsReference !== null) {
                    throw new RuntimeException(
                        'This account is configured as the default cash account and cannot be changed to non-posting.'
                    );
                }
            }

            if ($actorId !== null) {
                $fields['updated_by'] = $actorId;
            }

            $this->accounts->updateByCompanyAndId(
                $companyId,
                $accountId,
                $fields
            );

            $updated = $this->accounts->findByCompanyAndId(
                $companyId,
                $accountId
            );

            if ($updated === null) {
                throw new RuntimeException(
                    'The updated account could not be retrieved.'
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $exception;
        }
    }
}