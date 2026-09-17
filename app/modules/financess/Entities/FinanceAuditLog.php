<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class FinanceAuditLog extends BaseEntity
{
    public int $company_id;

    public string $module = '';

    public string $entity_name = '';

    public string $entity_id = '';

    public string $action = '';

    public ?string $old_values = null;

    public ?string $new_values = null;

    public ?string $ip_address = null;

    public ?string $user_agent = null;

    public int $performed_by;

    public string $performed_at = '';
}