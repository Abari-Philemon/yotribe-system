<?php
declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use InvalidArgumentException;

final class FinancialYearRequest
{
    private const ALLOWED_FIELDS = [
        'year_code',
        'year_name',
        'start_date',
        'end_date',
    ];

    /**
     * @return array{
     *     year_code:string,
     *     year_name:string,
     *     start_date:string,
     *     end_date:string
     * }
     */
    public function validate(array $input): array
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unsupported financial-year field(s): '
                . implode(', ', $unknown)
            );
        }

        foreach (self::ALLOWED_FIELDS as $field) {
            if (!array_key_exists($field, $input)) {
                throw new InvalidArgumentException(
                    "Missing required field: {$field}"
                );
            }

            if (!is_string($input[$field]) || trim($input[$field]) === '') {
                throw new InvalidArgumentException(
                    "Field {$field} must be a non-empty string."
                );
            }
        }

        $yearCode = trim($input['year_code']);
        $yearName = trim($input['year_name']);
        $startDate = trim($input['start_date']);
        $endDate = trim($input['end_date']);

        if (
            strlen($yearCode) > 20
            || !preg_match('/^[A-Za-z0-9_-]+$/', $yearCode)
        ) {
            throw new InvalidArgumentException(
                'Year code must contain only letters, numbers, underscores, or hyphens and be at most 20 characters.'
            );
        }

        if (strlen($yearName) > 100) {
            throw new InvalidArgumentException(
                'Year name must not exceed 100 characters.'
            );
        }

        $start = $this->parseDate($startDate, 'start_date');
        $end = $this->parseDate($endDate, 'end_date');

        if ($start > $end) {
            throw new InvalidArgumentException(
                'Financial-year start date must not be after its end date.'
            );
        }

        if (
            $start->format('m-d') !== '01-01'
            || $end->format('m-d') !== '12-31'
            || $start->format('Y') !== $end->format('Y')
        ) {
            throw new InvalidArgumentException(
                'A financial year must run from January 1 to December 31 of the same calendar year.'
            );
        }

        return [
            'year_code' => $yearCode,
            'year_name' => $yearName,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
        ];
    }

    private function parseDate(string $value, string $field): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException(
                "Field {$field} must be a valid date in YYYY-MM-DD format."
            );
        }

        return $date;
    }
}