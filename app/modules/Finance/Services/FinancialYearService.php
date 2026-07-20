<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\FinancialYearRepositoryInterface;
use App\Modules\Finance\Entities\FinancialYear;

final class FinancialYearService extends BaseFinanceService
{
    public function __construct(
        private readonly FinancialYearRepositoryInterface $financialYearRepository
    ) {
    }

    /**
     * Get a financial year by ID.
     */
    public function getById(int|string $id): ?FinancialYear
    {
        return $this->financialYearRepository->getById($id);
    }

    /**
     * Get all financial years.
     *
     * @param array<string, mixed> $filters
     * @return FinancialYear[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->financialYearRepository->getAll($filters);
    }

    /**
     * Get the active financial year.
     */
    public function getActive(): ?FinancialYear
    {
        return $this->financialYearRepository->getActive();
    }

    /**
     * Find a financial year by code.
     */
    public function findByCode(string $code): ?FinancialYear
    {
        return $this->financialYearRepository->findByCode($code);
    }

    /**
     * Create a financial year.
     */
    public function create(FinancialYear $financialYear): FinancialYear
    {
        return $this->financialYearRepository->insert($financialYear);
    }

    /**
     * Save a financial year.
     */
    public function save(FinancialYear $financialYear): FinancialYear
    {
        return $this->financialYearRepository->save($financialYear);
    }

    /**
     * Remove a financial year.
     */
    public function remove(int|string $id): bool
    {
        return $this->financialYearRepository->remove($id);
    }
}