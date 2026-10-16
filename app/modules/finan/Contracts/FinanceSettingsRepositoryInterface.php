<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\FinanceSettings;

interface FinanceSettingsRepositoryInterface
{
    public function getById(int|string $id): ?FinanceSettings;

    public function getByCompany(int $companyId): ?FinanceSettings;

    public function insert(FinanceSettings $settings): FinanceSettings;

    public function save(FinanceSettings $settings): FinanceSettings;

    public function remove(int|string $id): bool;
}