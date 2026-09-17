<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\CashBookRepositoryInterface;
use App\Modules\Finance\Entities\CashBook;

final class CashBookRepository extends BaseFinanceRepository implements CashBookRepositoryInterface
{
    protected string $table = 'cash_book';

    protected string $entityClass = CashBook::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?CashBook
    {
        return $this->getEntityById($id);
    }

    public function getAll(array $filters = []): array
    {
        return $this->getEntities($filters);
    }

    public function findByAccount(int $accountId): array
    {
        return $this->hydrateCollection(
            $this->find([
                'account_id' => $accountId,
            ])
        );
    }

    public function findByJournal(int $journalEntryId): array
    {
        return $this->hydrateCollection(
            $this->find([
                'journal_entry_id' => $journalEntryId,
            ])
        );
    }

    public function findByDateRange(string $fromDate, string $toDate): array
    {
        $sql = "
            SELECT *
            FROM {$this->table}
            WHERE transaction_date BETWEEN :from_date AND :to_date
            ORDER BY transaction_date ASC
        ";

        return $this->hydrateCollection(
            $this->db->fetchAll($sql, [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
            ])
        );
    }

    public function insert(CashBook $cashBook): CashBook
    {
        /** @var CashBook */
        return $this->insertEntity($cashBook);
    }

    public function save(CashBook $cashBook): CashBook
    {
        /** @var CashBook */
        return $this->saveEntity($cashBook);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}