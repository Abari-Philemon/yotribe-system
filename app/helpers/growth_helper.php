<?php

function updateBatchWeight(PDO $pdo, $farm_id, $pond_id, $batch_id, $avg_weight)
{
    // Update pond_stocking
    $stmt = $pdo->prepare("
        UPDATE pond_stocking
        SET avg_weight_g = ?
        WHERE farm_id = ? AND pond_id = ? AND batch_id = ?
    ");
    $stmt->execute([$avg_weight, $farm_id, $pond_id, $batch_id]);

    // Update fish_batches (optional global reference)
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET avg_weight_g = ?
        WHERE id = ? AND farm_id = ?
    ");
    $stmt->execute([$avg_weight, $batch_id, $farm_id]);
}