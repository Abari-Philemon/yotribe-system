<?php

declare(strict_types=1);

namespace App\Modules\Finance\Engine;

use App\Modules\Finance\Entities\DocumentSequence;
use App\Modules\Finance\Services\DocumentSequenceService;

final class DocumentNumberGenerator
{
    public function __construct(
        private readonly DocumentSequenceService $sequenceService
    ) {
    }

    public function generate(DocumentSequence $sequence): string
    {
        return sprintf(
            '%s%s%s',
            $sequence->prefix,
            $sequence->separator,
            str_pad(
                (string) ($sequence->last_number + 1),
                $sequence->number_length,
                '0',
                STR_PAD_LEFT
            )
        );
    }

    public function nextNumber(DocumentSequence $sequence): int
    {
        return $sequence->last_number + 1;
    }

    public function reserve(DocumentSequence $sequence): string
    {
        $documentNumber = $this->generate($sequence);

        $this->sequenceService->increment($sequence);

        return $documentNumber;
    }

    public function resetIfRequired(
        DocumentSequence $sequence,
        int $financialYear
    ): DocumentSequence {
        if (
            $sequence->reset_annually &&
            $sequence->financial_year !== $financialYear
        ) {
            $sequence->financial_year = $financialYear;
            $sequence->last_number = 0;

            return $this->sequenceService->save($sequence);
        }

        return $sequence;
    }
}