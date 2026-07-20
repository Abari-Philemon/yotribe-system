<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\DocumentTypeRepositoryInterface;
use App\Modules\Finance\Entities\DocumentType;

final class DocumentTypeService extends BaseFinanceService
{
    public function __construct(
        private readonly DocumentTypeRepositoryInterface $documentTypeRepository
    ) {
    }

    public function getById(int|string $id): ?DocumentType
    {
        return $this->documentTypeRepository->getById($id);
    }

    /**
     * @param array<string,mixed> $filters
     * @return DocumentType[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->documentTypeRepository->getAll($filters);
    }

    /**
     * @return DocumentType[]
     */
    public function getActive(): array
    {
        return $this->documentTypeRepository->getActive();
    }

    public function findByCode(string $code): ?DocumentType
    {
        return $this->documentTypeRepository->findByCode($code);
    }

    public function create(DocumentType $documentType): DocumentType
    {
        return $this->documentTypeRepository->insert($documentType);
    }

    public function save(DocumentType $documentType): DocumentType
    {
        return $this->documentTypeRepository->save($documentType);
    }

    public function remove(int|string $id): bool
    {
        return $this->documentTypeRepository->remove($id);
    }

    public function isSystem(DocumentType $documentType): bool
    {
        return $documentType->is_system;
    }

    public function isActive(DocumentType $documentType): bool
    {
        return $documentType->is_active;
    }

    public function canDelete(DocumentType $documentType): bool
    {
        return !$documentType->is_system;
    }

    public function getDisplayName(DocumentType $documentType): string
    {
        return sprintf(
            '%s (%s)',
            $documentType->name,
            $documentType->code
        );
    }
}