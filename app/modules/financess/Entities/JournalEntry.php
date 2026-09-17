<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;
use App\Modules\Finance\Enums\JournalStatus;

final class JournalEntry extends BaseEntity
{
    public int $company_id;

    public int $financial_year_id;

    public int $financial_period_id;

    public int $document_type_id;

    public int $document_sequence_id;

    public string $journal_number = '';

    public string $journal_date = '';

    public ?string $reference_number = null;

    public ?string $reference_type = null;

    public ?string $description = null;

    public float $total_debit = 0.00;

    public float $total_credit = 0.00;

    public JournalStatus $status;

    public bool $is_adjustment = false;

    public ?int $reversal_journal_id = null;

    public ?string $posted_at = null;

    public ?int $posted_by = null;

    public ?int $created_by = null;

    public ?int $updated_by = null;
}