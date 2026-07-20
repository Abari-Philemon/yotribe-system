<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\IncomeCategoryRepositoryInterface;
use App\Modules\Finance\Entities\IncomeCategory;

final class IncomeCategoryService extends BaseFinanceService
{
    public function __construct(
        private readonly IncomeCategoryRepositoryInterface $incomeCategoryRepository
    ) {
    }

    public function getById(int|string $id): ?IncomeCategory
    {
        return $this->incomeCategoryRepository->getById($id);
    }

    /**
     * @param array<string,mixed> $filters
     * @return IncomeCategory[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->incomeCategoryRepository->getAll($filters);
    }

    /**
     * @return IncomeCategory[]
     */
    public function getActive(): array
    {
        return $this->incomeCategoryRepository->getActive();
    }

    public function findByCode(string $categoryCode): ?IncomeCategory
    {
        return $this->incomeCategoryRepository->findByCode($categoryCode);
    }

    public function create(IncomeCategory $incomeCategory): IncomeCategory
    {
        return $this->incomeCategoryRepository->insert($incomeCategory);
    }

    public function save(IncomeCategory $incomeCategory): IncomeCategory
    {
        return $this->incomeCategoryRepository->save($incomeCategory);
    }

    public function remove(int|string $id): bool
    {
        return $this->incomeCategoryRepository->remove($id);
    }

    public function isSystemCategory(IncomeCategory $incomeCategory): bool
    {
        return $incomeCategory->is_system;
    }

    public function isActive(IncomeCategory $incomeCategory): bool
    {
        return $incomeCategory->is_active;
    }

    public function canDelete(IncomeCategory $incomeCategory): bool
    {
        return !$incomeCategory->is_system;
    }
}