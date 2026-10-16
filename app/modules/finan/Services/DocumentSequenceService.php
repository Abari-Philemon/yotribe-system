<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Contracts\DocumentSequenceRepositoryInterface;
use App\Modules\Finance\Entities\DocumentSequence;

final class DocumentSequenceService extends BaseFinanceService
{
    public function __construct(
        private readonly DocumentSequenceRepositoryInterface $sequenceRepository
    ) {
    }

    public function getById(int|string $id): ?DocumentSequence
    {
        return $this->sequenceRepository->getById($id);
    }

    /**
     * @param array<string,mixed> $filters
     * @return DocumentSequence[]
     */
    public function getAll(array $filters = []): array
    {
        return $this->sequenceRepository->getAll($filters);
    }

    public function findByDocumentType(
        int $documentTypeId,
        int $financialYear
    ): ?DocumentSequence {
        return $this->sequenceRepository->findByDocumentType(
            $documentTypeId,
            $financialYear
        );
    }

    /**
     * @return DocumentSequence[]
     */
    public function getActive(): array
    {
        return $this->sequenceRepository->getActive();
    }

    public function create(DocumentSequence $sequence): DocumentSequence
    {
        return $this->sequenceRepository->insert($sequence);
    }

    public function save(DocumentSequence $sequence): DocumentSequence
    {
        return $this->sequenceRepository->save($sequence);
    }

    public function remove(int|string $id): bool
    {
        return $this->sequenceRepository->remove($id);
    }

    public function getNextNumber(DocumentSequence $sequence): int
    {
        return $sequence->last_number + 1;
    }

    public function increment(DocumentSequence $sequence): DocumentSequence
    {
        $sequence->last_number++;

        return $this->sequenceRepository->save($sequence);
    }

    public function formatNumber(DocumentSequence $sequence): string
    {
        $number = str_pad(
            (string) ($sequence->last_number + 1),
            $sequence->number_length,
            '0',
            STR_PAD_LEFT
        );

        return $sequence->prefix
            . $sequence->separator
            . $number;
    }

    public function isActive(DocumentSequence $sequence): bool
    {
        return $sequence->is_active;
    }

    public function resetsAnnually(DocumentSequence $sequence): bool
    {
        return $sequence->reset_annually;
    }
}