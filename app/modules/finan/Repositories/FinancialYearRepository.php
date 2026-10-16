<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Modules\Finance\Contracts\FinancialYearRepositoryInterface;
use App\Modules\Finance\Entities\FinancialYear;

final class FinancialYearRepository extends BaseFinanceRepository implements FinancialYearRepositoryInterface
{
    protected string $table = 'financial_years';

    /**
     * Get a financial year by its ID.
     */
    public function getById(int|string $id): ?FinancialYear
    {
        $row = parent::findById($id);

        return $row === null
            ? null
            : FinancialYear::fromArray($row);
    }

    /**
     * Get all financial years.
     *
     * @param array<string,mixed> $filters
     * @return FinancialYear[]
     */
    public function getAll(array $filters = []): array
    {
        $rows = parent::findAll($filters);

        return array_map(
            static fn(array $row): FinancialYear => FinancialYear::fromArray($row),
            $rows
        );
    }

    /**
     * Get the active financial year.
     */
    public function getActive(): ?FinancialYear
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE is_active = 1
LIMIT 1
SQL;

        $row = $this->db->fetch($sql);

        return $row === null
            ? null
            : FinancialYear::fromArray($row);
    }

    /**
     * Find a financial year by code.
     */
    public function findByCode(string $code): ?FinancialYear
    {
        $sql = <<<SQL
SELECT *
FROM {$this->table}
WHERE code = :code
LIMIT 1
SQL;

        $row = $this->db->fetch($sql, [
            'code' => $code,
        ]);

        return $row === null
            ? null
            : FinancialYear::fromArray($row);
    }

    /**
     * Insert a financial year.
     */
    public function insert(FinancialYear $financialYear): FinancialYear
    {
        $id = parent::create($financialYear->toArray());

        return $this->getById($id);
    }

    /**
     * Save a financial year.
     */
    public function save(FinancialYear $financialYear): FinancialYear
    {
        parent::update(
            $financialYear->id,
            $financialYear->toArray()
        );

        return $this->getById($financialYear->id);
    }

    /**
     * Remove a financial year.
     */
    public function remove(int|string $id): bool
    {
        return parent::delete($id);
    }
}