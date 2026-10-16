<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use InvalidArgumentException;

final class FinanceSettingsRequest
{
    private const ALLOWED_FIELDS = [
        'default_currency',
        'currency_symbol',
        'decimal_places',
        'financial_year_start_month',
        'financial_year_start_day',
        'default_tax_rate',
        'allow_backdated_entries',
        'allow_negative_cash',
        'auto_post_journals',
        'default_cash_account_id',
        'default_bank_account_id',
    ];

    /**
     * Validate and normalize submitted finance settings.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unsupported finance setting field(s): '
                . implode(', ', $unknown)
            );
        }

        $validated = [];

        foreach ($input as $field => $value) {
            switch ($field) {
                case 'default_currency':
                    if (!is_string($value)
                        || !preg_match('/^[A-Za-z]{3}$/', $value)) {
                        throw new InvalidArgumentException(
                            'Currency must be a three-letter currency code.'
                        );
                    }

                    $validated[$field] = strtoupper($value);
                    break;

                case 'currency_symbol':
                    if (!is_string($value)
                        || trim($value) === ''
                        || mb_strlen($value) > 10) {
                        throw new InvalidArgumentException(
                            'Currency symbol must contain 1 to 10 characters.'
                        );
                    }

                    $validated[$field] = trim($value);
                    break;

                case 'decimal_places':
                    $validated[$field] = $this->integerInRange(
                        $field,
                        $value,
                        0,
                        6
                    );
                    break;

                case 'financial_year_start_month':
                    $validated[$field] = $this->integerInRange(
                        $field,
                        $value,
                        1,
                        12
                    );
                    break;

                case 'financial_year_start_day':
                    $validated[$field] = $this->integerInRange(
                        $field,
                        $value,
                        1,
                        31
                    );
                    break;

                case 'default_tax_rate':
                    if (!is_numeric($value)) {
                        throw new InvalidArgumentException(
                            'Default tax rate must be numeric.'
                        );
                    }

                    $rate = (float) $value;

                    if (!is_finite($rate) || $rate < 0 || $rate > 100) {
                        throw new InvalidArgumentException(
                            'Default tax rate must be between 0 and 100.'
                        );
                    }

                    $validated[$field] = number_format($rate, 2, '.', '');
                    break;

                case 'allow_backdated_entries':
                case 'allow_negative_cash':
                case 'auto_post_journals':
                    $validated[$field] = $this->booleanValue(
                        $field,
                        $value
                    );
                    break;

                case 'default_cash_account_id':
                case 'default_bank_account_id':
                    if ($value === null || $value === '') {
                        $validated[$field] = null;
                        break;
                    }

                    $validated[$field] = $this->integerInRange(
                        $field,
                        $value,
                        1,
                        PHP_INT_MAX
                    );
                    break;
            }
        }

        return $validated;
    }

    private function integerInRange(
        string $field,
        mixed $value,
        int $minimum,
        int $maximum
    ): int {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < $minimum || $integer > $maximum) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s must be an integer between %d and %d.',
                    $field,
                    $minimum,
                    $maximum
                )
            );
        }

        return $integer;
    }

    private function booleanValue(string $field, mixed $value): bool
    {
        $boolean = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($boolean === null) {
            throw new InvalidArgumentException(
                sprintf('%s must be a valid Boolean value.', $field)
            );
        }

        return $boolean;
    }
}