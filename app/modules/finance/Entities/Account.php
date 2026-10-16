<?php
declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class Account extends BaseEntity
{
    public int $company_id;
    public int $account_type_id;
    public int $account_class_id;
    public ?int $parent_account_id = null;

    public string $account_code;
    public string $system_key;
    public string $account_name;
    public ?string $short_name = null;
    public ?string $description = null;

    public string $normal_balance;
    public int $account_level = 1;
    public ?string $account_path = null;

    public string $opening_balance = '0.00';
    public string $current_balance = '0.00';

    public bool $allow_posting = true;
    public bool $is_control_account = false;
    public bool $is_cash_account = false;
    public bool $is_bank_account = false;
    public bool $is_system = false;
    public bool $is_active = true;

    public ?int $created_by = null;
    public ?int $updated_by = null;
}