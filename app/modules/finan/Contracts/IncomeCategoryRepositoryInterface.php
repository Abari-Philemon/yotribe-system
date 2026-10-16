<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\IncomeCategory;

interface IncomeCategoryRepositoryInterface
{
    public function getById(int|string $id): ?IncomeCategory;

    /**
     * @param array<string,mixed> $filters
     * @return IncomeCategory[]
     */
    public function getAll(array $filters = []): array;

    /**
     * @return IncomeCategory[]
     */
    public function getActive(): array;

    public function findByCode(string $categoryCode): ?IncomeCategory;

    public function insert(IncomeCategory $incomeCategory): IncomeCategory;

    public function save(IncomeCategory $incomeCategory): IncomeCategory;

    public function remove(int|string $id): bool;
}