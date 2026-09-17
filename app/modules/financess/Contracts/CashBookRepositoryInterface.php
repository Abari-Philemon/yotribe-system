<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\CashBook;

interface CashBookRepositoryInterface
{
    public function getById(int|string $id): ?CashBook;

    /**
     * @param array<string,mixed> $filters
     * @return CashBook[]
     */
    public function getAll(array $filters = []): array;

    /**
     * @return CashBook[]
     */
    public function findByAccount(int $accountId): array;

    /**
     * @return CashBook[]
     */
    public function findByJournal(int $journalEntryId): array;

    /**
     * @return CashBook[]
     */
    public function findByDateRange(string $fromDate, string $toDate): array;

    public function insert(CashBook $cashBook): CashBook;

    public function save(CashBook $cashBook): CashBook;

    public function remove(int|string $id): bool;
}