<?php

declare(strict_types=1);

namespace App\Modules\Finance\Repositories;

use App\Core\Database;
use App\Modules\Finance\Contracts\DocumentSequenceRepositoryInterface;
use App\Modules\Finance\Entities\DocumentSequence;

final class DocumentSequenceRepository extends BaseFinanceRepository implements DocumentSequenceRepositoryInterface
{
    protected string $table = 'document_sequences';

    protected string $entityClass = DocumentSequence::class;

    public function __construct(Database $database)
    {
        parent::__construct($database);
    }

    public function getById(int|string $id): ?DocumentSequence
    {
        /** @var DocumentSequence|null */
        return $this->getEntityById($id);
    }

    public function getAll(array $filters = []): array
    {
        /** @var DocumentSequence[] */
        return $this->getEntities($filters);
    }

    public function findByDocumentType(
        int $documentTypeId,
        int $financialYear
    ): ?DocumentSequence {
        /** @var DocumentSequence|null */
        return $this->hydrate(
            $this->findOne([
                'document_type_id' => $documentTypeId,
                'financial_year'   => $financialYear,
            ])
        );
    }

    public function getActive(): array
    {
        /** @var DocumentSequence[] */
        return $this->hydrateCollection(
            $this->find([
                'is_active' => true,
            ])
        );
    }

    public function insert(DocumentSequence $sequence): DocumentSequence
    {
        /** @var DocumentSequence */
        return $this->insertEntity($sequence);
    }

    public function save(DocumentSequence $sequence): DocumentSequence
    {
        /** @var DocumentSequence */
        return $this->saveEntity($sequence);
    }

    public function remove(int|string $id): bool
    {
        return $this->removeEntity($id);
    }
}