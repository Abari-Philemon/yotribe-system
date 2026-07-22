<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\FinancialPeriodRepositoryInterface;
use App\Modules\Finance\Entities\FinancialPeriod;
use App\Modules\Finance\Enums\FinancialStatus;

final class FinancialPeriodRepository extends BaseFinanceRepository implements FinancialPeriodRepositoryInterface
{
    public function __construct(Database $database)
    {
        parent::__construct($database, 'financial_periods', FinancialPeriod::class);
    }

    public function getById(int $id): ?FinancialPeriod
    {
        return $this->getEntityById($id);
    }

    public function getAll(): array
    {
        return $this->getEntities(
            orderBy: 'period_number ASC'
        );
    }

    public function findByCompany(int $companyId): array
    {
        return $this->find(
            [
                'company_id' => $companyId,
            ],
            orderBy: 'period_number ASC'
        );
    }

    public function findByFinancialYear(int $financialYearId): array
    {
        return $this->find(
            [
                'financial_year_id' => $financialYearId,
            ],
            orderBy: 'period_number ASC'
        );
    }

    public function findByStatus(FinancialStatus $status): array
    {
        return $this->find(
            [
                'status' => $status->value,
            ],
            orderBy: 'period_number ASC'
        );
    }

    public function findCurrentPeriod(
        int $companyId,
        string $businessDate
    ): ?FinancialPeriod {
        return $this->findOne([
            'company_id' => $companyId,
            'start_date <=' => $businessDate,
            'end_date >=' => $businessDate,
        ]);
    }

    public function findAdjustmentPeriods(
        int $financialYearId
    ): array {
        return $this->find(
            [
                'financial_year_id' => $financialYearId,
                'is_adjustment_period' => true,
            ],
            orderBy: 'period_number ASC'
        );
    }

    public function insert(FinancialPeriod $period): int
    {
        return $this->insertEntity($period);
    }

    public function save(FinancialPeriod $period): bool
    {
        return $this->saveEntity($period);
    }

    public function remove(int $id): bool
    {
        return $this->removeEntity($id);
    }
}