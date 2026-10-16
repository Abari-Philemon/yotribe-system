<?php
declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Core\Repository;
use InvalidArgumentException;

final class FinancialPeriodRepository extends Repository
{
    protected string $table = 'financial_periods';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Find a period within its company and financial year.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyYearAndId(
        int $companyId,
        int $financialYearId,
        int $periodId
    ): ?array {
        $this->validateIds($companyId, $financialYearId, $periodId);

        return $this->db->fetch(
            'SELECT *
             FROM financial_periods
             WHERE company_id = :company_id
               AND financial_year_id = :financial_year_id
               AND id = :period_id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'financial_year_id' => $financialYearId,
                'period_id' => $periodId,
            ]
        );
    }

    /**
     * Find a period by its number within a financial year.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyYearAndNumber(
        int $companyId,
        int $financialYearId,
        int $periodNo
    ): ?array {
        $this->validateIds($companyId, $financialYearId);

        if ($periodNo < 1 || $periodNo > 12) {
            throw new InvalidArgumentException(
                'Period number must be between 1 and 12.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM financial_periods
             WHERE company_id = :company_id
               AND financial_year_id = :financial_year_id
               AND period_no = :period_no
             LIMIT 1',
            [
                'company_id' => $companyId,
                'financial_year_id' => $financialYearId,
                'period_no' => $periodNo,
            ]
        );
    }

    /**
     * List periods for a financial year in calendar order.
     *
     * @return array<array<string, mixed>>
     */
    public function findAllByCompanyAndYear(
        int $companyId,
        int $financialYearId
    ): array {
        $this->validateIds($companyId, $financialYearId);

        return $this->db->fetchAll(
            'SELECT *
             FROM financial_periods
             WHERE company_id = :company_id
               AND financial_year_id = :financial_year_id
             ORDER BY period_no ASC, start_date ASC, id ASC',
            [
                'company_id' => $companyId,
                'financial_year_id' => $financialYearId,
            ]
        );
    }

    /**
     * Find the current period for a company and financial year.
     *
     * @return array<string, mixed>|null
     */
    public function findCurrentByCompanyAndYear(
        int $companyId,
        int $financialYearId
    ): ?array {
        $this->validateIds($companyId, $financialYearId);

        return $this->db->fetch(
            'SELECT *
             FROM financial_periods
             WHERE company_id = :company_id
               AND financial_year_id = :financial_year_id
               AND is_current = 1
             LIMIT 1',
            [
                'company_id' => $companyId,
                'financial_year_id' => $financialYearId,
            ]
        );
    }

    /**
     * Find the period containing a date.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndDate(
        int $companyId,
        string $date
    ): ?array {
        $this->validateCompanyId($companyId);
        $this->validateDate($date);

        return $this->db->fetch(
            'SELECT *
             FROM financial_periods
             WHERE company_id = :company_id
               AND start_date <= :period_start_date
               AND end_date >= :period_end_date
             ORDER BY start_date DESC, id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'period_start_date' => $date,
                'period_end_date' => $date,
            ]
        );
    }

    /**
     * Check for overlapping periods within a financial year.
     */
    public function hasOverlappingDateRange(
        int $companyId,
        int $financialYearId,
        string $startDate,
        string $endDate,
        ?int $excludePeriodId = null
    ): bool {
        $this->validateIds($companyId, $financialYearId);
        $this->validateDateRange($startDate, $endDate);

        $sql = '
            SELECT id
            FROM financial_periods
            WHERE company_id = :company_id
              AND financial_year_id = :financial_year_id
              AND start_date <= :end_date
              AND end_date >= :start_date
        ';

        $params = [
            'company_id' => $companyId,
            'financial_year_id' => $financialYearId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($excludePeriodId !== null) {
            if ($excludePeriodId < 1) {
                throw new InvalidArgumentException(
                    'Excluded period ID must be positive.'
                );
            }

            $sql .= ' AND id <> :exclude_period_id';
            $params['exclude_period_id'] = $excludePeriodId;
        }

        $sql .= ' LIMIT 1';

        return $this->db->fetch($sql, $params) !== null;
    }

    private function validateIds(
        int $companyId,
        int $financialYearId,
        ?int $periodId = null
    ): void {
        $this->validateCompanyId($companyId);

        if ($financialYearId < 1) {
            throw new InvalidArgumentException(
                'Financial year ID must be positive.'
            );
        }

        if ($periodId !== null && $periodId < 1) {
            throw new InvalidArgumentException(
                'Period ID must be positive.'
            );
        }
    }

    private function validateCompanyId(int $companyId): void
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'Company ID must be positive.'
            );
        }
    }

    private function validateDate(string $date): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $parsed === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException(
                'Date must be a valid date in YYYY-MM-DD format.'
            );
        }
    }

    private function validateDateRange(
        string $startDate,
        string $endDate
    ): void {
        $this->validateDate($startDate);
        $this->validateDate($endDate);

        if ($startDate > $endDate) {
            throw new InvalidArgumentException(
                'Period start date cannot be after its end date.'
            );
        }
    }
}