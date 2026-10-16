<?php
declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use InvalidArgumentException;

final class AccountRequest
{
    private const ALLOWED_FIELDS = [
        'account_type_id',
        'account_class_id',
        'parent_account_id',
        'account_code',
        'system_key',
        'account_name',
        'short_name',
        'description',
        'normal_balance',
        'opening_balance',
        'allow_posting',
        'is_control_account',
        'is_cash_account',
        'is_bank_account',
        'is_active',
    ];

    /**
     * Validate and normalize account input.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $unknown = array_diff(array_keys($input), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unsupported account field(s): ' . implode(', ', $unknown)
            );
        }

        $required = [
            'account_type_id',
            'account_class_id',
            'account_code',
            'system_key',
            'account_name',
            'normal_balance',
        ];

        foreach ($required as $field) {
            if (!array_key_exists($field, $input)) {
                throw new InvalidArgumentException(
                    sprintf('%s is required.', $field)
                );
            }
        }

        $validated = [];

        foreach ($input as $field => $value) {
            switch ($field) {
                case 'account_type_id':
                case 'account_class_id':
                    $validated[$field] = $this->positiveInteger($field, $value);
                    break;

                case 'parent_account_id':
                    $validated[$field] = ($value === null || $value === '')
                        ? null
                        : $this->positiveInteger($field, $value);
                    break;

                case 'account_code':
                    $validated[$field] = $this->requiredString(
                        $field,
                        $value,
                        30
                    );
                    break;

                case 'system_key':
                    $validated[$field] = $this->requiredString(
                        $field,
                        $value,
                        100
                    );
                    break;

                case 'account_name':
                    $validated[$field] = $this->requiredString(
                        $field,
                        $value,
                        150
                    );
                    break;

                case 'short_name':
                    $validated[$field] = $this->nullableString(
                        $field,
                        $value,
                        50
                    );
                    break;

                case 'description':
                    $validated[$field] = $this->nullableString(
                        $field,
                        $value,
                        65535
                    );
                    break;

                case 'normal_balance':
                    if (!is_string($value)) {
                        throw new InvalidArgumentException(
                            'Normal balance must be debit or credit.'
                        );
                    }

                    $balance = strtolower(trim($value));

                    if (!in_array($balance, ['debit', 'credit'], true)) {
                        throw new InvalidArgumentException(
                            'Normal balance must be debit or credit.'
                        );
                    }

                    $validated[$field] = $balance;
                    break;

                case 'opening_balance':
                    $validated[$field] = $this->moneyValue(
                        'opening_balance',
                        $value
                    );
                    break;

                case 'allow_posting':
                case 'is_control_account':
                case 'is_cash_account':
                case 'is_bank_account':
                case 'is_active':
                    $validated[$field] = $this->booleanValue($field, $value);
                    break;
            }
        }

        return $validated;
    }

    /**
     * Validate a partial account update.
     *
     * Opening balances and protected system fields cannot be updated here.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validateUpdate(array $input): array
    {
        if ($input === []) {
            throw new InvalidArgumentException(
                'At least one editable account field must be supplied.'
            );
        }

        $editableFields = [
            'account_code',
            'account_name',
            'short_name',
            'description',
            'allow_posting',
            'is_control_account',
            'is_cash_account',
            'is_bank_account',
        ];

        $unsupported = array_diff(array_keys($input), $editableFields);

        if ($unsupported !== []) {
            throw new InvalidArgumentException(
                'These fields cannot be changed through ordinary account updates: '
                . implode(', ', $unsupported)
            );
        }

        $validated = [];

        foreach ($input as $field => $value) {
            switch ($field) {
                case 'account_code':
                    $validated[$field] = $this->requiredString(
                        $field,
                        $value,
                        30
                    );
                    break;

                case 'account_name':
                    $validated[$field] = $this->requiredString(
                        $field,
                        $value,
                        150
                    );
                    break;

                case 'short_name':
                    $validated[$field] = $this->nullableString(
                        $field,
                        $value,
                        50
                    );
                    break;

                case 'description':
                    $validated[$field] = $this->nullableString(
                        $field,
                        $value,
                        65535
                    );
                    break;

                case 'allow_posting':
                case 'is_control_account':
                case 'is_cash_account':
                case 'is_bank_account':
                    $validated[$field] = $this->booleanValue($field, $value);
                    break;
            }
        }

        return $validated;
    }
    private function positiveInteger(string $field, mixed $value): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        if ($integer === false || $integer < 1) {
            throw new InvalidArgumentException(
                sprintf('%s must be a positive integer.', $field)
            );
        }

        return $integer;
    }

    private function requiredString(
        string $field,
        mixed $value,
        int $maximumLength
    ): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException(
                sprintf('%s must be a string.', $field)
            );
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s must contain 1 to %d characters.',
                    $field,
                    $maximumLength
                )
            );
        }

        return $value;
    }

    private function nullableString(
        string $field,
        mixed $value,
        int $maximumLength
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || mb_strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s must be a string of at most %d characters.',
                    $field,
                    $maximumLength
                )
            );
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function moneyValue(string $field, mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException(
                sprintf('%s must be a decimal string or integer.', $field)
            );
        }

        $amount = trim((string) $value);

        if (!preg_match('/^(-?)(\d{1,16})(?:\.(\d{1,2}))?$/D', $amount, $matches)) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s must be a valid amount with up to 16 integer digits and 2 decimal places.',
                    $field
                )
            );
        }

        $sign = $matches[1];
        $whole = $matches[2];
        $fraction = str_pad($matches[3] ?? '', 2, '0');

        return $sign . $whole . '.' . $fraction;
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