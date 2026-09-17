<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Modules\Finance\Contracts\BankAccountRepositoryInterface;
use App\Modules\Finance\Entities\BankAccount;

final class BankAccountRepository extends BaseFinanceRepository implements BankAccountRepositoryInterface
{
    protected string $table = 'bank_accounts';

    protected string $entityClass = BankAccount::class;

    /**
     * Get a bank account by its ID.
     */
    public function getById(int|string $id): ?BankAccount
    {
        /** @var ?BankAccount */
        return $this->getEntityById($id);
    }

    /**
     * Get all bank accounts.
     *
     * @param array<string,mixed> $filters
     * @return BankAccount[]
     */
    public function getAll(array $filters = []): array
    {
        /** @var BankAccount[] */
        return $this->getEntities($filters);
    }

    /**
     * Find a bank account by account number.
     */
    public function findByAccountNumber(string $accountNumber): ?BankAccount
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE account_number = :account_number
LIMIT 1
SQL;

        $row = $this->db->fetch($sql, [
            'account_number' => $accountNumber,
        ]);

        /** @var ?BankAccount */
        return $this->hydrate($row);
    }

    /**
     * Get active bank accounts.
     *
     * @return BankAccount[]
     */
    public function getActive(): array
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE is_active = 1
ORDER BY bank_name ASC, account_name ASC
SQL;

        $rows = $this->db->fetchAll($sql);

        /** @var BankAccount[] */
        return $this->hydrateCollection($rows);
    }

    /**
     * Insert a bank account.
     */
    public function insert(BankAccount $bankAccount): BankAccount
    {
        /** @var BankAccount */
        return $this->insertEntity($bankAccount);
    }

    /**
     * Save a bank account.
     */
    public function save(BankAccount $bankAccount): BankAccount
    {
        /** @var BankAccount */
        return $this->saveEntity($bankAccount);
    }

    /**
     * Remove a bank account.
     */
    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}