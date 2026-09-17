<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\FinanceAuditLogRepositoryInterface;
use App\Modules\Finance\Entities\FinanceAuditLog;

final class FinanceAuditLogRepository extends BaseFinanceRepository implements FinanceAuditLogRepositoryInterface
{
    protected string $table = 'finance_audit_logs';

    protected string $entityClass = FinanceAuditLog::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?FinanceAuditLog
    {
        /** @var FinanceAuditLog|null */
        return $this->getEntityById($id);
    }

    public function getAll(array $filters = []): array
    {
        /** @var FinanceAuditLog[] */
        return $this->getEntities($filters);
    }

    public function findByModule(string $module): array
    {
        /** @var FinanceAuditLog[] */
        return $this->hydrateCollection(
            $this->find([
                'module' => $module,
            ])
        );
    }

    public function findByEntity(string $entityName, string $entityId): array
    {
        /** @var FinanceAuditLog[] */
        return $this->hydrateCollection(
            $this->find([
                'entity_name' => $entityName,
                'entity_id'   => $entityId,
            ])
        );
    }

    public function findByUser(int $performedBy): array
    {
        /** @var FinanceAuditLog[] */
        return $this->hydrateCollection(
            $this->find([
                'performed_by' => $performedBy,
            ])
        );
    }

    public function insert(FinanceAuditLog $auditLog): FinanceAuditLog
    {
        /** @var FinanceAuditLog */
        return $this->insertEntity($auditLog);
    }

    public function save(FinanceAuditLog $auditLog): FinanceAuditLog
    {
        /** @var FinanceAuditLog */
        return $this->saveEntity($auditLog);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}