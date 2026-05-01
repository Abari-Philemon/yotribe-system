<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$farm_id = farm_id();

/**
 * QUICK KPI SNAPSHOT (optimized)
 */
function fetchOne($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

$biomass = fetchOne($pdo, "SELECT COALESCE(SUM(estimated_weight_kg),0) FROM fish_inventory WHERE farm_id = ?", [$farm_id]);
$feed    = fetchOne($pdo, "SELECT COALESCE(SUM(quantity_kg),0) FROM feed_store WHERE farm_id = ?", [$farm_id]);
$sales   = fetchOne($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE farm_id = ?", [$farm_id]);
$expense = fetchOne($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE farm_id = ?", [$farm_id]);

$profit = $sales - $expense;

/**
 * LIVE FEEDING TODAY
 */
$stmt = $pdo->prepare("
    SELECT SUM(quantity_kg) 
    FROM feeding_logs 
    WHERE farm_id = ? AND date = CURDATE()
");
$stmt->execute([$farm_id]);
$feed_today = (float)$stmt->fetchColumn();

/**
 * ALERT COUNT
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM pond_alerts 
    WHERE farm_id = ? AND status = 'open'
");
$stmt->execute([$farm_id]);
$alerts = (int)$stmt->fetchColumn();

echo json_encode([
    "biomass" => $biomass,
    "feed" => $feed,
    "sales" => $sales,
    "expense" => $expense,
    "profit" => $profit,
    "feed_today" => $feed_today,
    "alerts" => $alerts,
    "timestamp" => time()
]);