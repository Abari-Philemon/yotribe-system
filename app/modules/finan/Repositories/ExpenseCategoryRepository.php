<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\ExpenseCategoryRepositoryInterface;
use App\Modules\Finance\Entities\ExpenseCategory;

final class ExpenseCategoryRepository extends BaseFinanceRepository implements ExpenseCategoryRepositoryInterface
{
    protected string $table = 'expense_categories';

    protected string $entityClass = ExpenseCategory::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?ExpenseCategory
    {
        /** @var ExpenseCategory|null */
        return $this->getEntityById($id);
    }

    public function getAll(array $filters = []): array
    {
        /** @var ExpenseCategory[] */
        return $this->getEntities($filters);
    }

    public function getActive(): array
    {
        /** @var ExpenseCategory[] */
        return $this->hydrateCollection(
            $this->find([
                'is_active' => true,
            ])
        );
    }

    public function findByCode(string $categoryCode): ?ExpenseCategory
    {
        /** @var ExpenseCategory|null */
        return $this->hydrate(
            $this->findOne([
                'category_code' => $categoryCode,
            ])
        );
    }

    public function insert(ExpenseCategory $expenseCategory): ExpenseCategory
    {
        /** @var ExpenseCategory */
        return $this->insertEntity($expenseCategory);
    }

    public function save(ExpenseCategory $expenseCategory): ExpenseCategory
    {
        /** @var ExpenseCategory */
        return $this->saveEntity($expenseCategory);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}