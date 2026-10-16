<?php
declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Core\Database;
use App\Modules\Finance\Repositories\FinancialPeriodRepository;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class FinancialPeriodService
{
    public function __construct(
        private readonly Database $db,
        private readonly FinancialYearRepository $financialYears,
        private readonly FinancialPeriodRepository $financialPeriods
    ) {
    }

    /**
     * Generate monthly periods for a financial year that has no periods.
     *
     * This initial implementation supports financial years beginning on
     * the first day of a month and ending on the last day of a month,
     * spanning exactly twelve calendar months.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateMonthlyPeriods(
        int $companyId,
        int $financialYearId,
        ?int $actorId = null
    ): array {
        $this->validateIds($companyId, $financialYearId, $actorId);

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $year = $this->financialYears->findByCompanyAndIdForUpdate(
                $companyId,
                $financialYearId
            );

            if ($year === null) {
                throw new InvalidArgumentException(
                    'Financial year was not found for this company.'
                );
            }

            if (strtoupper((string) $year['status']) !== 'OPEN') {
                throw new RuntimeException(
                    'Monthly periods can only be generated for an open financial year.'
                );
            }

            $start = $this->parseDate((string) $year['start_date']);
            $end = $this->parseDate((string) $year['end_date']);

            if (
                $start->format('d') !== '01'
                || $end->format('Y-m-d')
                    !== $end->modify('last day of this month')->format('Y-m-d')
                || $start->modify('+11 months')->format('Y-m')
                    !== $end->format('Y-m')
            ) {
                throw new InvalidArgumentException(
                    'Monthly generation requires a twelve-month financial year '
                    . 'starting on the first day and ending on the last day of a month.'
                );
            }

            if ($this->financialPeriods->findAllByCompanyAndYear(
                $companyId,
                $financialYearId
            ) !== []) {
                throw new RuntimeException(
                    'This financial year already has periods. Generation was not started.'
                );
            }

            $created = [];
            $month = $start;

            for ($periodNo = 1; $periodNo <= 12; $periodNo++) {
                $periodStart = $month->format('Y-m-d');
                $periodEnd = $month->modify('last day of this month')
                    ->format('Y-m-d');

                $params = [
                    'uuid' => \generateUuid(),
                    'company_id' => $companyId,
                    'financial_year_id' => $financialYearId,
                    'period_no' => $periodNo,
                    'period_code' => $month->format('Ym'),
                    'period_name' => $month->format('F Y'),
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'status' => 'OPEN',
                    'is_current' => 0,
                ];

                if ($actorId !== null) {
                    $params['created_by'] = $actorId;
                    $params['updated_by'] = $actorId;
                }

                $columns = array_keys($params);
                $placeholders = array_map(
                    static fn (string $column): string => ':' . $column,
                    $columns
                );

                $this->db->execute(
                    'INSERT INTO financial_periods ('
                    . implode(', ', $columns)
                    . ') VALUES ('
                    . implode(', ', $placeholders)
                    . ')',
                    $params
                );

                $month = $month->modify('first day of next month');
            }

            $created = $this->financialPeriods->findAllByCompanyAndYear(
                $companyId,
                $financialYearId
            );

            if (count($created) !== 12) {
                throw new RuntimeException(
                    'Period generation did not produce exactly twelve periods.'
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $created;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $e;
        }
    }

    /**
     * Set one open period as current for its company and financial year.
     *
     * @return array<string, mixed>
     */
    public function setCurrentPeriod(
        int $companyId,
        int $financialYearId,
        int $periodId,
        ?int $actorId = null
    ): array {
        $this->validateIds(
            $companyId,
            $financialYearId,
            $actorId,
            $periodId
        );

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $year = $this->financialYears->findByCompanyAndIdForUpdate(
                $companyId,
                $financialYearId
            );

            if ($year === null) {
                throw new InvalidArgumentException(
                    'Financial year was not found for this company.'
                );
            }

            if (strtoupper((string) $year['status']) !== 'OPEN') {
                throw new RuntimeException(
                    'A current period can only be selected in an open financial year.'
                );
            }

            $period = $this->financialPeriods->findByCompanyYearAndId(
                $companyId,
                $financialYearId,
                $periodId
            );

            if ($period === null) {
                throw new InvalidArgumentException(
                    'Financial period was not found for this company and year.'
                );
            }

            if (strtoupper((string) $period['status']) !== 'OPEN') {
                throw new RuntimeException(
                    'Only an open financial period can be set as current.'
                );
            }

            $this->db->execute(
                'UPDATE financial_periods
                 SET is_current = 0,
                     updated_by = COALESCE(:clear_actor_id, updated_by)
                 WHERE company_id = :clear_company_id
                   AND financial_year_id = :clear_year_id
                   AND is_current = 1',
                [
                    'clear_actor_id' => $actorId,
                    'clear_company_id' => $companyId,
                    'clear_year_id' => $financialYearId,
                ]
            );

            $this->db->execute(
                'UPDATE financial_periods
                 SET is_current = 1,
                     updated_by = COALESCE(:set_actor_id, updated_by)
                 WHERE company_id = :set_company_id
                   AND financial_year_id = :set_year_id
                   AND id = :set_period_id',
                [
                    'set_actor_id' => $actorId,
                    'set_company_id' => $companyId,
                    'set_year_id' => $financialYearId,
                    'set_period_id' => $periodId,
                ]
            );

            $current = $this->financialPeriods->findCurrentByCompanyAndYear(
                $companyId,
                $financialYearId
            );

            if ($current === null || (int) $current['id'] !== $periodId) {
                throw new RuntimeException(
                    'The requested current period could not be verified.'
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $current;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $e;
        }
    }
    private function validateIds(
        int $companyId,
        int $financialYearId,
        ?int $actorId = null,
        ?int $periodId = null
    ): void {
        if ($companyId < 1 || $financialYearId < 1) {
            throw new InvalidArgumentException(
                'Company ID and financial year ID must be positive integers.'
            );
        }

        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException(
                'Actor ID must be a positive integer.'
            );
        }

        if ($periodId !== null && $periodId < 1) {
            throw new InvalidArgumentException(
                'Period ID must be a positive integer.'
            );
        }
    }

    private function parseDate(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $parsed === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException(
                'Financial year contains an invalid date.'
            );
        }

        return $parsed;
    }
}