<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\FinancialYear;

interface FinancialYearRepositoryInterface
{
    public function getById(int|string $id): ?FinancialYear;

    /**
     * @param array<string,mixed> $filters
     * @return FinancialYear[]
     */
    public function getAll(array $filters = []): array;

    /**
     * Get the currently active financial year.
     */
    public function getActive(): ?FinancialYear;

    /**
     * Get a financial year by its code.
     */
    public function findByCode(string $code): ?FinancialYear;

    /**
     * Insert a new financial year.
     */
    public function insert(FinancialYear $financialYear): FinancialYear;

    /**
     * Save changes to a financial year.
     */
    public function save(FinancialYear $financialYear): FinancialYear;

    /**
     * Remove a financial year.
     */
    public function remove(int|string $id): bool;
}