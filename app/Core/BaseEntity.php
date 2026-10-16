<?php

declare(strict_types=1);

namespace App\Core;

require_once __DIR__ . '/../helpers/uuid_helper.php';

abstract class BaseEntity extends Entity
{
    public ?int $id = null;
    public string $uuid;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public function __construct()
    {
        $this->uuid = generateUuid();
    }

    public function toArray(): array
    {
        return parent::toArray();
    }
}