<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\DocumentSequence;

interface DocumentSequenceRepositoryInterface
{
    public function getById(int|string $id): ?DocumentSequence;

    /**
     * @param array<string,mixed> $filters
     * @return DocumentSequence[]
     */
    public function getAll(array $filters = []): array;

    public function findByDocumentType(
        int $documentTypeId,
        int $financialYear
    ): ?DocumentSequence;

    /**
     * @return DocumentSequence[]
     */
    public function getActive(): array;

    public function insert(DocumentSequence $sequence): DocumentSequence;

    public function save(DocumentSequence $sequence): DocumentSequence;

    public function remove(int|string $id): bool;
}