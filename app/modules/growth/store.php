<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/growth_helper.php';

$farm_id = farm_id();

/**
 * BASIC VALIDATION
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: create.php");
    exit;
}

$pond_id      = (int)($_POST['pond_id'] ?? 0);
$batch_id     = (int)($_POST['batch_id'] ?? 0);
$sample_count = (int)($_POST['sample_count'] ?? 0);
$avg_weight   = (float)($_POST['avg_weight_g'] ?? 0);
$total_count  = (int)($_POST['total_count'] ?? 0);
$date         = $_POST['recorded_at'] ?? date('Y-m-d');

try {

    /**
     * STRICT VALIDATION
     */
    if (!$pond_id || !$batch_id) {
        throw new Exception("Invalid pond or batch");
    }

    if ($sample_count <= 0) {
        throw new Exception("Sample count must be greater than 0");
    }

    if ($avg_weight <= 0) {
        throw new Exception("Average weight must be greater than 0");
    }

    if ($total_count <= 0) {
        throw new Exception("Total fish count is invalid");
    }

    /**
     * VERIFY BATCH EXISTS IN THIS POND
     */
    $stmt = $pdo->prepare("
        SELECT current_count
        FROM pond_stocking
        WHERE farm_id = ? AND pond_id = ? AND batch_id = ? AND status = 'active'
    ");
    $stmt->execute([$farm_id, $pond_id, $batch_id]);
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stock) {
        throw new Exception("Batch not found in selected pond");
    }

    /**
     * PREVENT INVALID COUNT OVERRIDE
     */
    if ($total_count > $stock['current_count']) {
        throw new Exception("Total count cannot exceed current stock ({$stock['current_count']})");
    }

    /**
     * PREVENT DUPLICATE ENTRY SAME DAY
     */
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM fish_growth_logs
        WHERE farm_id = ? AND pond_id = ? AND batch_id = ? AND recorded_at = ?
    ");
    $stmt->execute([$farm_id, $pond_id, $batch_id, $date]);

    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Growth already recorded for this batch today");
    }

    /**
     * RECORD GROWTH (MAIN ENGINE)
     */
    recordGrowth(
        $pdo,
        $farm_id,
        $pond_id,
        $batch_id,
        $sample_count,
        $avg_weight,
        $total_count,
        $date
    );

    /**
     * OPTIONAL: GET PREDICTION + ALERT
     */
    $predicted = predictNextWeight($pdo, $pond_id, $batch_id);
    $alert     = growthAlert($pdo, $pond_id, $batch_id);

    $msg = "Growth recorded successfully";

    if ($predicted) {
        $msg .= " | Predicted (7d): " . round($predicted, 2) . "g";
    }

    if ($alert) {
        $msg .= " | " . $alert;
    }

    header("Location: create.php?success=" . urlencode($msg));
    exit;

} catch (Exception $e) {

    header("Location: create.php?error=" . urlencode($e->getMessage()));
    exit;
}