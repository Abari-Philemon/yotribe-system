<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Core\Repository;

final class FinanceSettingsRepository extends Repository
{
    protected string $table = 'finance_settings';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Find finance settings belonging to a company.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyId(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }

        return $this->findOne([
            'company_id' => $companyId,
        ]);
    }
}