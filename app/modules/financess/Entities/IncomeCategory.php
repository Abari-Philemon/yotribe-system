<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class IncomeCategory extends BaseEntity
{
    public int $company_id;

    public string $category_code = '';

    public string $category_name = '';

    public int $ledger_account_id;

    public ?string $description = null;

    public bool $is_system = false;

    public bool $is_active = true;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}