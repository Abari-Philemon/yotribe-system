<?php

declare(strict_types=1);

function beginTransaction(PDO $pdo): void
{
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
}

function commitTransaction(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
}

function rollbackTransaction(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}