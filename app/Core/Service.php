<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Contracts\RepositoryInterface;
use App\Core\Contracts\ServiceInterface;

abstract class Service implements ServiceInterface
{
    public function __construct(
        protected readonly RepositoryInterface $repository
    ) {
    }

    /**
     * Find a record by ID.
     */
    public function findById(int|string $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Retrieve all records.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }

    /**
     * Create a record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return $this->repository->create($data);
    }

    /**
     * Update a record.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Delete a record.
     */
    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Check if a record exists.
     */
    public function exists(int|string $id): bool
    {
        return $this->repository->exists($id);
    }

    /**
     * Count records.
     */
    public function count(array $filters = []): int
    {
        return $this->repository->count($filters);
    }
}