<?php
declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use InvalidArgumentException;

final class JournalEntryRequest
{
    private const ALLOWED_FIELDS = [
        'farm_id',
        'journal_date',
        'transaction_code',
        'source_module',
        'source_table',
        'source_id',
        'reference_no',
        'narration',
        'lines',
    ];

    private const ALLOWED_LINE_FIELDS = [
        'account_id',
        'entry_type',
        'amount',
        'description',
    ];

    /**
     * Validate and normalize a journal header and its lines.
     *
     * Company, farm, period, account ownership, account status and
     * posting permissions must also be checked by the service layer.
     *
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unsupported journal field(s): ' . implode(', ', $unknown)
            );
        }

        foreach ([
            'farm_id',
            'journal_date',
            'transaction_code',
            'source_module',
            'narration',
            'lines',
        ] as $field) {
            if (!array_key_exists($field, $input)) {
                throw new InvalidArgumentException(
                    "Missing required field: {$field}"
                );
            }
        }

        $farmId = $this->positiveInteger($input['farm_id'], 'farm_id');
        $journalDate = $this->validateDate($input['journal_date']);

        $transactionCode = $this->requiredString(
            $input['transaction_code'],
            'transaction_code',
            50
        );

        $sourceModule = $this->requiredString(
            $input['source_module'],
            'source_module',
            50
        );

        $narration = $this->requiredString(
            $input['narration'],
            'narration',
            65535
        );

        $sourceTable = $this->optionalString(
            $input['source_table'] ?? null,
            'source_table',
            100
        );

        $referenceNo = $this->optionalString(
            $input['reference_no'] ?? null,
            'reference_no',
            100
        );

        $sourceId = null;
        if (array_key_exists('source_id', $input) && $input['source_id'] !== null) {
            $sourceId = $this->positiveInteger(
                $input['source_id'],
                'source_id'
            );
        }

        if (!is_array($input['lines']) || !array_is_list($input['lines'])) {
            throw new InvalidArgumentException(
                'Journal lines must be a list of line objects.'
            );
        }

        if (count($input['lines']) < 2) {
            throw new InvalidArgumentException(
                'A journal must contain at least two lines.'
            );
        }

        $lines = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($input['lines'] as $index => $line) {
            $lineNumber = $index + 1;

            if (!is_array($line)) {
                throw new InvalidArgumentException(
                    "Journal line {$lineNumber} must be an array."
                );
            }

            $unknownLineFields = array_diff(
                array_keys($line),
                self::ALLOWED_LINE_FIELDS
            );

            if ($unknownLineFields !== []) {
                throw new InvalidArgumentException(
                    "Unsupported field(s) in journal line {$lineNumber}: "
                    . implode(', ', $unknownLineFields)
                );
            }

            foreach (['account_id', 'entry_type', 'amount'] as $field) {
                if (!array_key_exists($field, $line)) {
                    throw new InvalidArgumentException(
                        "Missing {$field} in journal line {$lineNumber}."
                    );
                }
            }

            $accountId = $this->positiveInteger(
                $line['account_id'],
                "lines.{$lineNumber}.account_id"
            );

            if (!is_string($line['entry_type'])) {
                throw new InvalidArgumentException(
                    "Entry type in journal line {$lineNumber} must be DEBIT or CREDIT."
                );
            }

            $entryType = strtoupper(trim($line['entry_type']));

            if (!in_array($entryType, ['DEBIT', 'CREDIT'], true)) {
                throw new InvalidArgumentException(
                    "Entry type in journal line {$lineNumber} must be DEBIT or CREDIT."
                );
            }

            $amountCents = $this->amountToCents(
                $line['amount'],
                "lines.{$lineNumber}.amount"
            );

            if ($entryType === 'DEBIT') {
                $totalDebit += $amountCents;
            } else {
                $totalCredit += $amountCents;
            }

            $lines[] = [
                'account_id' => $accountId,
                'entry_type' => $entryType,
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'description' => $this->optionalString(
                    $line['description'] ?? null,
                    "lines.{$lineNumber}.description",
                    255
                ),
            ];
        }

        if ($totalDebit !== $totalCredit) {
            throw new InvalidArgumentException(
                'Journal is not balanced: total debits must equal total credits.'
            );
        }

        return [
            'farm_id' => $farmId,
            'journal_date' => $journalDate,
            'transaction_code' => $transactionCode,
            'source_module' => $sourceModule,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'reference_no' => $referenceNo,
            'narration' => $narration,
            'lines' => $lines,
            'total_debit' => number_format($totalDebit / 100, 2, '.', ''),
            'total_credit' => number_format($totalCredit / 100, 2, '.', ''),
        ];
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if (
            !(is_int($value) || (is_string($value) && ctype_digit($value)))
            || (int) $value < 1
        ) {
            throw new InvalidArgumentException(
                "Field {$field} must be a positive integer."
            );
        }

        return (int) $value;
    }

    private function requiredString(
        mixed $value,
        string $field,
        int $maxLength
    ): string {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                "Field {$field} must be a non-empty string."
            );
        }

        $value = trim($value);

        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException(
                "Field {$field} exceeds {$maxLength} bytes."
            );
        }

        return $value;
    }

    private function optionalString(
        mixed $value,
        string $field,
        int $maxLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException(
                "Field {$field} must be a string or null."
            );
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException(
                "Field {$field} exceeds {$maxLength} bytes."
            );
        }

        return $value;
    }

    private function validateDate(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                'Journal date must be a valid YYYY-MM-DD date.'
            );
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException(
                'Journal date must be a valid YYYY-MM-DD date.'
            );
        }

        return $date->format('Y-m-d');
    }

    private function amountToCents(mixed $value, string $field): int
    {
        if (
            !(is_string($value) || is_int($value) || is_float($value))
            || !is_numeric($value)
        ) {
            throw new InvalidArgumentException(
                "Field {$field} must be a positive amount with at most two decimal places."
            );
        }

        $amount = (string) $value;

        if (!preg_match('/^(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?$/', $amount)) {
            throw new InvalidArgumentException(
                "Field {$field} must be a positive amount with at most two decimal places."
            );
        }

        $parts = explode('.', $amount, 2);
        $whole = (int) $parts[0];
        $fraction = isset($parts[1])
            ? (int) str_pad($parts[1], 2, '0')
            : 0;

        $cents = ($whole * 100) + $fraction;

        if ($cents < 1) {
            throw new InvalidArgumentException(
                "Field {$field} must be greater than zero."
            );
        }

        return $cents;
    }
}