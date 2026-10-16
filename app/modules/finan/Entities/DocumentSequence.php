<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class DocumentSequence extends BaseEntity
{
    public int $company_id;

    public int $document_type_id;

    public int $financial_year;

    public string $prefix = '';

    public int $last_number = 0;

    public int $number_length = 6;

    public string $separator = '-';

    public bool $reset_annually = true;

    public bool $is_active = true;
}