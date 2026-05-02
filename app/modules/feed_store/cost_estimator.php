<?php
require '../../middleware/auth_guard.php';
require '../../middleware/farm_guard.php';
require '../../config/database.php';

$farm_id = farm_id();

$feed_type = $_GET['feed_type'];
$qty = (float)$_GET['qty'];

$stmt = $pdo->prepare("
    SELECT available_kg, cost_per_kg
    FROM feed_store
    WHERE farm_id=? AND feed_type=? AND available_kg>0
    ORDER BY received_date ASC
");
$stmt->execute([$farm_id,$feed_type]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$remaining = $qty;
$total_cost = 0;

foreach ($rows as $r){

    if ($remaining <= 0) break;

    $take = min($remaining, $r['available_kg']);
    $total_cost += $take * $r['cost_per_kg'];
    $remaining -= $take;
}

echo json_encode([
    'cost' => round($total_cost,2)
]);