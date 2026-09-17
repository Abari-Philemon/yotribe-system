<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Modules\Finance\Contracts\AccountRepositoryInterface;
use App\Modules\Finance\Entities\Account;

final class AccountRepository extends BaseFinanceRepository implements AccountRepositoryInterface
{
    protected string $table = 'accounts';

    protected string $entityClass = Account::class;

    /**
     * Get an account by its ID.
     */
    public function getById(int|string $id): ?Account
    {
        /** @var ?Account */
        return $this->getEntityById($id);
    }

    /**
     * Get all accounts.
     *
     * @param array<string,mixed> $filters
     * @return Account[]
     */
    public function getAll(array $filters = []): array
    {
        /** @var Account[] */
        return $this->getEntities($filters);
    }

    /**
     * Find an account by its account code.
     */
    public function findByCode(string $accountCode): ?Account
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE account_code = :account_code
LIMIT 1
SQL;

        $row = $this->db->fetch($sql, [
            'account_code' => $accountCode,
        ]);

        return $this->hydrate($row);
    }

    /**
     * Find an account by its system key.
     */
    public function findBySystemKey(string $systemKey): ?Account
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE system_key = :system_key
LIMIT 1
SQL;

        $row = $this->db->fetch($sql, [
            'system_key' => $systemKey,
        ]);

        return $this->hydrate($row);
    }

    /**
     * Get all active accounts.
     *
     * @return Account[]
     */
    public function getActive(): array
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE is_active = 1
ORDER BY account_code
SQL;

        $rows = $this->db->fetchAll($sql);

        /** @var Account[] */
        return $this->hydrateCollection($rows);
    }

    /**
     * Insert a new account.
     */
    public function insert(Account $account): Account
    {
        /** @var Account */
        return $this->insertEntity($account);
    }

    /**
     * Save changes to an account.
     */
    public function save(Account $account): Account
    {
        /** @var Account */
        return $this->saveEntity($account);
    }

    /**
     * Remove an account.
     */
    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}