<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\AccountRepositoryInterface;
use App\Modules\Finance\Entities\Account;

final class AccountService extends BaseFinanceService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository
    ) {
        parent::__construct($accountRepository);
    }

    /**
     * Get an account by ID.
     */
    public function getById(int|string $id): ?Account
    {
        return $this->accountRepository->getById($id);
    }

    /**
     * Get all accounts.
     *
     * @param array<string, mixed> $filters
     * @return Account[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->accountRepository->getAll($filters);
    }

    /**
     * Get active accounts.
     *
     * @return Account[]
     */
    public function getActive(): array
    {
        return $this->accountRepository->getActive();
    }

    /**
     * Find an account by account code.
     */
    public function findByCode(string $accountCode): ?Account
    {
        return $this->accountRepository->findByCode($accountCode);
    }

    /**
     * Find an account by system key.
     */
    public function findBySystemKey(string $systemKey): ?Account
    {
        return $this->accountRepository->findBySystemKey($systemKey);
    }

    /**
     * Create a new account.
     */
    public function create(Account $account): Account
    {
        return $this->accountRepository->insert($account);
    }

    /**
     * Save an account.
     */
    public function save(Account $account): Account
    {
        return $this->accountRepository->save($account);
    }

    /**
     * Remove an account.
     */
    public function remove(int|string $id): bool
    {
        return $this->accountRepository->remove($id);
    }
}