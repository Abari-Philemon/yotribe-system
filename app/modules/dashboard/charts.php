<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$farm_id = $_SESSION['farm_id'] ?? 0;

if (!$farm_id) {
    echo json_encode(['labels' => [], 'values' => []]);
    exit;
}

$type = $_GET['type'] ?? '';

try {

    if ($type === 'biomass') {

        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date,
                   SUM(estimated_weight_kg) as total
            FROM fish_inventory
            WHERE farm_id = ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
            LIMIT 7
        ");
        $stmt->execute([$farm_id]);

    } elseif ($type === 'sales') {

        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date,
                   SUM(total_amount) as total
            FROM sales
            WHERE farm_id = ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
            LIMIT 7
        ");
        $stmt->execute([$farm_id]);

    } else {
        echo json_encode(['labels' => [], 'values' => []]);
        exit;
    }

    $labels = [];
    $values = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $labels[] = $row['date'];
        $values[] = (float)$row['total'];
    }

    echo json_encode([
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    echo json_encode([
        'labels' => [],
        'values' => [],
        'error' => $e->getMessage()
    ]);
}