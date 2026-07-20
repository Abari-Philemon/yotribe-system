<?php

declare(strict_types=1);

namespace App\Core\Contracts;

/**
 * Repository Interface
 *
 * Defines the minimum contract for all repositories.
 */
interface RepositoryInterface
{
    /**
     * Find a record by its primary key.
     */
    public function findById(int|string $id): ?array;

    /**
     * Retrieve multiple records.
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function findAll(array $filters = []): array;

    /**
     * Create a new record.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int;

    /**
     * Update an existing record.
     *
     * @param int|string $id
     * @param array<string,mixed> $data
     */
    public function update(int|string $id, array $data): bool;

    /**
     * Delete a record.
     */
    public function delete(int|string $id): bool;

    /**
     * Check if a record exists.
     */
    public function exists(int|string $id): bool;

    /**
     * Count records.
     *
     * @param array<string,mixed> $filters
     */
    public function count(array $filters = []): int;
}