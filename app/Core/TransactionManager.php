<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\DatabaseException;
use Closure;
use Throwable;

final class TransactionManager
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    /**
     * Execute a callback inside a database transaction.
     *
     * @template T
     *
     * @param Closure():T $callback
     *
     * @return T
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback): mixed
    {
        try {
            $this->database->beginTransaction();

            $result = $callback();

            $this->database->commit();

            return $result;

        } catch (Throwable $e) {

            if ($this->database->inTransaction()) {
                $this->database->rollback();
            }

            throw $e;
        }
    }

    /**
     * Begin a manual transaction.
     */
    public function begin(): void
    {
        if (!$this->database->inTransaction()) {
            $this->database->beginTransaction();
        }
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->commit();
        }
    }

    /**
     * Roll back the current transaction.
     */
    public function rollback(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollback();
        }
    }

    /**
     * Determine whether a transaction is active.
     */
    public function inTransaction(): bool
    {
        return $this->database->inTransaction();
    }
}