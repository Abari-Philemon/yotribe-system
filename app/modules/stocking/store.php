<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/pond_helper.php';

$farm_id = farm_id();

try {

    $pdo->beginTransaction();

    $pond_id = (int) $_POST['pond_id'];
    $qty     = (int) $_POST['quantity'];

    if ($qty <= 0) {
        throw new Exception("Invalid quantity");
    }

    $current  = pond_current_stock($pdo, $pond_id);
    $capacity = pond_capacity($pdo, $pond_id);

    $new_total = $current + $qty;

    if ($new_total > $capacity) {
        throw new Exception("Stock exceeds pond capacity");
    }

    /**
     * INSERT STOCK
     */
    $stmt = $pdo->prepare("
        INSERT INTO pond_stocking 
        (pond_id, quantity, current_count, status)
        VALUES (?, ?, ?, 'active')
    ");
    $stmt->execute([$pond_id, $qty, $qty]);

    /**
     * ALERT LEVEL
     */
    $util = ($new_total / $capacity) * 100;

    if ($util >= 100) $level = 'critical';
    elseif ($util >= 90) $level = 'high';
    elseif ($util >= 75) $level = 'warning';
    else $level = null;

    if ($level) {

        $msg = "Pond near capacity: {$new_total}/{$capacity}";

        $stmt = $pdo->prepare("
            INSERT INTO pond_alerts 
            (pond_id, farm_id, alert_type, message, level)
            VALUES (?, ?, 'capacity', ?, ?)
        ");

        $stmt->execute([$pond_id, $farm_id, $msg, $level]);
    }

    $pdo->commit();

    header("Location: index.php?success=1");
    exit;

} catch (Exception $e) {

    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}