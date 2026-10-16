<?php
declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Core\Repository;
use InvalidArgumentException;

final class AccountTypeRepository extends Repository
{
    protected string $table = 'account_types';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Find an account type by ID within a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndId(
        int $companyId,
        int $typeId
    ): ?array {
        $this->validateIds($companyId, $typeId);

        return $this->db->fetch(
            'SELECT *
             FROM account_types
             WHERE company_id = :company_id
               AND id = :type_id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'type_id' => $typeId,
            ]
        );
    }

    /**
     * Find an account type by code within a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndCode(
        int $companyId,
        string $typeCode
    ): ?array {
        $this->validateCompanyId($companyId);
        $typeCode = strtoupper(trim($typeCode));

        if ($typeCode === '') {
            throw new InvalidArgumentException(
                'Account type code cannot be empty.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM account_types
             WHERE company_id = :company_id
               AND type_code = :type_code
             LIMIT 1',
            [
                'company_id' => $companyId,
                'type_code' => $typeCode,
            ]
        );
    }

    /**
     * List account types for a company.
     *
     * @return array<array<string, mixed>>
     */
    public function findAllByCompany(
        int $companyId,
        bool $activeOnly = true
    ): array {
        $this->validateCompanyId($companyId);

        $sql = '
            SELECT *
            FROM account_types
            WHERE company_id = :company_id
        ';

        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY display_order, type_code, id';

        return $this->db->fetchAll($sql, [
            'company_id' => $companyId,
        ]);
    }

    /**
     * Confirm that an account type belongs to a company.
     */
    public function belongsToCompany(
        int $companyId,
        int $typeId
    ): bool {
        return $this->findByCompanyAndId(
            $companyId,
            $typeId
        ) !== null;
    }

    private function validateIds(
        int $companyId,
        int $typeId
    ): void {
        $this->validateCompanyId($companyId);

        if ($typeId < 1) {
            throw new InvalidArgumentException(
                'Account type ID must be a positive integer.'
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