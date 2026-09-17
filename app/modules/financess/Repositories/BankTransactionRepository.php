<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\BankTransactionRepositoryInterface;
use App\Modules\Finance\Entities\BankTransaction;

final class BankTransactionRepository extends BaseFinanceRepository implements BankTransactionRepositoryInterface
{
    protected string $table = 'bank_transactions';

    protected string $entityClass = BankTransaction::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    /**
     * Get a bank transaction by ID.
     */
    public function getById(int|string $id): ?BankTransaction
    {
        return $this->getEntityById($id);
    }

    /**
     * Get all bank transactions.
     *
     * @param array<string, mixed> $filters
     * @return BankTransaction[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->getEntities($filters);
    }

    /**
     * Find a transaction by reference number.
     */
    public function findByReferenceNumber(string $referenceNumber): ?BankTransaction
    {
        $row = $this->findOne([
            'reference_number' => $referenceNumber,
        ]);

        return $row === null
            ? null
            : $this->hydrate($row);
    }

    /**
     * Get all transactions for a bank account.
     *
     * @return BankTransaction[]
     */
    public function findByBankAccount(int $bankAccountId): array
    {
        $rows = $this->find([
            'bank_account_id' => $bankAccountId,
        ]);

        return $this->hydrateCollection($rows);
    }

    /**
     * Insert a bank transaction.
     */
    public function insert(BankTransaction $transaction): BankTransaction
    {
        return $this->insertEntity($transaction);
    }

    /**
     * Save a bank transaction.
     */
    public function save(BankTransaction $transaction): BankTransaction
    {
        return $this->saveEntity($transaction);
    }

    /**
     * Remove a bank transaction.
     */
    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}