<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;
use App\Modules\Finance\Enums\BankTransactionType;

final class BankTransaction extends BaseEntity
{
    public int $company_id;

    public int $bank_account_id;

    public ?int $journal_entry_id = null;

    public BankTransactionType $transaction_type;

    public string $transaction_date = '';

    public string $reference_number = '';

    public ?string $description = null;

    public float $amount = 0.00;

    public float $running_balance = 0.00;

    public bool $is_reconciled = false;

    public ?string $reconciled_at = null;

    public ?int $reconciled_by = null;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}