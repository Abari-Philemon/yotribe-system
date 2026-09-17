<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;
use App\Modules\Finance\Enums\NormalBalance;

final class Account extends BaseEntity
{
    public int $company_id;

    public int $account_type_id;

    public int $account_class_id;

    public ?int $parent_account_id = null;

    public string $account_code = '';

    public string $system_key = '';

    public string $account_name = '';

    public ?string $short_name = null;

    public ?string $description = null;

    public NormalBalance $normal_balance;

    public bool $allow_posting = true;

    public bool $is_control_account = false;

    public bool $is_cash_account = false;

    public bool $is_bank_account = false;

    public bool $is_system = false;

    public bool $is_active = true;

    public float $opening_balance = 0.00;

    public float $current_balance = 0.00;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}