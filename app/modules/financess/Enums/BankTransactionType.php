<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum BankTransactionType: string
{
    case Deposit = 'DEPOSIT';
    case Withdrawal = 'WITHDRAWAL';
    case Transfer = 'TRANSFER';
    case Adjustment = 'ADJUSTMENT';
}