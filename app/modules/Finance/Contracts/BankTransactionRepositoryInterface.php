<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\BankTransaction;

interface BankTransactionRepositoryInterface
{
    /**
     * Get a bank transaction by ID.
     */
    public function getById(int|string $id): ?BankTransaction;

    /**
     * Get all bank transactions.
     *
     * @param array<string, mixed> $filters
     * @return BankTransaction[]
     */
    public function getAll(array $filters = []): array;

    /**
     * Find a bank transaction by reference number.
     */
    public function findByReferenceNumber(string $referenceNumber): ?BankTransaction;

    /**
     * Get all transactions belonging to a bank account.
     *
     * @return BankTransaction[]
     */
    public function findByBankAccount(int $bankAccountId): array;

    /**
     * Insert a new bank transaction.
     */
    public function insert(BankTransaction $transaction): BankTransaction;

    /**
     * Save an existing bank transaction.
     */
    public function save(BankTransaction $transaction): BankTransaction;

    /**
     * Remove a bank transaction.
     */
    public function remove(int|string $id): bool;
}