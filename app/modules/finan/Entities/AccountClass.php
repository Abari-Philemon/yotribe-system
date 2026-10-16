<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class AccountClass extends BaseEntity
{
    public int $company_id;

    public int $account_type_id;

    public string $class_code = '';

    public string $class_name = '';

    public ?string $description = null;

    public int $display_order = 0;

    public bool $is_system = true;

    public bool $is_active = true;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}
