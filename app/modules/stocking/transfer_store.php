<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id = farm_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

// CSRF
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Invalid CSRF token");
}

try {

    $pdo->beginTransaction();

    /**
     * -----------------------------------------
     * SAFE INPUTS
     * -----------------------------------------
     */
    $stock_id   = isset($_POST['stock_id']) ? (int)$_POST['stock_id'] : 0;
    $to_pond_id = isset($_POST['to_pond']) ? (int)$_POST['to_pond'] : 0;
    $qty        = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    if ($stock_id <= 0) {
        throw new Exception("Invalid source selection");
    }

    if ($to_pond_id <= 0) {
        throw new Exception("Destination pond is required");
    }

    if ($qty <= 0) {
        throw new Exception("Invalid quantity");
    }

    /**
     * -----------------------------------------
     * LOCK SOURCE STOCK ROW
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        SELECT *
        FROM pond_stocking
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$stock_id, $farm_id]);

    $source = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        throw new Exception("Source not found");
    }

    if ($qty > $source['current_count']) {
        throw new Exception("Not enough fish in source pond");
    }

    if ($source['pond_id'] == $to_pond_id) {
        throw new Exception("Cannot transfer to same pond");
    }

    /**
     * -----------------------------------------
     * LOCK DESTINATION POND
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM ponds_tanks
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$to_pond_id, $farm_id]);

    if (!$stmt->fetch()) {
        throw new Exception("Invalid destination pond");
    }

    /**
     * -----------------------------------------
     * VALIDATE DESTINATION CAPACITY
     * -----------------------------------------
     */
    validateStocking($pdo, $to_pond_id, $farm_id, $qty);

    /**
     * -----------------------------------------
     * REDUCE SOURCE STOCK
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        UPDATE pond_stocking
        SET current_count = current_count - ?
        WHERE id = ?
    ");
    $stmt->execute([$qty, $stock_id]);

    /**
     * CLOSE SOURCE IF EMPTY
     */
    $stmt = $pdo->prepare("
        UPDATE pond_stocking
        SET status = 'moved'
        WHERE id = ?
        AND current_count <= 0
    ");
    $stmt->execute([$stock_id]);

    /**
     * -----------------------------------------
     * CHECK DESTINATION EXISTING BATCH
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        SELECT id
        FROM pond_stocking
        WHERE pond_id = ? AND batch_id = ? AND status = 'active'
    ");
    $stmt->execute([$to_pond_id, $source['batch_id']]);
    $dest = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dest) {
        /**
         * UPDATE EXISTING RECORD
         */
        $stmt = $pdo->prepare("
            UPDATE pond_stocking
            SET current_count = current_count + ?
            WHERE id = ?
        ");
        $stmt->execute([$qty, $dest['id']]);

    } else {
        /**
         * INSERT NEW RECORD
         */
        $stmt = $pdo->prepare("
            INSERT INTO pond_stocking
            (farm_id, pond_id, batch_id, stocked_count, current_count, avg_weight_g, stocking_date, status)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'active')
        ");
        $stmt->execute([
            $farm_id,
            $to_pond_id,
            $source['batch_id'],
            $qty,
            $qty,
            $source['avg_weight_g']
        ]);
    }

    /**
     * -----------------------------------------
     * LOG MOVEMENT (IMPORTANT FOR AUDIT)
     * -----------------------------------------
     */
    $stmt = $pdo->prepare("
        INSERT INTO stock_movements
        (farm_id, type, from_pond_id, to_pond_id, batch_id, quantity, movement_date)
        VALUES (?, 'transfer', ?, ?, ?, ?, CURDATE())
    ");
    $stmt->execute([
        $farm_id,
        $source['pond_id'],
        $to_pond_id,
        $source['batch_id'],
        $qty
    ]);

    /**
     * -----------------------------------------
     * COMMIT
     * -----------------------------------------
     */
    $pdo->commit();

    header("Location: index.php?success=transfer");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("Transfer Error: " . $e->getMessage());
}