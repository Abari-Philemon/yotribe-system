<?php

declare(strict_types=1);

namespace App\Core;

abstract class BaseEntity extends Entity
{
    /**
     * Database primary key.
     */
    public ?int $id = null;

    /**
     * Global unique identifier.
     */
    public string $uuid = '';

    /**
     * Audit information.
     */
    public ?string $created_at = null;

    public ?string $updated_at = null;

    /**
     * Convert the entity to an array.
     */
    public function toArray(): array
    {
        return parent::toArray();
    }
}