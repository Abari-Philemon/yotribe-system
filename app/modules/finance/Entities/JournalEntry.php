<?php
declare(strict_types=1);

namespace App\Modules\Finance\Entities;

use InvalidArgumentException;

final class JournalEntry
{
    public ?int $id = null;
    public string $uuid = '';

    public int $company_id;
    public int $farm_id;
    public int $financial_year_id;
    public int $financial_period_id;
    public int $document_sequence_id;
    public int $document_type_id;

    public string $journal_no = '';
    public string $journal_date = '';
    public string $transaction_code = '';
    public string $source_module = '';
    public ?string $source_table = null;
    public ?int $source_id = null;
    public ?string $reference_no = null;
    public string $narration = '';

    public string $status = 'DRAFT';
    public string $total_debit = '0.00';
    public string $total_credit = '0.00';

    public ?int $posted_by = null;
    public ?string $posted_at = null;
    public ?int $reversed_by = null;
    public ?string $reversed_at = null;
    public ?int $reversal_journal_id = null;

    public int $created_by;
    public ?int $updated_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isPosted(): bool
    {
        return $this->status === 'POSTED';
    }

    public function isReversed(): bool
    {
        return $this->status === 'REVERSED';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function isBalanced(): bool
    {
        if (
            !$this->validMoney($this->total_debit)
            || !$this->validMoney($this->total_credit)
        ) {
            return false;
        }

        return $this->toCents($this->total_debit)
            === $this->toCents($this->total_credit);
    }

    private function toCents(string $amount): int
    {
        $parts = explode('.', $amount, 2);
        $whole = (int) $parts[0];
        $fraction = isset($parts[1])
            ? (int) str_pad($parts[1], 2, '0')
            : 0;

        return ($whole * 100) + $fraction;
    }

    public function validateIdentity(): void
    {
        if (
            $this->company_id < 1
            || $this->farm_id < 1
            || $this->financial_year_id < 1
            || $this->financial_period_id < 1
            || $this->document_sequence_id < 1
            || $this->document_type_id < 1
            || $this->created_by < 1
        ) {
            throw new InvalidArgumentException(
                'Journal company, farm, year, period, document, and creator IDs must be positive.'
            );
        }
    }

    private function validMoney(string $amount): bool
    {
        return preg_match(
            '/^(?:0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?$/',
            $amount
        ) === 1;
    }
}