<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class DocumentType extends BaseEntity
{
    public int $company_id;

    public string $code = '';

    public string $name = '';

    public string $prefix = '';

    public ?string $description = null;

    public bool $is_system = true;

    public bool $is_active = true;
}