<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/pond_helper.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

/**
 * INPUT
 */
$pond_id   = (int) $_POST['pond_id'];
$fish_qty  = (int) $_POST['quantity'];

/**
 * LOAD POND
 */
$stmt = $pdo->prepare("
    SELECT volume_liters, capacity 
    FROM ponds_tanks
    WHERE id = ? AND farm_id = ?
    LIMIT 1
");
$stmt->execute([$pond_id, farm_id()]);
$pond = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pond) {
    die("Invalid pond");
}

$volume = (float) $pond['volume_liters'];
$capacity = (int) $pond['capacity'];

/**
 * CALCULATE MAX BASED ON VOLUME
 */
$max_by_volume = calculateMaxStock($pdo, $volume);

/**
 * ALSO RESPECT MANUAL CAPACITY
 */
$max_allowed = min($max_by_volume, $capacity);

/**
 * CURRENT STOCK IN POND
 */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(quantity),0)
    FROM fish_stocking
    WHERE pond_id = ?
");
$stmt->execute([$pond_id]);

$current_stock = (int) $stmt->fetchColumn();

/**
 * PROJECTED STOCK
 */
$new_total = $current_stock + $fish_qty;

/**
 * ENFORCE LIMIT
 */
if ($new_total > $max_allowed) {

    $remaining = $max_allowed - $current_stock;

    die("
        ❌ Stocking limit exceeded.<br><br>
        Max allowed: {$max_allowed} fish<br>
        Current: {$current_stock}<br>
        You can only add: {$remaining}
    ");
}

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
        (pond_id, stocked_count, current_count, status)
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