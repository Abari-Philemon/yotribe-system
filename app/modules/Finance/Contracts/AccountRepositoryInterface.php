<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\Account;

interface AccountRepositoryInterface
{
    public function getById(int|string $id): ?Account;

    /**
     * @return Account[]
     */
    public function getAll(): array;

    /**
     * @return Account[]
     */
    public function getActive(): array;

    public function findByCode(string $accountCode): ?Account;

    public function findBySystemKey(string $systemKey): ?Account;

    public function insert(Account $account): Account;

    public function save(Account $account): Account;

    public function remove(int|string $id): bool;
}