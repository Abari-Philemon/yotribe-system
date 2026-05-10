<?php

/**
 * =========================================================
 * GROWTH HELPER
 * =========================================================
 * Handles:
 * - Growth recording
 * - Batch weight updates
 * - Growth prediction
 * - SGR calculations
 * - Growth alerts
 * =========================================================
 */


/**
 * RECORD GROWTH + UPDATE LIVE WEIGHT
 */
function recordGrowth(
    PDO $pdo,
    int $farm_id,
    int $pond_id,
    int $batch_id,
    int $sample_count,
    float $total_weight_g,
    int $recorded_by,
    string $remarks = ''
){

    if ($sample_count <= 0) {
        throw new Exception("Invalid sample count");
    }

    if ($total_weight_g <= 0) {
        throw new Exception("Invalid total weight");
    }

    $avg_weight = $total_weight_g / $sample_count;

    try {

        $pdo->beginTransaction();

        /**
         * LOCK ACTIVE STOCK
         */
        $stmt = $pdo->prepare("
            SELECT *
            FROM pond_stocking
            WHERE farm_id = ?
            AND pond_id = ?
            AND batch_id = ?
            AND status = 'active'
            FOR UPDATE
        ");

        $stmt->execute([
            $farm_id,
            $pond_id,
            $batch_id
        ]);

        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Active stock not found");
        }

        /**
         * INSERT GROWTH LOG
         */
        $stmt = $pdo->prepare("
            INSERT INTO growth_logs (
                farm_id,
                pond_id,
                batch_id,
                sample_count,
                total_weight_g,
                avg_weight_g,
                recorded_by,
                remarks
            )
            VALUES (?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $farm_id,
            $pond_id,
            $batch_id,
            $sample_count,
            $total_weight_g,
            $avg_weight,
            $recorded_by,
            $remarks
        ]);

        /**
         * UPDATE LIVE WEIGHT
         */
        updateBatchWeight(
            $pdo,
            $farm_id,
            $pond_id,
            $batch_id,
            $avg_weight
        );

        $pdo->commit();

        return [
            'success' => true,
            'avg_weight_g' => round($avg_weight, 2)
        ];

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/**
 * UPDATE LIVE BATCH WEIGHT
 */
function updateBatchWeight(
    PDO $pdo,
    int $farm_id,
    int $pond_id,
    int $batch_id,
    float $avg_weight
){

    /**
     * UPDATE pond_stocking
     */
    $stmt = $pdo->prepare("
        UPDATE pond_stocking
        SET avg_weight_g = ?
        WHERE farm_id = ?
        AND pond_id = ?
        AND batch_id = ?
        AND status = 'active'
    ");

    $stmt->execute([
        $avg_weight,
        $farm_id,
        $pond_id,
        $batch_id
    ]);

    /**
     * UPDATE fish_batches
     */
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET avg_weight_g = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $avg_weight,
        $batch_id
    ]);
}


/**
 * PREDICT NEXT WEIGHT
 * Simple linear growth model
 */
function predictNextWeight(
    PDO $pdo,
    int $pond_id,
    int $batch_id,
    int $days_ahead = 7
){

    $stmt = $pdo->prepare("
        SELECT avg_weight_g, recorded_at
        FROM growth_logs
        WHERE pond_id = ?
        AND batch_id = ?
        ORDER BY recorded_at DESC
        LIMIT 2
    ");

    $stmt->execute([
        $pond_id,
        $batch_id
    ]);

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) < 2) {
        return null;
    }

    $latest = $logs[0];
    $previous = $logs[1];

    $days = (
        strtotime($latest['recorded_at']) -
        strtotime($previous['recorded_at'])
    ) / 86400;

    if ($days <= 0) {
        return null;
    }

    $growth_per_day =
        ($latest['avg_weight_g'] - $previous['avg_weight_g']) / $days;

    return round(
        $latest['avg_weight_g'] + ($growth_per_day * $days_ahead),
        2
    );
}


/**
 * SPECIFIC GROWTH RATE (SGR)
 */
function calculateSGR(
    PDO $pdo,
    int $pond_id,
    int $batch_id
){

    $stmt = $pdo->prepare("
        SELECT avg_weight_g, recorded_at
        FROM growth_logs
        WHERE pond_id = ?
        AND batch_id = ?
        ORDER BY recorded_at ASC
    ");

    $stmt->execute([
        $pond_id,
        $batch_id
    ]);

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) < 2) {
        return null;
    }

    $first = $logs[0];
    $last = end($logs);

    $days = (
        strtotime($last['recorded_at']) -
        strtotime($first['recorded_at'])
    ) / 86400;

    if ($days <= 0) {
        return null;
    }

    $sgr = (
        log($last['avg_weight_g']) -
        log($first['avg_weight_g'])
    ) / $days * 100;

    return round($sgr, 2);
}


/**
 * DAILY WEIGHT GAIN
 */
function calculateDailyGain(
    PDO $pdo,
    int $pond_id,
    int $batch_id
){

    $stmt = $pdo->prepare("
        SELECT avg_weight_g, recorded_at
        FROM growth_logs
        WHERE pond_id = ?
        AND batch_id = ?
        ORDER BY recorded_at DESC
        LIMIT 2
    ");

    $stmt->execute([
        $pond_id,
        $batch_id
    ]);

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) < 2) {
        return null;
    }

    $latest = $logs[0];
    $previous = $logs[1];

    $days = (
        strtotime($latest['recorded_at']) -
        strtotime($previous['recorded_at'])
    ) / 86400;

    if ($days <= 0) {
        return null;
    }

    $gain =
        ($latest['avg_weight_g'] - $previous['avg_weight_g']) / $days;

    return round($gain, 2);
}


/**
 * GROWTH ALERT SYSTEM
 */
function growthAlert(
    PDO $pdo,
    int $pond_id,
    int $batch_id
){

    $predicted = predictNextWeight(
        $pdo,
        $pond_id,
        $batch_id,
        7
    );

    if (!$predicted) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT avg_weight_g
        FROM pond_stocking
        WHERE pond_id = ?
        AND batch_id = ?
        AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        $pond_id,
        $batch_id
    ]);

    $current = (float) $stmt->fetchColumn();

    $growth = $predicted - $current;

    if ($growth < 5) {
        return "⚠️ Poor growth detected";
    }

    return null;
}