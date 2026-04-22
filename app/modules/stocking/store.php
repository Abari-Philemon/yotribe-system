<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id = farm_id();

try {

    $pdo->beginTransaction();

    /**
     * -----------------------------------------
     * SAFE INPUTS
     * -----------------------------------------
     */
    $pond_id  = isset($_POST['pond_id']) ? (int)$_POST['pond_id'] : 0;
    $batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
    $qty      = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    if ($pond_id <= 0) {
        throw new Exception("Pond is required");
    }

    if ($batch_id <= 0) {
        throw new Exception("Batch is required");
    }

    if ($qty <= 0) {
        throw new Exception("Invalid quantity");
    }

    /**
     * -----------------------------------------
     * LOCK BATCH (CRITICAL)
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        SELECT id, current_count, status, avg_weight_g
        FROM fish_batches
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$batch_id, $farm_id]);

    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new Exception("Invalid batch");
    }

    if ($batch['status'] !== 'active') {
        throw new Exception("Batch is not active");
    }

    if ($qty > $batch['current_count']) {
        throw new Exception("Batch has only {$batch['current_count']} fish left");
    }

    /**
     * -----------------------------------------
     * LOCK POND (CRITICAL)
     * -----------------------------------------
     * We call helper AFTER lock to avoid race conditions
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM ponds_tanks
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$pond_id, $farm_id]);

    if (!$stmt->fetch()) {
        throw new Exception("Invalid pond");
    }

    /**
     * -----------------------------------------
     * VALIDATE STOCKING (CENTRAL ENGINE)
     * -----------------------------------------
     */
    validateStocking($pdo, $pond_id, $farm_id, $qty);

    /**
     * -----------------------------------------
     * INSERT STOCKING RECORD
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        INSERT INTO pond_stocking
        (farm_id, pond_id, batch_id, stocked_count, current_count, avg_weight_g, stocking_date, status)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'active')
    ");

    $stmt->execute([
        $farm_id,
        $pond_id,
        $batch_id,
        $qty,
        $qty,
        $batch['avg_weight_g'] ?? 0
    ]);

    /**
     * -----------------------------------------
     * UPDATE BATCH INVENTORY
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET current_count = current_count - ?
        WHERE id = ?
    ");
    $stmt->execute([$qty, $batch_id]);

    /**
     * -----------------------------------------
     * AUTO CLOSE BATCH IF EMPTY
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET status = 'closed'
        WHERE id = ?
        AND current_count <= 0
    ");
    $stmt->execute([$batch_id]);

    /**
     * -----------------------------------------
     * COMMIT TRANSACTION
     * -----------------------------------------
     */
    $pdo->commit();

    header("Location: index.php?success=1");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("Stocking Error: " . $e->getMessage());
}