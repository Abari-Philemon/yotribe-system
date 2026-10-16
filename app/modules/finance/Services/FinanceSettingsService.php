<?php
declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Core\Service;
use App\Modules\Finance\Repositories\FinanceSettingsRepository;
use InvalidArgumentException;

final class FinanceSettingsService extends Service
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

    public function __construct(
        private readonly FinanceSettingsRepository $settingsRepository
    ) {
        parent::__construct($settingsRepository);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByCompanyId(int $companyId): ?array
    {
        $this->validateCompanyId($companyId);

        return $this->settingsRepository->findByCompanyId($companyId);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createForCompany(
        int $companyId,
        array $data,
        ?int $actorId = null
    ): int {
        $this->validateCompanyId($companyId);
        $this->validateActorId($actorId);

        if ($this->settingsRepository->findByCompanyId($companyId) !== null) {
            throw new InvalidArgumentException(
                'Finance settings already exist for this company.'
            );
        }

        $data = $this->filterAllowedFields($data);

        $data['uuid'] = \generateUuid();
        $data['company_id'] = $companyId;

        if ($actorId !== null) {
            $data['created_by'] = $actorId;
            $data['updated_by'] = $actorId;
        }

        return $this->settingsRepository->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateForCompany(
        int $companyId,
        array $data,
        ?int $actorId = null
    ): bool {
        $this->validateCompanyId($companyId);
        $this->validateActorId($actorId);

        $settings = $this->settingsRepository->findByCompanyId($companyId);

        if ($settings === null) {
            return false;
        }

        $data = $this->filterAllowedFields($data);

        if ($actorId !== null) {
            $data['updated_by'] = $actorId;
        }

        if ($data === []) {
            throw new InvalidArgumentException(
                'At least one valid finance setting must be supplied.'
            );
        }

        return $this->settingsRepository->update(
            (int) $settings['id'],
            $data
        );
    }

    private function validateCompanyId(int $companyId): void
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException(
                'Company ID must be a positive integer.'
            );
        }
    }

    private function validateActorId(?int $actorId): void
    {
        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException(
                'Actor ID must be a positive integer.'
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterAllowedFields(array $data): array
    {
        $unknown = array_diff(array_keys($data), self::ALLOWED_FIELDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unsupported finance setting field(s): '
                . implode(', ', $unknown)
            );
        }

        return $data;
    }
}