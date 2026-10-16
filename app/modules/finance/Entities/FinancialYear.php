<?php
declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class FinancialYear extends BaseEntity
{
    public int $company_id;

    public string $year_code;
    public string $year_name;

    public string $start_date;
    public string $end_date;

    public string $status = 'OPEN';

    public bool $is_current = false;

    public ?int $closed_by = null;
    public ?string $closed_at = null;

    public ?int $created_by = null;
    public ?int $updated_by = null;
}