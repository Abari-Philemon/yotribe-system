<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum NormalBalance: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function isDebit(): bool
    {
        return $this === self::Debit;
    }

    public function isCredit(): bool
    {
        return $this === self::Credit;
    }
}