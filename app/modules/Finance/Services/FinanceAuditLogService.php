<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\FinanceAuditLogRepositoryInterface;
use App\Modules\Finance\Entities\FinanceAuditLog;

final class FinanceAuditLogService extends BaseFinanceService
{
    public function __construct(
        private readonly FinanceAuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    public function getById(int|string $id): ?FinanceAuditLog
    {
        return $this->auditLogRepository->getById($id);
    }

    /**
     * @param array<string,mixed> $filters
     * @return FinanceAuditLog[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->auditLogRepository->getAll($filters);
    }

    /**
     * @return FinanceAuditLog[]
     */
    public function findByModule(string $module): array
    {
        return $this->auditLogRepository->findByModule($module);
    }

    /**
     * @return FinanceAuditLog[]
     */
    public function findByEntity(string $entityName, string $entityId): array
    {
        return $this->auditLogRepository->findByEntity(
            $entityName,
            $entityId
        );
    }

    /**
     * @return FinanceAuditLog[]
     */
    public function findByUser(int $performedBy): array
    {
        return $this->auditLogRepository->findByUser($performedBy);
    }

    public function create(FinanceAuditLog $auditLog): FinanceAuditLog
    {
        return $this->auditLogRepository->insert($auditLog);
    }

    public function save(FinanceAuditLog $auditLog): FinanceAuditLog
    {
        return $this->auditLogRepository->save($auditLog);
    }

    public function remove(int|string $id): bool
    {
        return $this->auditLogRepository->remove($id);
    }

    public function hasChanges(FinanceAuditLog $auditLog): bool
    {
        return $auditLog->old_values !== $auditLog->new_values;
    }

    public function isCreateAction(FinanceAuditLog $auditLog): bool
    {
        return strtoupper($auditLog->action) === 'CREATE';
    }

    public function isUpdateAction(FinanceAuditLog $auditLog): bool
    {
        return strtoupper($auditLog->action) === 'UPDATE';
    }

    public function isDeleteAction(FinanceAuditLog $auditLog): bool
    {
        return strtoupper($auditLog->action) === 'DELETE';
    }
}