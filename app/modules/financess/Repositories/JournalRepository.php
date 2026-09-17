<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Modules\Finance\Contracts\JournalRepositoryInterface;
use App\Modules\Finance\Entities\JournalEntry;

final class JournalRepository extends BaseFinanceRepository implements JournalRepositoryInterface
{
    protected string $table = 'journal_entries';

    protected string $entityClass = JournalEntry::class;

    /**
     * Get a journal entry by its ID.
     */
    public function getById(int|string $id): ?JournalEntry
    {
        /** @var ?JournalEntry */
        return $this->getEntityById($id);
    }

    /**
     * Get all journal entries.
     *
     * @param array<string,mixed> $filters
     * @return JournalEntry[]
     */
    public function getAll(array $filters = []): array
    {
        /** @var JournalEntry[] */
        return $this->getEntities($filters);
    }

    /**
     * Find a journal entry by journal number.
     */
    public function findByJournalNumber(string $journalNumber): ?JournalEntry
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE journal_number = :journal_number
LIMIT 1
SQL;

        $row = $this->db->fetch($sql, [
            'journal_number' => $journalNumber,
        ]);

        /** @var ?JournalEntry */
        return $this->hydrate($row);
    }

    /**
     * Get journal entries by status.
     *
     * @return JournalEntry[]
     */
    public function findByStatus(string $status): array
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE status = :status
ORDER BY journal_date DESC, id DESC
SQL;

        $rows = $this->db->fetchAll($sql, [
            'status' => $status,
        ]);

        /** @var JournalEntry[] */
        return $this->hydrateCollection($rows);
    }

    /**
     * Insert a journal entry.
     */
    public function insert(JournalEntry $journal): JournalEntry
    {
        /** @var JournalEntry */
        return $this->insertEntity($journal);
    }

    /**
     * Save a journal entry.
     */
    public function save(JournalEntry $journal): JournalEntry
    {
        /** @var JournalEntry */
        return $this->saveEntity($journal);
    }

    /**
     * Remove a journal entry.
     */
    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}