<?php
declare(strict_types=1);

namespace App\Modules\Finance\Requests;

final class AccountRequest
{
    /** @return array<string, mixed> */
    public static function validate(array $input): array
    {
        $errors = [];
        $code = strtoupper(trim((string)($input['account_code'] ?? '')));
        $name = trim((string)($input['account_name'] ?? ''));
        $typeId = filter_var($input['account_type_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $classId = filter_var($input['account_class_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $parent = trim((string)($input['parent_account_id'] ?? ''));
        $opening = trim((string)($input['opening_balance'] ?? '0'));
        $shortName = trim((string)($input['short_name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));

        if ($code === '' || !preg_match('/^[A-Z0-9][A-Z0-9._-]{0,29}$/', $code)) {
            $errors['account_code'] = 'Enter an account code of up to 30 characters using letters, numbers, dots, underscores or hyphens.';
        }
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['account_name'] = 'Account name is required and must not exceed 150 characters.';
        }
        if ($typeId === false) {
            $errors['account_type_id'] = 'Select a valid account type.';
        }
        if ($classId === false) {
            $errors['account_class_id'] = 'Select a valid account class.';
        }
        if ($parent !== '' && filter_var($parent, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors['parent_account_id'] = 'Select a valid parent account.';
        }
        if (!is_numeric($opening) || !is_finite((float)$opening) || abs((float)$opening) > 9999999999999999.99) {
            $errors['opening_balance'] = 'Enter a valid opening balance.';
        }
        if (mb_strlen($shortName) > 50) {
            $errors['short_name'] = 'Short name must not exceed 50 characters.';
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        return [
            'errors' => [],
            'data' => [
                'account_code' => $code,
                'account_name' => $name,
                'account_type_id' => (int)$typeId,
                'account_class_id' => (int)$classId,
                'parent_account_id' => $parent === '' ? null : (int)$parent,
                'opening_balance' => round((float)$opening, 2),
                'short_name' => $shortName === '' ? null : $shortName,
                'description' => $description === '' ? null : $description,
                'allow_posting' => isset($input['allow_posting']) ? 1 : 0,
                'is_cash_account' => isset($input['is_cash_account']) ? 1 : 0,
                'is_bank_account' => isset($input['is_bank_account']) ? 1 : 0,
            ],
        ];
    }
}
