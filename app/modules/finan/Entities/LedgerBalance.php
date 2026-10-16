<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class LedgerBalance extends BaseEntity
{
    public int $company_id;

    public int $financial_year_id;

    public int $financial_period_id;

    public int $account_id;

    public float $opening_debit = 0.00;

    public float $opening_credit = 0.00;

    public float $period_debit = 0.00;

    public float $period_credit = 0.00;

    public float $closing_debit = 0.00;

    public float $closing_credit = 0.00;
}