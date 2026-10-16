<?php

declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use App\Core\BaseEntity;
use App\Modules\Finance\Enums\EntryType;

final class JournalEntryLine extends BaseEntity
{
    public int $journal_entry_id;

    public int $account_id;

    public EntryType $entry_type;

    public float $amount = 0.00;

    public ?string $description = null;

    public int $line_number = 1;
}