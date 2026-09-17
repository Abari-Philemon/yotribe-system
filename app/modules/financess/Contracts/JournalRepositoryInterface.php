<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\JournalEntry;

interface JournalRepositoryInterface
{
    public function getById(int|string $id): ?JournalEntry;

    /**
     * @param array<string,mixed> $filters
     * @return JournalEntry[]
     */
    public function getAll(array $filters = []): array;

    /**
     * Find a journal entry by journal number.
     */
    public function findByJournalNumber(string $journalNumber): ?JournalEntry;

    /**
     * Get journal entries by status.
     *
     * @return JournalEntry[]
     */
    public function findByStatus(string $status): array;

    /**
     * Insert a journal entry.
     */
    public function insert(JournalEntry $journal): JournalEntry;

    /**
     * Save a journal entry.
     */
    public function save(JournalEntry $journal): JournalEntry;

    /**
     * Remove a journal entry.
     */
    public function remove(int|string $id): bool;
}