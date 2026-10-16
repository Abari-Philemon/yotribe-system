<?php
declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Core\Repository;
use InvalidArgumentException;

final class FinancialYearRepository extends Repository
{
    protected string $table = 'financial_years';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Find a financial year within the specified company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndId(
        int $companyId,
        int $financialYearId
    ): ?array {
        $this->validateIds($companyId, $financialYearId);

        return $this->db->fetch(
            'SELECT *
             FROM financial_years
             WHERE company_id = :company_id
               AND id = :financial_year_id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'financial_year_id' => $financialYearId,
            ]
        );
    }
    /**
     * Find and lock a financial year within its company.
     *
     * An active transaction is required for the row lock to be useful.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndIdForUpdate(
        int $companyId,
        int $financialYearId
    ): ?array {
        $this->validateIds($companyId, $financialYearId);

        if (!$this->db->inTransaction()) {
            throw new \RuntimeException(
                'An active transaction is required to lock a financial year.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM financial_years
             WHERE company_id = :company_id
               AND id = :financial_year_id
             LIMIT 1
             FOR UPDATE',
            [
                'company_id' => $companyId,
                'financial_year_id' => $financialYearId,
            ]
        );
    }

    /**
     * Find a financial year by its code within a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndCode(
        int $companyId,
        string $yearCode
    ): ?array {
        $this->validateCompanyId($companyId);
        $yearCode = trim($yearCode);

        if ($yearCode === '') {
            throw new InvalidArgumentException(
                'Financial year code cannot be empty.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM financial_years
             WHERE company_id = :company_id
               AND year_code = :year_code
             LIMIT 1',
            [
                'company_id' => $companyId,
                'year_code' => $yearCode,
            ]
        );
    }

    /**
     * List financial years for one company.
     *
     * @return array<array<string, mixed>>
     */
    public function findAllByCompany(
        int $companyId
    ): array {
        $this->validateCompanyId($companyId);

        return $this->db->fetchAll(
            'SELECT *
             FROM financial_years
             WHERE company_id = :company_id
             ORDER BY start_date DESC, id DESC',
            ['company_id' => $companyId]
        );
    }

    /**
     * Find the current financial year for a company.
     *
     * @return array<string, mixed>|null
     */
    public function findCurrentByCompany(
        int $companyId
    ): ?array {
        $this->validateCompanyId($companyId);

        return $this->db->fetch(
            'SELECT *
             FROM financial_years
             WHERE company_id = :company_id
               AND is_current = 1
             LIMIT 1',
            ['company_id' => $companyId]
        );
    }

    /**
     * Check whether a date range overlaps another year
     * belonging to the same company.
     */
    /**
     * Find the financial year containing a date.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndDate(
        int $companyId,
        string $date
    ): ?array {
        $this->validateCompanyId($companyId);

        if ($date === '') {
            throw new InvalidArgumentException(
                'Date is required.'
            );
        }

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

        return $this->db->fetch(
            'SELECT *
             FROM financial_years
             WHERE company_id = :company_id
               AND start_date <= :start_date
               AND end_date >= :end_date
             ORDER BY start_date DESC, id DESC
             LIMIT 1',
            [
                'company_id' => $companyId,
                'start_date' => $date,
                'end_date' => $date,
            ]
        );
    }
    public function hasOverlappingDateRange(
        int $companyId,
        string $startDate,
        string $endDate,
        ?int $excludeYearId = null
    ): bool {
        $this->validateCompanyId($companyId);

        if (
            $startDate === ''
            || $endDate === ''
            || $startDate > $endDate
        ) {
            throw new InvalidArgumentException(
                'A valid financial year date range is required.'
            );
        }

        $sql = '
            SELECT id
            FROM financial_years
            WHERE company_id = :company_id
              AND start_date <= :end_date
              AND end_date >= :start_date
        ';

        $params = [
            'company_id' => $companyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($excludeYearId !== null) {
            if ($excludeYearId < 1) {
                throw new InvalidArgumentException(
                    'Excluded financial year ID must be positive.'
                );
            }

            $sql .= ' AND id <> :exclude_year_id';
            $params['exclude_year_id'] = $excludeYearId;
        }

        $sql .= ' LIMIT 1';

        return $this->db->fetch($sql, $params) !== null;
    }

    private function validateIds(
        int $companyId,
        int $financialYearId
    ): void {
        $this->validateCompanyId($companyId);

        if ($financialYearId < 1) {
            throw new InvalidArgumentException(
                'Financial year ID must be a positive integer.'
            );
        }
    }

    private function validateCompanyId(int $companyId): void
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'Company ID must be a positive integer.'
            );
        }
    }
}


