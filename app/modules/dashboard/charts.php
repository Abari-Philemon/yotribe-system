<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';


header('Content-Type: application/json');

if (!isset($_SESSION['active_farm_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Farm context missing']);
    exit;
}

$farm_id = (int) $_SESSION['active_farm_id'];
$type    = $_GET['type'] ?? '';

$data = [
    'labels' => [],
    'values' => []
];

switch ($type) {

    case 'biomass':
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) d, SUM(estimated_weight_kg) v
            FROM fish_inventory
            WHERE farm_id = ?
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY d
            ORDER BY d
        ");
        break;

    case 'sales':
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) d, SUM(total_amount) v
            FROM sales
            WHERE farm_id = ?
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY d
            ORDER BY d
        ");
        break;

    default:
        echo json_encode(['error' => 'Invalid chart type']);
        exit;
}

$stmt->execute([$farm_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data['labels'][] = $row['d'];
    $data['values'][] = (float) $row['v'];
}

echo json_encode($data);
