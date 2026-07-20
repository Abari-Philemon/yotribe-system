<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Returns the underlying PDO instance.
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepare and execute a SQL statement.
     *
     * @param array<string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return $statement;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database query failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Fetch a single row.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Fetch multiple rows.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Execute INSERT, UPDATE or DELETE.
     *
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): bool
    {
        return $this->query($sql, $params)->rowCount() > 0;
    }

    /**
     * Last inserted ID.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin transaction.
     */
    public function beginTransaction(): bool
    {
        if ($this->pdo->inTransaction()) {
            return true;
        }

        return $this->pdo->beginTransaction();
    }


    /**
     * Commit transaction.
     */
    public function commit(): bool
    {
        if (!$this->pdo->inTransaction()) {
            return true;
        }

        return $this->pdo->commit();
    }


    /**
     * Roll back transaction.
     */
    public function rollback(): bool
    {
        if (!$this->pdo->inTransaction()) {
            return true;
        }

        return $this->pdo->rollBack();
    }

    /**
     * Determine whether a transaction is active.
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }




}