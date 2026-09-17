<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\AccountClass;

interface AccountClassRepositoryInterface
{
    public function getById(int|string $id): ?AccountClass;

    /**
     * @param array<string,mixed> $filters
     * @return AccountClass[]
     */
    public function getAll(array $filters = []): array;

    /**
     * @return AccountClass[]
     */
    public function getActive(): array;

    /**
     * @return AccountClass[]
     */
    public function findByAccountType(int $accountTypeId): array;

    public function findByCode(string $classCode): ?AccountClass;

    public function insert(AccountClass $accountClass): AccountClass;

    public function save(AccountClass $accountClass): AccountClass;

    public function remove(int|string $id): bool;
}