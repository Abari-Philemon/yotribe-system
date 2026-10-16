<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Modules\Finance\Contracts\PostingRuleRepositoryInterface;
use App\Modules\Finance\Entities\PostingRule;

final class PostingRuleRepository extends BaseFinanceRepository implements PostingRuleRepositoryInterface
{
    protected string $table = 'posting_rules';

    protected string $entityClass = PostingRule::class;

    /**
     * Get a posting rule by its ID.
     */
    public function getById(int|string $id): ?PostingRule
    {
        /** @var ?PostingRule */
        return $this->getEntityById($id);
    }

    /**
     * Get all posting rules.
     *
     * @param array<string,mixed> $filters
     * @return PostingRule[]
     */
    public function getAll(array $filters = []): array
    {
        /** @var PostingRule[] */
        return $this->getEntities($filters);
    }

    /**
     * Find a posting rule by transaction type.
     */
    public function findByTransactionType(string $transactionType): ?PostingRule
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE transaction_type = :transaction_type
LIMIT 1
SQL;

        $row = $this->db->fetch($sql, [
            'transaction_type' => $transactionType,
        ]);

        /** @var ?PostingRule */
        return $this->hydrate($row);
    }

    /**
     * Get active posting rules.
     *
     * @return PostingRule[]
     */
    public function getActive(): array
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE is_active = 1
ORDER BY transaction_type ASC
SQL;

        $rows = $this->db->fetchAll($sql);

        /** @var PostingRule[] */
        return $this->hydrateCollection($rows);
    }

    /**
     * Insert a posting rule.
     */
    public function insert(PostingRule $postingRule): PostingRule
    {
        /** @var PostingRule */
        return $this->insertEntity($postingRule);
    }

    /**
     * Save a posting rule.
     */
    public function save(PostingRule $postingRule): PostingRule
    {
        /** @var PostingRule */
        return $this->saveEntity($postingRule);
    }

    /**
     * Remove a posting rule.
     */
    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}