<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\BankAccount;

interface BankAccountRepositoryInterface
{
    public function getById(int|string $id): ?BankAccount;

    /**
     * @param array<string,mixed> $filters
     * @return BankAccount[]
     */
    public function getAll(array $filters = []): array;

    /**
     * Find a bank account by account number.
     */
    public function findByAccountNumber(string $accountNumber): ?BankAccount;

    /**
     * Get active bank accounts.
     *
     * @return BankAccount[]
     */
    public function getActive(): array;

    /**
     * Insert a bank account.
     */
    public function insert(BankAccount $bankAccount): BankAccount;

    /**
     * Save a bank account.
     */
    public function save(BankAccount $bankAccount): BankAccount;

    /**
     * Remove a bank account.
     */
    public function remove(int|string $id): bool;
}