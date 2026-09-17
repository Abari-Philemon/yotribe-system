<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\ExpenseCategory;

interface ExpenseCategoryRepositoryInterface
{
    public function getById(int|string $id): ?ExpenseCategory;

    /**
     * @param array<string,mixed> $filters
     * @return ExpenseCategory[]
     */
    public function getAll(array $filters = []): array;

    /**
     * @return ExpenseCategory[]
     */
    public function getActive(): array;

    public function findByCode(string $categoryCode): ?ExpenseCategory;

    public function insert(ExpenseCategory $expenseCategory): ExpenseCategory;

    public function save(ExpenseCategory $expenseCategory): ExpenseCategory;

    public function remove(int|string $id): bool;
}