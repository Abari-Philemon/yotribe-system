<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\AccountClassRepositoryInterface;
use App\Modules\Finance\Entities\AccountClass;

final class AccountClassService extends BaseFinanceService
{
    public function __construct(
        private readonly AccountClassRepositoryInterface $accountClassRepository
    ) {
    }

    public function getById(int|string $id): ?AccountClass
    {
        return $this->accountClassRepository->getById($id);
    }

    /**
     * @param array<string,mixed> $filters
     * @return AccountClass[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->accountClassRepository->getAll($filters);
    }

    /**
     * @return AccountClass[]
     */
    public function getActive(): array
    {
        return $this->accountClassRepository->getActive();
    }

    /**
     * @return AccountClass[]
     */
    public function findByAccountType(int $accountTypeId): array
    {
        return $this->accountClassRepository->findByAccountType($accountTypeId);
    }

    public function findByCode(string $classCode): ?AccountClass
    {
        return $this->accountClassRepository->findByCode($classCode);
    }

    public function create(AccountClass $accountClass): AccountClass
    {
        return $this->accountClassRepository->insert($accountClass);
    }

    public function save(AccountClass $accountClass): AccountClass
    {
        return $this->accountClassRepository->save($accountClass);
    }

    public function remove(int|string $id): bool
    {
        return $this->accountClassRepository->remove($id);
    }

    public function isSystem(AccountClass $accountClass): bool
    {
        return $accountClass->is_system;
    }

    public function isActive(AccountClass $accountClass): bool
    {
        return $accountClass->is_active;
    }

    public function canDelete(AccountClass $accountClass): bool
    {
        return !$accountClass->is_system;
    }

    public function getDisplayName(AccountClass $accountClass): string
    {
        return sprintf(
            '%s - %s',
            $accountClass->class_code,
            $accountClass->class_name
        );
    }
}