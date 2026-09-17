<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\LedgerBalanceRepositoryInterface;
use App\Modules\Finance\Entities\LedgerBalance;

final class LedgerBalanceService extends BaseFinanceService
{
    public function __construct(
        private readonly LedgerBalanceRepositoryInterface $ledgerBalanceRepository
    ) {
    }

    public function getById(int|string $id): ?LedgerBalance
    {
        return $this->ledgerBalanceRepository->getById($id);
    }

    /**
     * @param array<string,mixed> $filters
     * @return LedgerBalance[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->ledgerBalanceRepository->getAll($filters);
    }

    public function findByAccount(
        int $financialYearId,
        int $financialPeriodId,
        int $accountId
    ): ?LedgerBalance {
        return $this->ledgerBalanceRepository->findByAccount(
            $financialYearId,
            $financialPeriodId,
            $accountId
        );
    }

    /**
     * @return LedgerBalance[]
     */
    public function findByFinancialPeriod(
        int $financialYearId,
        int $financialPeriodId
    ): array {
        return $this->ledgerBalanceRepository->findByFinancialPeriod(
            $financialYearId,
            $financialPeriodId
        );
    }

    public function create(LedgerBalance $ledgerBalance): LedgerBalance
    {
        return $this->ledgerBalanceRepository->insert($ledgerBalance);
    }

    public function save(LedgerBalance $ledgerBalance): LedgerBalance
    {
        return $this->ledgerBalanceRepository->save($ledgerBalance);
    }

    public function remove(int|string $id): bool
    {
        return $this->ledgerBalanceRepository->remove($id);
    }

    public function getNetOpeningBalance(LedgerBalance $ledgerBalance): float
    {
        return $ledgerBalance->opening_debit - $ledgerBalance->opening_credit;
    }

    public function getNetMovement(LedgerBalance $ledgerBalance): float
    {
        return $ledgerBalance->period_debit - $ledgerBalance->period_credit;
    }

    public function getNetClosingBalance(LedgerBalance $ledgerBalance): float
    {
        return $ledgerBalance->closing_debit - $ledgerBalance->closing_credit;
    }
}