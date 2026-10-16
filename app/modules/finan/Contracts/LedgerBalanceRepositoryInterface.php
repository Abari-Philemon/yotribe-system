<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\LedgerBalance;

interface LedgerBalanceRepositoryInterface
{
    public function getById(int|string $id): ?LedgerBalance;

    /**
     * @param array<string,mixed> $filters
     * @return LedgerBalance[]
     */
    public function getAll(array $filters = []): array;

    public function findByAccount(
        int $financialYearId,
        int $financialPeriodId,
        int $accountId
    ): ?LedgerBalance;

    /**
     * @return LedgerBalance[]
     */
    public function findByFinancialPeriod(
        int $financialYearId,
        int $financialPeriodId
    ): array;

    public function insert(LedgerBalance $ledgerBalance): LedgerBalance;

    public function save(LedgerBalance $ledgerBalance): LedgerBalance;

    public function remove(int|string $id): bool;
}