<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\CashBookRepositoryInterface;
use App\Modules\Finance\Entities\CashBook;

final class CashBookService extends BaseFinanceService
{
    public function __construct(
        private readonly CashBookRepositoryInterface $cashBookRepository
    ) {
    }

    public function getById(int|string $id): ?CashBook
    {
        return $this->cashBookRepository->getById($id);
    }

    /**
     * @param array<string,mixed> $filters
     * @return CashBook[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->cashBookRepository->getAll($filters);
    }

    /**
     * @return CashBook[]
     */
    public function findByAccount(int $accountId): array
    {
        return $this->cashBookRepository->findByAccount($accountId);
    }

    /**
     * @return CashBook[]
     */
    public function findByJournal(int $journalEntryId): array
    {
        return $this->cashBookRepository->findByJournal($journalEntryId);
    }

    /**
     * @return CashBook[]
     */
    public function findByDateRange(string $fromDate, string $toDate): array
    {
        return $this->cashBookRepository->findByDateRange($fromDate, $toDate);
    }

    public function create(CashBook $cashBook): CashBook
    {
        return $this->cashBookRepository->insert($cashBook);
    }

    public function save(CashBook $cashBook): CashBook
    {
        return $this->cashBookRepository->save($cashBook);
    }

    public function remove(int|string $id): bool
    {
        return $this->cashBookRepository->remove($id);
    }

    public function isDebit(CashBook $cashBook): bool
    {
        return $cashBook->entry_type->isDebit();
    }

    public function isCredit(CashBook $cashBook): bool
    {
        return $cashBook->entry_type->isCredit();
    }
}