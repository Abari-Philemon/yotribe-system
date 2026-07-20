<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\FinanceAuditLog;

interface FinanceAuditLogRepositoryInterface
{
    public function getById(int|string $id): ?FinanceAuditLog;

    /**
     * @param array<string,mixed> $filters
     * @return FinanceAuditLog[]
     */
    public function getAll(array $filters = []): array;

    /**
     * @return FinanceAuditLog[]
     */
    public function findByModule(string $module): array;

    /**
     * @return FinanceAuditLog[]
     */
    public function findByEntity(string $entityName, string $entityId): array;

    /**
     * @return FinanceAuditLog[]
     */
    public function findByUser(int $performedBy): array;

    public function insert(FinanceAuditLog $auditLog): FinanceAuditLog;

    public function save(FinanceAuditLog $auditLog): FinanceAuditLog;

    public function remove(int|string $id): bool;
}