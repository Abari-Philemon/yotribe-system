<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id = farm_id();

try {

    $pdo->beginTransaction();

    /**
     * SAFE INPUTS
     */
    $pond_id  = isset($_POST['pond_id']) ? (int)$_POST['pond_id'] : 0;
    $batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
    $qty      = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    if ($pond_id <= 0) throw new Exception("Pond is required");
    if ($batch_id <= 0) throw new Exception("Batch is required");
    if ($qty <= 0) throw new Exception("Invalid quantity");

    /**
     * LOCK BATCH
     */
    $stmt = $pdo->prepare("
        SELECT id, current_count, status, avg_weight_g
        FROM fish_batches
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$batch_id, $farm_id]);

    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) throw new Exception("Invalid batch");

    if ($batch['status'] !== 'active') {
        throw new Exception("Batch is not active");
    }

    if ($qty > $batch['current_count']) {
        throw new Exception("Batch has only {$batch['current_count']} fish left");
    }

    /**
     * LOCK POND
     */
    $stmt = $pdo->prepare("
        SELECT volume_liters, capacity
        FROM ponds_tanks
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$pond_id, $farm_id]);

    $pond = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pond) throw new Exception("Invalid pond");

    if ($pond['volume_liters'] <= 0) {
        throw new Exception("Pond volume not set");
    }

    /**
     * CALCULATE LIMIT
     */
   

    /**
     * CURRENT STOCK (FARM SAFE)
     */
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(current_count),0)
        FROM pond_stocking
        WHERE pond_id = ?
        AND farm_id = ?
        AND status = 'active'
    ");
    $stmt->execute([$pond_id, $farm_id]);

    $current_stock = (int)$stmt->fetchColumn();

    if ($current_stock >= $max_allowed) {
        throw new Exception("Pond is already full");
    }

    $remaining = $max_allowed - $current_stock;

    if ($qty > $remaining) {
        throw new Exception("Pond limit exceeded. Max you can add is {$remaining}");
    }

    /**
     * INSERT STOCKING
     */
    $stmt = $pdo->prepare("
        INSERT INTO pond_stocking
        (farm_id, pond_id, batch_id, stocked_count, current_count, avg_weight_g, stocking_date)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE())
    ");

    $stmt->execute([
        $farm_id,
        $pond_id,
        $batch_id,
        $qty,
        $qty,
        $batch['avg_weight_g'] // <-- FIXED
    ]);

    /**
     * REDUCE BATCH
     */
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET current_count = current_count - ?
        WHERE id = ?
    ");
    $stmt->execute([$qty, $batch_id]);

    /**
     * AUTO CLOSE BATCH
     */
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET status = 'closed'
        WHERE id = ?
        AND current_count <= 0
    ");
    $stmt->execute([$batch_id]);

    $pdo->commit();

    header("Location: index.php?success=1");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("Stocking Error: " . $e->getMessage());
}