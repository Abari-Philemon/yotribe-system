<?php
require '../../middleware/auth_guard.php';
require '../../middleware/farm_guard.php';
require '../../config/database.php';

header('Content-Type: application/json');

$farm_id = farm_id();

/**
 * LIVE BIOMASS
 */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(current_count * avg_weight_g)/1000,0)
    FROM pond_stocking
    WHERE farm_id=? AND status='active'
");
$stmt->execute([$farm_id]);
$biomass = (float)$stmt->fetchColumn();

/**
 * TODAY FEED
 */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(quantity_kg),0)
    FROM feeding_logs
    WHERE farm_id=? AND date=CURDATE()
");
$stmt->execute([$farm_id]);
$today_feed = (float)$stmt->fetchColumn();

/**
 * TODAY COST
 */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_cost),0)
    FROM feeding_logs
    WHERE farm_id=? AND date=CURDATE()
");
$stmt->execute([$farm_id]);
$today_cost = (float)$stmt->fetchColumn();

/**
 * ACTIVE PONDS
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM pond_stocking
    WHERE farm_id=? AND status='active'
");
$stmt->execute([$farm_id]);
$active_ponds = (int)$stmt->fetchColumn();

echo json_encode([
    'biomass' => round($biomass,2),
    'today_feed' => round($today_feed,2),
    'today_cost' => round($today_cost,2),
    'ponds' => $active_ponds,
    'time' => date('H:i:s')
]);