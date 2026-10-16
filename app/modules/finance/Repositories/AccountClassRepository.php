<?php
declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Core\Repository;
use InvalidArgumentException;

final class AccountClassRepository extends Repository
{
    protected string $table = 'account_classes';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Find an account class by ID within a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndId(
        int $companyId,
        int $classId
    ): ?array {
        $this->validateIds($companyId, $classId);

        return $this->db->fetch(
            'SELECT *
             FROM account_classes
             WHERE company_id = :company_id
               AND id = :class_id
             LIMIT 1',
            [
                'company_id' => $companyId,
                'class_id' => $classId,
            ]
        );
    }

    /**
     * Find an account class by code within a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyAndCode(
        int $companyId,
        string $classCode
    ): ?array {
        $this->validateCompanyId($companyId);
        $classCode = strtoupper(trim($classCode));

        if ($classCode === '') {
            throw new InvalidArgumentException(
                'Account class code cannot be empty.'
            );
        }

        return $this->db->fetch(
            'SELECT *
             FROM account_classes
             WHERE company_id = :company_id
               AND class_code = :class_code
             LIMIT 1',
            [
                'company_id' => $companyId,
                'class_code' => $classCode,
            ]
        );
    }

    /**
     * List account classes for a company, optionally filtered by type.
     *
     * @return array<array<string, mixed>>
     */
    public function findAllByCompany(
        int $companyId,
        ?int $accountTypeId = null,
        bool $activeOnly = true
    ): array {
        $this->validateCompanyId($companyId);

        if ($accountTypeId !== null && $accountTypeId < 1) {
            throw new InvalidArgumentException(
                'Account type ID must be a positive integer.'
            );
        }

        $sql = '
            SELECT *
            FROM account_classes
            WHERE company_id = :company_id
        ';

        $params = ['company_id' => $companyId];

        if ($accountTypeId !== null) {
            $sql .= ' AND account_type_id = :account_type_id';
            $params['account_type_id'] = $accountTypeId;
        }

        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY display_order, class_code, id';

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Confirm that a class belongs to a company.
     */
    public function belongsToCompany(
        int $companyId,
        int $classId
    ): bool {
        return $this->findByCompanyAndId(
            $companyId,
            $classId
        ) !== null;
    }

    /**
     * Confirm that an active class belongs to the supplied active type
     * within the same company.
     */
    public function belongsToAccountType(
        int $companyId,
        int $classId,
        int $accountTypeId
    ): bool {
        $this->validateCompanyId($companyId);

        if ($classId < 1 || $accountTypeId < 1) {
            return false;
        }

        return $this->db->fetch(
            'SELECT c.id
             FROM account_classes c
             INNER JOIN account_types t
                ON t.id = c.account_type_id
               AND t.company_id = c.company_id
             WHERE c.company_id = :company_id
               AND c.id = :class_id
               AND c.account_type_id = :account_type_id
               AND c.is_active = 1
               AND t.is_active = 1
             LIMIT 1',
            [
                'company_id' => $companyId,
                'class_id' => $classId,
                'account_type_id' => $accountTypeId,
            ]
        ) !== null;
    }

    private function validateIds(
        int $companyId,
        int $classId
    ): void {
        $this->validateCompanyId($companyId);

        if ($classId < 1) {
            throw new InvalidArgumentException(
                'Account class ID must be a positive integer.'
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