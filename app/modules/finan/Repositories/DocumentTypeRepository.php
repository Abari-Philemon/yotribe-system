<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\DocumentTypeRepositoryInterface;
use App\Modules\Finance\Entities\DocumentType;

final class DocumentTypeRepository extends BaseFinanceRepository implements DocumentTypeRepositoryInterface
{
    protected string $table = 'document_types';

    protected string $entityClass = DocumentType::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?DocumentType
    {
        /** @var DocumentType|null */
        return $this->getEntityById($id);
    }

    public function getAll(array $filters = []): array
    {
        /** @var DocumentType[] */
        return $this->getEntities($filters);
    }

    public function getActive(): array
    {
        /** @var DocumentType[] */
        return $this->hydrateCollection(
            $this->find([
                'is_active' => true,
            ])
        );
    }

    public function findByCode(string $code): ?DocumentType
    {
        /** @var DocumentType|null */
        return $this->hydrate(
            $this->findOne([
                'code' => $code,
            ])
        );
    }

    public function insert(DocumentType $documentType): DocumentType
    {
        /** @var DocumentType */
        return $this->insertEntity($documentType);
    }

    public function save(DocumentType $documentType): DocumentType
    {
        /** @var DocumentType */
        return $this->saveEntity($documentType);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}