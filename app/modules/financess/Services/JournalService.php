<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\JournalRepositoryInterface;
use App\Modules\Finance\Entities\JournalEntry;
use App\Modules\Finance\Enums\JournalStatus;

final class JournalService extends BaseFinanceService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journalRepository
    ) {
    }

    /**
     * Get a journal entry by ID.
     */
    public function getById(int|string $id): ?JournalEntry
    {
        return $this->journalRepository->getById($id);
    }

    /**
     * Get all journal entries.
     *
     * @param array<string, mixed> $filters
     * @return JournalEntry[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->journalRepository->getAll($filters);
    }

    /**
     * Find a journal by journal number.
     */
    public function findByJournalNumber(string $journalNumber): ?JournalEntry
    {
        return $this->journalRepository->findByJournalNumber($journalNumber);
    }

    /**
     * Get journals by status.
     *
     * @return JournalEntry[]
     */
    public function findByStatus(JournalStatus $status): array
    {
        return $this->journalRepository->findByStatus($status->value);
    }

    /**
     * Create a journal entry.
     */
    public function create(JournalEntry $journal): JournalEntry
    {
        return $this->journalRepository->insert($journal);
    }

    /**
     * Save a journal entry.
     */
    public function save(JournalEntry $journal): JournalEntry
    {
        return $this->journalRepository->save($journal);
    }

    /**
     * Remove a journal entry.
     */
    public function remove(int|string $id): bool
    {
        return $this->journalRepository->remove($id);
    }

    /**
     * Determine whether the journal can be edited.
     */
    public function canEdit(JournalEntry $journal): bool
    {
        return $journal->status->canEdit();
    }

    /**
     * Determine whether the journal can be posted.
     */
    public function canPost(JournalEntry $journal): bool
    {
        return $journal->status->canPost();
    }

    /**
     * Determine whether the journal can be reversed.
     */
    public function canReverse(JournalEntry $journal): bool
    {
        return $journal->status->canReverse();
    }

    /**
     * Determine whether the journal can be deleted.
     */
    public function canDelete(JournalEntry $journal): bool
    {
        return $journal->status->canEdit();
    }

    /**
     * Determine whether the journal is balanced.
     */
    public function isBalanced(JournalEntry $journal): bool
    {
        return abs($journal->total_debit - $journal->total_credit) < 0.00001;
    }

    /**
     * Determine whether the journal has been posted.
     */
    public function isPosted(JournalEntry $journal): bool
    {
        return $journal->status === JournalStatus::Posted;
    }

    /**
     * Determine whether the journal is still a draft.
     */
    public function isDraft(JournalEntry $journal): bool
    {
        return $journal->status === JournalStatus::Draft;
    }

    /**
     * Determine whether the journal has been reversed.
     */
    public function isReversed(JournalEntry $journal): bool
    {
        return $journal->status === JournalStatus::Reversed;
    }

    /**
     * Determine whether the journal has been cancelled.
     */
    public function isCancelled(JournalEntry $journal): bool
    {
        return $journal->status === JournalStatus::Cancelled;
    }
}