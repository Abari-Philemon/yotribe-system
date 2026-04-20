<?php

function pond_current_stock(PDO $pdo, int $pond_id): int
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(current_count),0)
        FROM pond_stocking
        WHERE pond_id = ?
          AND status = 'active'
    ");
    $stmt->execute([$pond_id]);

    return (int)$stmt->fetchColumn();
}

function pond_capacity(PDO $pdo, int $pond_id): int
{
    $stmt = $pdo->prepare("
        SELECT capacity FROM ponds_tanks WHERE id = ?
    ");
    $stmt->execute([$pond_id]);

    return (int)$stmt->fetchColumn();
}

function pond_utilization(PDO $pdo, int $pond_id): float
{
    $stock = pond_current_stock($pdo, $pond_id);
    $cap   = pond_capacity($pdo, $pond_id);

    if ($cap <= 0) return 0;

    return round(($stock / $cap) * 100, 2);
}