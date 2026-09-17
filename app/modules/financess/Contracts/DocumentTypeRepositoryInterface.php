<?php

declare(strict_types=1);

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\Entities\DocumentType;

interface DocumentTypeRepositoryInterface
{
    public function getById(int|string $id): ?DocumentType;

    /**
     * @param array<string,mixed> $filters
     * @return DocumentType[]
     */
    public function getAll(array $filters = []): array;

    /**
     * @return DocumentType[]
     */
    public function getActive(): array;

    public function findByCode(string $code): ?DocumentType;

    public function insert(DocumentType $documentType): DocumentType;

    public function save(DocumentType $documentType): DocumentType;

    public function remove(int|string $id): bool;
}