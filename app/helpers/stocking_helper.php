<?php

function getStockingRatio(PDO $pdo): float
{
    $stmt = $pdo->prepare("
        SELECT setting_value 
        FROM system_settings 
        WHERE setting_key = 'stocking_ratio_liters_per_fish'
        LIMIT 1
    ");
    $stmt->execute();

    $val = $stmt->fetchColumn();

    return $val ? (float)$val : 10; // default fallback
}

function calculateMaxStock(PDO $pdo, float $volume_liters): int
{
    $ratio = getStockingRatio($pdo);

    if ($volume_liters <= 0) return 0;

    return (int) floor($volume_liters / $ratio);
}