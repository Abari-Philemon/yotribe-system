<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';

$farm_id = farm_id();

$feed_type = $_GET['feed_type'] ?? '';
$qty       = (float)($_GET['qty'] ?? 0);

if (!$feed_type || $qty <= 0) {
    echo json_encode(['status'=>'error']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, batch_no, available_kg, cost_per_kg, received_date
    FROM feed_store
    WHERE feed_type = ?
    AND status='active'
    AND available_kg > 0
    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
    ORDER BY received_date ASC, id ASC
");
$stmt->execute([$feed_type]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$remaining = $qty;
$total_cost = 0;
$preview = [];

foreach ($rows as $r) {

    if ($remaining <= 0) break;

    $take = min($remaining, $r['available_kg']);
    $cost = $take * $r['cost_per_kg'];

    $preview[] = [
        'batch' => $r['batch_no'],
        'take'  => $take,
        'cost'  => $cost,
        'rate'  => $r['cost_per_kg']
    ];

    $total_cost += $cost;
    $remaining -= $take;
}

echo json_encode([
    'status' => 'ok',
    'preview' => $preview,
    'total_cost' => $total_cost,
    'remaining' => $remaining
]);