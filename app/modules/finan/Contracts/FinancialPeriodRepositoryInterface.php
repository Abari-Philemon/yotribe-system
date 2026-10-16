<?php
declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\FinancialPeriod;
use App\Modules\Finance\Enums\FinancialStatus;

interface FinancialPeriodRepositoryInterface
{
    public function getById(int $id): ?FinancialPeriod;

    /**
     * @return FinancialPeriod[]
     */
    public function getAll(): array;

    /**
     * @return FinancialPeriod[]
     */
    public function findByCompany(int $companyId): array;

    /**
     * @return FinancialPeriod[]
     */
    public function findByFinancialYear(int $financialYearId): array;

    /**
     * @return FinancialPeriod[]
     */
    public function findByStatus(FinancialStatus $status): array;

    public function findCurrentPeriod(
        int $companyId,
        string $businessDate
    ): ?FinancialPeriod;

    /**
     * @return FinancialPeriod[]
     */
    public function findAdjustmentPeriods(
        int $financialYearId
    ): array;

    public function insert(FinancialPeriod $period): int;

    public function save(FinancialPeriod $period): bool;

    public function remove(int $id): bool;
}