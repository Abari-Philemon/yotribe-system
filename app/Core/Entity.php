<?php

declare(strict_types=1);

namespace App\Core;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;

abstract class Entity
{
    /**
     * Hydrate an entity from an array.
     */
    public static function fromArray(array $data): static
    {
        $entity = new static();

        $reflection = new ReflectionClass($entity);

        foreach ($data as $property => $value) {

            if (!$reflection->hasProperty($property)) {
                continue;
            }

            $reflectionProperty = $reflection->getProperty($property);

            $type = $reflectionProperty->getType();

            if (
                $type instanceof ReflectionNamedType &&
                enum_exists($type->getName()) &&
                is_subclass_of($type->getName(), BackedEnum::class) &&
                $value !== null
            ) {
                $value = $type->getName()::from($value);
            }

            $entity->{$property} = $value;
        }

        return $entity;
    }

    /**
     * Convert entity into array.
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);

        $data = [];

        foreach ($reflection->getProperties() as $property) {

            if (!$property->isInitialized($this)) {
                continue;
            }

            $value = $property->getValue($this);

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            $data[$property->getName()] = $value;
        }

        return $data;
    }
}