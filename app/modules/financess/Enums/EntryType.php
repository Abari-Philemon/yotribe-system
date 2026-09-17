<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum EntryType: string
{
    case Debit = 'DEBIT';
    case Credit = 'CREDIT';

    public function isDebit(): bool
    {
        return $this === self::Debit;
    }

    public function isCredit(): bool
    {
        return $this === self::Credit;
    }
}