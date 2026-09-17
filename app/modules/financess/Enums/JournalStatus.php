<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum JournalStatus: string
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case Reversed = 'REVERSED';
    case Cancelled = 'CANCELLED';

    public function canEdit(): bool
    {
        return $this === self::Draft;
    }

    public function canPost(): bool
    {
        return $this === self::Draft;
    }

    public function canReverse(): bool
    {
        return $this === self::Posted;
    }
}