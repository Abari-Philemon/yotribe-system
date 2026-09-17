<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\AccountClassRepositoryInterface;
use App\Modules\Finance\Entities\AccountClass;

final class AccountClassRepository extends BaseFinanceRepository implements AccountClassRepositoryInterface
{
    protected string $table = 'account_classes';

    protected string $entityClass = AccountClass::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?AccountClass
    {
        /** @var AccountClass|null */
        return $this->getEntityById($id);
    }

    public function getAll(array $filters = []): array
    {
        /** @var AccountClass[] */
        return $this->getEntities($filters);
    }

    public function getActive(): array
    {
        /** @var AccountClass[] */
        return $this->hydrateCollection(
            $this->find([
                'is_active' => true,
            ])
        );
    }

    public function findByAccountType(int $accountTypeId): array
    {
        /** @var AccountClass[] */
        return $this->hydrateCollection(
            $this->find([
                'account_type_id' => $accountTypeId,
            ])
        );
    }

    public function findByCode(string $classCode): ?AccountClass
    {
        /** @var AccountClass|null */
        return $this->hydrate(
            $this->findOne([
                'class_code' => $classCode,
            ])
        );
    }

    public function insert(AccountClass $accountClass): AccountClass
    {
        /** @var AccountClass */
        return $this->insertEntity($accountClass);
    }

    public function save(AccountClass $accountClass): AccountClass
    {
        /** @var AccountClass */
        return $this->saveEntity($accountClass);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}