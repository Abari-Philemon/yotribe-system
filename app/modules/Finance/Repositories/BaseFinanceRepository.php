<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Repository;

abstract class BaseFinanceRepository extends Repository
{
    /**
     * Database table name.
     */
    protected string $table;

    /**
     * Fully-qualified entity class name.
     *
     * Example:
     * App\Modules\Finance\Entities\Account::class
     */
    protected string $entityClass;

    /**
     * Hydrate a single entity.
     */
    protected function hydrate(?array $row): ?object
    {
        if ($row === null) {
            return null;
        }

        /** @var class-string $class */
        $class = $this->entityClass;

        return $class::fromArray($row);
    }

    /**
     * Hydrate multiple entities.
     *
     * @return array<object>
     */
    protected function hydrateCollection(array $rows): array
    {
        return array_map(
            fn(array $row) => $this->hydrate($row),
            $rows
        );
    }

    /**
     * Get a hydrated entity by ID.
     */
    protected function getEntityById(int|string $id): ?object
    {
        return $this->hydrate(
            parent::findById($id)
        );
    }

    /**
     * Get hydrated entities.
     *
     * @param array<string,mixed> $filters
     * @return array<object>
     */
    protected function getEntities(array $filters = []): array
    {
        return $this->hydrateCollection(
            parent::findAll($filters)
        );
    }

    /**
     * Insert an entity.
     */
    protected function insertEntity(object $entity): ?object
    {
        /** @phpstan-ignore-next-line */
        $id = parent::create($entity->toArray());

        return $this->getEntityById($id);
    }

    /**
     * Save an entity.
     */
    protected function saveEntity(object $entity): ?object
    {
        /** @phpstan-ignore-next-line */
        parent::update(
            $entity->id,
            $entity->toArray()
        );

        /** @phpstan-ignore-next-line */
        return $this->getEntityById($entity->id);
    }

    /**
     * Remove an entity.
     */
    protected function removeEntity(int|string $id): bool
    {
        return parent::delete($id);
    }
}