<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\IncomeCategoryRepositoryInterface;
use App\Modules\Finance\Entities\IncomeCategory;

final class IncomeCategoryRepository extends BaseFinanceRepository implements IncomeCategoryRepositoryInterface
{
    protected string $table = 'income_categories';

    protected string $entityClass = IncomeCategory::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?IncomeCategory
    {
        /** @var IncomeCategory|null */
        return $this->getEntityById($id);
    }

    public function getAll(array $filters = []): array
    {
        /** @var IncomeCategory[] */
        return $this->getEntities($filters);
    }

    public function getActive(): array
    {
        /** @var IncomeCategory[] */
        return $this->hydrateCollection(
            $this->find([
                'is_active' => true,
            ])
        );
    }

    public function findByCode(string $categoryCode): ?IncomeCategory
    {
        /** @var IncomeCategory|null */
        return $this->hydrate(
            $this->findOne([
                'category_code' => $categoryCode,
            ])
        );
    }

    public function insert(IncomeCategory $incomeCategory): IncomeCategory
    {
        /** @var IncomeCategory */
        return $this->insertEntity($incomeCategory);
    }

    public function save(IncomeCategory $incomeCategory): IncomeCategory
    {
        /** @var IncomeCategory */
        return $this->saveEntity($incomeCategory);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}