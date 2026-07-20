<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\PostingRuleRepositoryInterface;
use App\Modules\Finance\Entities\PostingRule;

final class PostingRuleService extends BaseFinanceService
{
    public function __construct(
        private readonly PostingRuleRepositoryInterface $postingRuleRepository
    ) {
    }

    /**
     * Get a posting rule by ID.
     */
    public function getById(int|string $id): ?PostingRule
    {
        return $this->postingRuleRepository->getById($id);
    }

    /**
     * Get all posting rules.
     *
     * @param array<string, mixed> $filters
     * @return PostingRule[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->postingRuleRepository->getAll($filters);
    }

    /**
     * Get all active posting rules.
     *
     * @return PostingRule[]
     */
    public function getActive(): array
    {
        return $this->postingRuleRepository->getActive();
    }

    /**
     * Find a posting rule by transaction type.
     */
    public function findByTransactionType(string $transactionType): ?PostingRule
    {
        return $this->postingRuleRepository->findByTransactionType($transactionType);
    }

    /**
     * Create a posting rule.
     */
    public function create(PostingRule $postingRule): PostingRule
    {
        return $this->postingRuleRepository->insert($postingRule);
    }

    /**
     * Save a posting rule.
     */
    public function save(PostingRule $postingRule): PostingRule
    {
        return $this->postingRuleRepository->save($postingRule);
    }

    /**
     * Remove a posting rule.
     */
    public function remove(int|string $id): bool
    {
        return $this->postingRuleRepository->remove($id);
    }
}