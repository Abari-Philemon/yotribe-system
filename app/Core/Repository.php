<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Contracts\RepositoryInterface;
use InvalidArgumentException;

abstract class Repository implements RepositoryInterface
{
    /**
     * Table name.
     */
    protected string $table = '';

    /**
     * Primary key column.
     */
    protected string $primaryKey = 'id';

    /**
     * Automatic timestamps.
     */
    protected bool $timestamps = true;

    /**
     * Soft delete support.
     */
    protected bool $softDelete = false;

    protected string $createdAtColumn = 'created_at';
    protected string $updatedAtColumn = 'updated_at';
    protected string $deletedAtColumn = 'deleted_at';

    public function __construct(
        protected readonly Database $db
    ) {
        if ($this->table === '') {
            throw new InvalidArgumentException(
                static::class . ' must define a table name.'
            );
        }
    }

    /**
     * Get table name.
     */
    protected function table(): string
    {
        return $this->table;
    }

    /**
     * Active records clause.
     */
    protected function activeWhereClause(): string
    {
        return $this->softDelete
            ? "{$this->deletedAtColumn} IS NULL"
            : '1=1';
    }

    /**
     * Find record by primary key.
     */
    public function findById(int|string $id): ?array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :id AND %s LIMIT 1',
            $this->table(),
            $this->primaryKey,
            $this->activeWhereClause()
        );

        return $this->db->fetch($sql, [
            'id' => $id,
        ]);
    }

    /**
     * Retrieve all records.
     */
    public function findAll(array $filters = []): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s',
            $this->table(),
            $this->activeWhereClause()
        );

        return $this->db->fetchAll($sql);
    }

    /**
     * Check if a record exists.
     */
    public function exists(int|string $id): bool
    {
        $sql = sprintf(
            'SELECT COUNT(*) total FROM %s WHERE %s = :id AND %s',
            $this->table(),
            $this->primaryKey,
            $this->activeWhereClause()
        );

        $row = $this->db->fetch($sql, [
            'id' => $id,
        ]);

        return (int)($row['total'] ?? 0) > 0;
    }

    /**
     * Count records.
     */
    public function count(array $filters = []): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) total FROM %s WHERE %s',
            $this->table(),
            $this->activeWhereClause()
        );

        $row = $this->db->fetch($sql);

        return (int)($row['total'] ?? 0);
    }

    /**
     * Create a record.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');

            $data[$this->createdAtColumn] ??= $now;
            $data[$this->updatedAtColumn] ??= $now;
        }

        $columns = array_keys($data);

        $placeholders = array_map(
            static fn(string $column): string => ':' . $column,
            $columns
        );

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->db->execute($sql, $data);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update a record.
     *
     * @param array<string,mixed> $data
     */
    public function update(int|string $id, array $data): bool
    {
        if ($this->timestamps) {
            $data[$this->updatedAtColumn] = date('Y-m-d H:i:s');
        }

        $sets = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = :{$column}";
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :primaryKey',
            $this->table(),
            implode(', ', $sets),
            $this->primaryKey
        );

        $data['primaryKey'] = $id;

        return $this->db->execute($sql, $data);
    }

    /**
     * Delete a record.
     */
    public function delete(int|string $id): bool
    {
        if ($this->softDelete) {
            return $this->update($id, [
                $this->deletedAtColumn => date('Y-m-d H:i:s'),
            ]);
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :id',
            $this->table(),
            $this->primaryKey
        );

        return $this->db->execute($sql, [
            'id' => $id,
        ]);
    }
    /**
     * Find the first record matching the criteria.
     *
     * @param array<string,mixed> $criteria
     */
    protected function findOne(array $criteria): ?array
    {
        $results = $this->find($criteria);

        return $results[0] ?? null;
    }

    /**
     * Find records matching the supplied criteria.
     *
     * @param array<string,mixed> $criteria
     * @return array<array<string,mixed>>
     */
    protected function find(array $criteria): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if ($criteria !== []) {
            $conditions = [];

            foreach (array_keys($criteria) as $column) {
                $conditions[] = "{$column} = :{$column}";
            }

            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        return $this->db->fetchAll($sql, $criteria);
    }
}