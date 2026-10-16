<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;
use App\Modules\Finance\Enums\FinancialStatus;

final class FinancialPeriod extends BaseEntity
{
    public int $company_id;

    public int $financial_year_id;

    public int $period_number;

    public string $period_name = '';

    public string $start_date = '';

    public string $end_date = '';

    public FinancialStatus $status;

    public bool $is_adjustment_period = false;

    public ?string $closed_at = null;

    public ?int $closed_by = null;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}