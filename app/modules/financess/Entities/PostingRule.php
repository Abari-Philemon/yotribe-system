<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;

final class PostingRule extends BaseEntity
{
    public int $company_id;

    public string $transaction_type = '';

    public int $debit_account_id;

    public int $credit_account_id;

    public ?string $description = null;

    public bool $is_active = true;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}