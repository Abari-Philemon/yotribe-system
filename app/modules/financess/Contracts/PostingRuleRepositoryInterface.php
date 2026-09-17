<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\PostingRule;

interface PostingRuleRepositoryInterface
{
    public function getById(int|string $id): ?PostingRule;

    /**
     * @param array<string,mixed> $filters
     * @return PostingRule[]
     */
    public function getAll(array $filters = []): array;

    /**
     * Find a posting rule by transaction type.
     */
    public function findByTransactionType(string $transactionType): ?PostingRule;

    /**
     * Get active posting rules.
     *
     * @return PostingRule[]
     */
    public function getActive(): array;

    /**
     * Insert a posting rule.
     */
    public function insert(PostingRule $postingRule): PostingRule;

    /**
     * Save a posting rule.
     */
    public function save(PostingRule $postingRule): PostingRule;

    /**
     * Remove a posting rule.
     */
    public function remove(int|string $id): bool;
}