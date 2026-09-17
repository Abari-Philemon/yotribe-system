<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\FinanceSettingsRepositoryInterface;
use App\Modules\Finance\Entities\FinanceSettings;

final class FinanceSettingsService extends BaseFinanceService
{
    public function __construct(
        private readonly FinanceSettingsRepositoryInterface $settingsRepository
    ) {
    }

    public function getById(int|string $id): ?FinanceSettings
    {
        return $this->settingsRepository->getById($id);
    }

    public function getByCompany(int $companyId): ?FinanceSettings
    {
        return $this->settingsRepository->getByCompany($companyId);
    }

    public function create(FinanceSettings $settings): FinanceSettings
    {
        return $this->settingsRepository->insert($settings);
    }

    public function save(FinanceSettings $settings): FinanceSettings
    {
        return $this->settingsRepository->save($settings);
    }

    public function remove(int|string $id): bool
    {
        return $this->settingsRepository->remove($id);
    }

    public function allowsBackdatedEntries(FinanceSettings $settings): bool
    {
        return $settings->allow_backdated_entries;
    }

    public function allowsNegativeCash(FinanceSettings $settings): bool
    {
        return $settings->allow_negative_cash;
    }

    public function autoPostsJournals(FinanceSettings $settings): bool
    {
        return $settings->auto_post_journals;
    }

    public function getDefaultCurrency(FinanceSettings $settings): string
    {
        return $settings->default_currency;
    }

    public function getCurrencySymbol(FinanceSettings $settings): string
    {
        return $settings->currency_symbol;
    }

    public function getDecimalPlaces(FinanceSettings $settings): int
    {
        return $settings->decimal_places;
    }

    public function getDefaultCashAccountId(FinanceSettings $settings): ?int
    {
        return $settings->default_cash_account_id;
    }

    public function getDefaultBankAccountId(FinanceSettings $settings): ?int
    {
        return $settings->default_bank_account_id;
    }

    public function getDefaultTaxRate(FinanceSettings $settings): float
    {
        return $settings->default_tax_rate;
    }

    public function getFinancialYearStartMonth(FinanceSettings $settings): int
    {
        return $settings->financial_year_start_month;
    }

    public function getFinancialYearStartDay(FinanceSettings $settings): int
    {
        return $settings->financial_year_start_day;
    }
}