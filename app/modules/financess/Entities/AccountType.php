<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class AccountType extends BaseEntity
{
    public int $company_id;

    public string $type_code = '';

    public string $type_name = '';

    public string $normal_balance = '';

    public int $display_order = 0;

    public ?string $description = null;

    public bool $is_system = true;

    public bool $is_active = true;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}