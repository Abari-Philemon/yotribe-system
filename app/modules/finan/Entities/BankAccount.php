<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class BankAccount extends BaseEntity
{
    public int $company_id;

    public int $ledger_account_id;

    public string $bank_name = '';

    public string $account_name = '';

    public string $account_number = '';

    public ?string $branch_name = null;

    public ?string $swift_code = null;

    public ?string $iban = null;

    public string $currency = 'NGN';

    public bool $is_default = false;

    public bool $is_active = true;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}