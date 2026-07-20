<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\ExpenseCategoryRepositoryInterface;
use App\Modules\Finance\Entities\ExpenseCategory;

final class ExpenseCategoryService extends BaseFinanceService
{
    public function __construct(
        private readonly ExpenseCategoryRepositoryInterface $expenseCategoryRepository
    ) {
    }

    public function getById(int|string $id): ?ExpenseCategory
    {
        return $this->expenseCategoryRepository->getById($id);
    }

    /**
     * @param array<string,mixed> $filters
     * @return ExpenseCategory[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->expenseCategoryRepository->getAll($filters);
    }

    /**
     * @return ExpenseCategory[]
     */
    public function getActive(): array
    {
        return $this->expenseCategoryRepository->getActive();
    }

    public function findByCode(string $categoryCode): ?ExpenseCategory
    {
        return $this->expenseCategoryRepository->findByCode($categoryCode);
    }

    public function create(ExpenseCategory $expenseCategory): ExpenseCategory
    {
        return $this->expenseCategoryRepository->insert($expenseCategory);
    }

    public function save(ExpenseCategory $expenseCategory): ExpenseCategory
    {
        return $this->expenseCategoryRepository->save($expenseCategory);
    }

    public function remove(int|string $id): bool
    {
        return $this->expenseCategoryRepository->remove($id);
    }

    public function isSystemCategory(ExpenseCategory $expenseCategory): bool
    {
        return $expenseCategory->is_system;
    }

    public function isActive(ExpenseCategory $expenseCategory): bool
    {
        return $expenseCategory->is_active;
    }

    public function canDelete(ExpenseCategory $expenseCategory): bool
    {
        return !$expenseCategory->is_system;
    }
}