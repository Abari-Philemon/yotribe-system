<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\FinanceSettingsRepositoryInterface;
use App\Modules\Finance\Entities\FinanceSettings;

final class FinanceSettingsRepository extends BaseFinanceRepository implements FinanceSettingsRepositoryInterface
{
    protected string $table = 'finance_settings';

    protected string $entityClass = FinanceSettings::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?FinanceSettings
    {
        /** @var FinanceSettings|null */
        return $this->getEntityById($id);
    }

    public function getByCompany(int $companyId): ?FinanceSettings
    {
        /** @var FinanceSettings|null */
        return $this->hydrate(
            $this->findOne([
                'company_id' => $companyId,
            ])
        );
    }

    public function insert(FinanceSettings $settings): FinanceSettings
    {
        /** @var FinanceSettings */
        return $this->insertEntity($settings);
    }

    public function save(FinanceSettings $settings): FinanceSettings
    {
        /** @var FinanceSettings */
        return $this->saveEntity($settings);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}