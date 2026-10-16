<?php
declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Core\Database;
use App\Modules\Finance\Repositories\FinancialYearRepository;
use App\Modules\Finance\Requests\FinancialYearRequest;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class FinancialYearService
{
    public function __construct(
        private readonly Database $db,
        private readonly FinancialYearRepository $financialYears,
        private readonly FinancialYearRequest $request
    ) {
    }

    public function create(
        int $companyId,
        array $input,
        ?int $actorId = null
    ): array {
        $this->validateIds($companyId, $actorId);

        $data = $this->request->validate($input);

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $company = $this->db->fetch(
                'SELECT id
                 FROM companies
                 WHERE id = :company_id
                 FOR UPDATE',
                ['company_id' => $companyId]
            );

            if ($company === null) {
                throw new InvalidArgumentException(
                    'The company does not exist.'
                );
            }

            if ($this->financialYears->findByCompanyAndCode(
                $companyId,
                $data['year_code']
            ) !== null) {
                throw new InvalidArgumentException(
                    'This financial year code already exists for the company.'
                );
            }

            if ($this->financialYears->hasOverlappingDateRange(
                $companyId,
                $data['start_date'],
                $data['end_date']
            )) {
                throw new InvalidArgumentException(
                    'The financial year overlaps an existing financial year.'
                );
            }

            $this->db->execute(
                'INSERT INTO financial_years (
                    uuid,
                    company_id,
                    year_code,
                    year_name,
                    start_date,
                    end_date,
                    status,
                    is_current,
                    created_by,
                    updated_by
                ) VALUES (
                    :uuid,
                    :company_id,
                    :year_code,
                    :year_name,
                    :start_date,
                    :end_date,
                    :status,
                    :is_current,
                    :created_by,
                    :updated_by
                )',
                [
                    'uuid' => \generateUuid(),
                    'company_id' => $companyId,
                    'year_code' => $data['year_code'],
                    'year_name' => $data['year_name'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'status' => 'OPEN',
                    'is_current' => 0,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]
            );

            $yearId = (int) $this->db->lastInsertId();

            $year = $this->financialYears->findByCompanyAndId(
                $companyId,
                $yearId
            );

            if ($year === null) {
                throw new RuntimeException(
                    'The financial year could not be reloaded after creation.'
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $year;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $exception;
        }
    }

    public function listYears(int $companyId): array
    {
        $this->validateIds($companyId);

        return $this->financialYears->findAllByCompany($companyId);
    }

    public function getYear(int $companyId, int $yearId): ?array
    {
        $this->validateIds($companyId, null, $yearId);

        return $this->financialYears->findByCompanyAndId(
            $companyId,
            $yearId
        );
    }

    public function setCurrentYear(
        int $companyId,
        int $yearId,
        ?int $actorId = null
    ): array {
        $this->validateIds($companyId, $actorId, $yearId);

        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $company = $this->db->fetch(
                'SELECT id
                 FROM companies
                 WHERE id = :company_id
                 FOR UPDATE',
                ['company_id' => $companyId]
            );

            if ($company === null) {
                throw new InvalidArgumentException(
                    'The company does not exist.'
                );
            }

            $year = $this->financialYears->findByCompanyAndId(
                $companyId,
                $yearId
            );

            if ($year === null) {
                throw new InvalidArgumentException(
                    'The financial year does not exist for this company.'
                );
            }

            if (($year['status'] ?? null) !== 'OPEN') {
                throw new InvalidArgumentException(
                    'Only an open financial year can be set as current.'
                );
            }

            $this->db->execute(
                'UPDATE financial_years
                 SET is_current = 0,
                     updated_by = :clear_actor
                 WHERE company_id = :clear_company_id',
                [
                    'clear_actor' => $actorId,
                    'clear_company_id' => $companyId,
                ]
            );

            $this->db->execute(
                'UPDATE financial_years
                 SET is_current = 1,
                     updated_by = :set_actor
                 WHERE company_id = :set_company_id
                   AND id = :set_year_id
                   AND status = :set_status',
                [
                    'set_actor' => $actorId,
                    'set_company_id' => $companyId,
                    'set_year_id' => $yearId,
                    'set_status' => 'OPEN',
                ]
            );

            $currentYear = $this->financialYears->findCurrentByCompany(
                $companyId
            );

            if (
                $currentYear === null
                || (int) $currentYear['id'] !== $yearId
            ) {
                throw new RuntimeException(
                    'The current financial year could not be verified.'
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $currentYear;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            throw $exception;
        }
    }

    private function validateIds(
        int $companyId,
        ?int $actorId = null,
        ?int $yearId = null
    ): void {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'A valid company ID is required.'
            );
        }

        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException(
                'The actor ID must be positive when provided.'
            );
        }

        if ($yearId !== null && $yearId < 1) {
            throw new InvalidArgumentException(
                'A valid financial year ID is required.'
            );
        }
    }
}