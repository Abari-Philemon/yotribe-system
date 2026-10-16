<?php
declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Core\Database;
use RuntimeException;

final class CompanyContext
{
    public function __construct(
        private readonly Database $db
    ) {
    }

    /**
     * @param array<string, mixed> $session
     */
    public function resolve(array $session): int
    {
        $selectedId = $session['active_company_id']
            ?? $session['company_id']
            ?? null;

        if ($selectedId !== null && $selectedId !== '') {
            $companyId = filter_var(
                $selectedId,
                FILTER_VALIDATE_INT
            );

            if ($companyId === false || $companyId < 1) {
                throw new RuntimeException(
                    'The selected company ID is invalid.'
                );
            }

            $company = $this->db->fetch(
                'SELECT id
                 FROM companies
                 WHERE id = :id
                   AND status = :status',
                [
                    'id' => $companyId,
                    'status' => 'active',
                ]
            );

            if ($company === null) {
                throw new RuntimeException(
                    'The selected company does not exist or is inactive.'
                );
            }

            return (int) $company['id'];
        }

        $companies = $this->db->fetchAll(
            'SELECT id
             FROM companies
             WHERE status = :status
             ORDER BY id',
            ['status' => 'active']
        );

        if (count($companies) === 0) {
            throw new RuntimeException(
                'No active company is configured.'
            );
        }

        if (count($companies) > 1) {
            throw new RuntimeException(
                'An active company must be selected before continuing.'
            );
        }

        return (int) $companies[0]['id'];
    }
}