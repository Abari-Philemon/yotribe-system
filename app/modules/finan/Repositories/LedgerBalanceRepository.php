<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\LedgerBalanceRepositoryInterface;
use App\Modules\Finance\Entities\LedgerBalance;

final class LedgerBalanceRepository extends BaseFinanceRepository implements LedgerBalanceRepositoryInterface
{
    protected string $table = 'ledger_balances';

    protected string $entityClass = LedgerBalance::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?LedgerBalance
    {
        /** @var LedgerBalance|null */
        return $this->getEntityById($id);
    }

    public function getAll(array $filters = []): array
    {
        /** @var LedgerBalance[] */
        return $this->getEntities($filters);
    }

    public function findByAccount(
        int $financialYearId,
        int $financialPeriodId,
        int $accountId
    ): ?LedgerBalance {
        return $this->hydrate(
            $this->findOne([
                'financial_year_id'   => $financialYearId,
                'financial_period_id' => $financialPeriodId,
                'account_id'          => $accountId,
            ])
        );
    }

    public function findByFinancialPeriod(
        int $financialYearId,
        int $financialPeriodId
    ): array {
        return $this->hydrateCollection(
            $this->find([
                'financial_year_id'   => $financialYearId,
                'financial_period_id' => $financialPeriodId,
            ])
        );
    }

    public function insert(LedgerBalance $ledgerBalance): LedgerBalance
    {
        /** @var LedgerBalance */
        return $this->insertEntity($ledgerBalance);
    }

    public function save(LedgerBalance $ledgerBalance): LedgerBalance
    {
        /** @var LedgerBalance */
        return $this->saveEntity($ledgerBalance);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}