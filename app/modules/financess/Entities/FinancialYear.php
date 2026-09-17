<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;
use App\Modules\Finance\Enums\FinancialStatus;

final class FinancialYear extends BaseEntity
{
    public int $company_id;

    public string $year_code = '';

    public string $year_name = '';

    public string $start_date = '';

    public string $end_date = '';

    public FinancialStatus $status;

    public bool $is_current = false;

    public ?string $closed_at = null;

    public ?int $closed_by = null;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}