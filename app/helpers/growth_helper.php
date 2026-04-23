<?php

/**
 * RECORD GROWTH + UPDATE SYSTEM
 */
function recordGrowth(PDO $pdo, $farm_id, $pond_id, $batch_id, $sample_count, $avg_weight, $total_count, $date)
{
    $pdo->beginTransaction();

    try {

        /**
         * VALIDATION
         */
        if ($avg_weight <= 0 || $total_count <= 0) {
            throw new Exception("Invalid growth data");
        }

        /**
         * INSERT INTO GROWTH LOGS (SOURCE OF TRUTH)
         */
        $stmt = $pdo->prepare("
            INSERT INTO fish_growth_logs
            (farm_id, pond_id, batch_id, sample_count, avg_weight_g, total_count, recorded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $farm_id,
            $pond_id,
            $batch_id,
            $sample_count,
            $avg_weight,
            $total_count,
            $date
        ]);

        /**
         * UPDATE LIVE WEIGHT (pond level)
         */
        $stmt = $pdo->prepare("
            UPDATE pond_stocking
            SET avg_weight_g = ?
            WHERE farm_id = ? AND pond_id = ? AND batch_id = ?
        ");
        $stmt->execute([$avg_weight, $farm_id, $pond_id, $batch_id]);

        /**
         * UPDATE GLOBAL BATCH (REFERENCE)
         */
        $stmt = $pdo->prepare("
            UPDATE fish_batches
            SET avg_weight_g = ?
            WHERE id = ? AND farm_id = ?
        ");
        $stmt->execute([$avg_weight, $batch_id, $farm_id]);

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}


/**
 * PREDICT NEXT WEIGHT (LINEAR MODEL)
 */
function predictNextWeight(PDO $pdo, $pond_id, $batch_id, $days_ahead = 7)
{
    $stmt = $pdo->prepare("
        SELECT avg_weight_g, recorded_at
        FROM fish_growth_logs
        WHERE pond_id = ? AND batch_id = ?
        ORDER BY recorded_at DESC
        LIMIT 2
    ");
    $stmt->execute([$pond_id, $batch_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) < 2) return null;

    $latest = $logs[0];
    $prev   = $logs[1];

    $days = (strtotime($latest['recorded_at']) - strtotime($prev['recorded_at'])) / 86400;

    if ($days <= 0) return null;

    $growth_per_day = ($latest['avg_weight_g'] - $prev['avg_weight_g']) / $days;

    return $latest['avg_weight_g'] + ($growth_per_day * $days_ahead);
}


/**
 * SPECIFIC GROWTH RATE (SGR)
 */
function calculateSGR(PDO $pdo, $pond_id, $batch_id)
{
    $stmt = $pdo->prepare("
        SELECT avg_weight_g, recorded_at
        FROM fish_growth_logs
        WHERE pond_id = ? AND batch_id = ?
        ORDER BY recorded_at ASC
    ");
    $stmt->execute([$pond_id, $batch_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) < 2) return null;

    $first = $logs[0];
    $last  = end($logs);

    $days = (strtotime($last['recorded_at']) - strtotime($first['recorded_at'])) / 86400;

    if ($days <= 0) return null;

    $sgr = (log($last['avg_weight_g']) - log($first['avg_weight_g'])) / $days * 100;

    return round($sgr, 2);
}


/**
 * GROWTH ALERT SYSTEM
 */
function growthAlert(PDO $pdo, $pond_id, $batch_id)
{
    $predicted = predictNextWeight($pdo, $pond_id, $batch_id, 7);

    if (!$predicted) return null;

    $stmt = $pdo->prepare("
        SELECT avg_weight_g
        FROM pond_stocking
        WHERE pond_id = ? AND batch_id = ?
    ");
    $stmt->execute([$pond_id, $batch_id]);
    $current = (float)$stmt->fetchColumn();

    $growth = $predicted - $current;

    if ($growth < 5) {
        return "⚠️ Poor growth detected";
    }

    return null;
}