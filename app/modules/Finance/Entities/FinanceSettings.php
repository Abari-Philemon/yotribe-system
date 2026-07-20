<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class FinanceSettings extends BaseEntity
{
    public int $company_id;

    public string $default_currency = 'NGN';

    public string $currency_symbol = '₦';

    public int $decimal_places = 2;

    public int $financial_year_start_month = 1;

    public int $financial_year_start_day = 1;

    public float $default_tax_rate = 0.00;

    public bool $allow_backdated_entries = false;

    public bool $allow_negative_cash = false;

    public bool $auto_post_journals = true;

    public ?int $default_cash_account_id = null;

    public ?int $default_bank_account_id = null;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}