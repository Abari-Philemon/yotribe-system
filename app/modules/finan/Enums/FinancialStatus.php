<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum FinancialStatus: string
{
    case Open = 'OPEN';
    case Closed = 'CLOSED';
    case Locked = 'LOCKED';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isLocked(): bool
    {
        return $this === self::Locked;
    }
}