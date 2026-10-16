<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;
use App\Modules\Finance\Enums\EntryType;

final class CashBook extends BaseEntity
{
    public int $company_id;

    public int $journal_entry_id;

    public int $account_id;

    public string $transaction_date = '';

    public string $reference_number = '';

    public ?string $description = null;

    public EntryType $entry_type;

    public float $amount = 0.00;

    public float $running_balance = 0.00;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}