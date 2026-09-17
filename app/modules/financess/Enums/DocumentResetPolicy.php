<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

enum DocumentResetPolicy: string
{
    case Annual = 'ANNUAL';
    case Never = 'NEVER';
}