<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\BankAccountRepositoryInterface;
use App\Modules\Finance\Entities\BankAccount;

final class BankAccountService extends BaseFinanceService
{
    public function __construct(
        private readonly BankAccountRepositoryInterface $bankAccountRepository
    ) {
    }

    /**
     * Get a bank account by ID.
     */
    public function getById(int|string $id): ?BankAccount
    {
        return $this->bankAccountRepository->getById($id);
    }

    /**
     * Get all bank accounts.
     *
     * @param array<string, mixed> $filters
     * @return BankAccount[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->bankAccountRepository->getAll($filters);
    }

    /**
     * Get all active bank accounts.
     *
     * @return BankAccount[]
     */
    public function getActive(): array
    {
        return $this->bankAccountRepository->getActive();
    }

    /**
     * Find a bank account by account number.
     */
    public function findByAccountNumber(string $accountNumber): ?BankAccount
    {
        return $this->bankAccountRepository->findByAccountNumber($accountNumber);
    }

    /**
     * Create a bank account.
     */
    public function create(BankAccount $bankAccount): BankAccount
    {
        return $this->bankAccountRepository->insert($bankAccount);
    }

    /**
     * Save a bank account.
     */
    public function save(BankAccount $bankAccount): BankAccount
    {
        return $this->bankAccountRepository->save($bankAccount);
    }

    /**
     * Remove a bank account.
     */
    public function remove(int|string $id): bool
    {
        return $this->bankAccountRepository->remove($id);
    }

    /**
     * Determine whether the bank account is active.
     */
    public function isActive(BankAccount $bankAccount): bool
    {
        return $bankAccount->is_active;
    }

    /**
     * Determine whether the bank account can be used.
     */
    public function canTransact(BankAccount $bankAccount): bool
    {
        return $bankAccount->is_active;
    }
}