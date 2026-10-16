<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\BankTransactionRepositoryInterface;
use App\Modules\Finance\Entities\BankTransaction;

final class BankTransactionService extends BaseFinanceService
{
    public function __construct(
        private readonly BankTransactionRepositoryInterface $bankTransactionRepository
    ) {
    }

    /**
     * Get a bank transaction by ID.
     */
    public function getById(int|string $id): ?BankTransaction
    {
        return $this->bankTransactionRepository->getById($id);
    }

    /**
     * Get all bank transactions.
     *
     * @param array<string, mixed> $filters
     * @return BankTransaction[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->bankTransactionRepository->getAll($filters);
    }

    /**
     * Find a transaction by reference number.
     */
    public function findByReferenceNumber(string $referenceNumber): ?BankTransaction
    {
        return $this->bankTransactionRepository->findByReferenceNumber($referenceNumber);
    }

    /**
     * Get all transactions for a bank account.
     *
     * @return BankTransaction[]
     */
    public function findByBankAccount(int $bankAccountId): array
    {
        return $this->bankTransactionRepository->findByBankAccount($bankAccountId);
    }

    /**
     * Create a bank transaction.
     */
    public function create(BankTransaction $transaction): BankTransaction
    {
        return $this->bankTransactionRepository->insert($transaction);
    }

    /**
     * Save a bank transaction.
     */
    public function save(BankTransaction $transaction): BankTransaction
    {
        return $this->bankTransactionRepository->save($transaction);
    }

    /**
     * Remove a bank transaction.
     */
    public function remove(int|string $id): bool
    {
        return $this->bankTransactionRepository->remove($id);
    }

    /**
     * Determine whether the transaction has been reconciled.
     */
    public function isReconciled(BankTransaction $transaction): bool
    {
        return $transaction->is_reconciled;
    }

    /**
     * Determine whether the transaction is linked to a journal.
     */
    public function isJournalPosted(BankTransaction $transaction): bool
    {
        return $transaction->journal_entry_id !== null;
    }

    /**
     * Determine whether the transaction can be reconciled.
     */
    public function canReconcile(BankTransaction $transaction): bool
    {
        return !$transaction->is_reconciled;
    }

    /**
     * Determine whether the transaction can be edited.
     */
    public function canEdit(BankTransaction $transaction): bool
    {
        return !$transaction->is_reconciled;
    }

    /**
     * Determine whether the transaction can be deleted.
     */
    public function canDelete(BankTransaction $transaction): bool
    {
        return !$transaction->is_reconciled;
    }
}