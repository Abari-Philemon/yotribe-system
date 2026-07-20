<?php

declare(strict_types=1);

namespace App\Core\Contracts;

interface ServiceInterface
{
    /**
     * Find a record by ID.
     */
    public function findById(int|string $id): ?array;

    /**
     * Retrieve all records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $filters = []): array;

    /**
     * Create a new record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int;

    /**
     * Update an existing record.
     *
     * @param array<string, mixed> $data
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
     */
    public function count(array $filters = []): int;
}