<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

$role     = $_SESSION['role'];
$staff_id = $_SESSION['staff_id'];

switch ($role) {

    case 'super_admin':
        $stmt = $pdo->query("SELECT id, name FROM farms WHERE status='active'");
        break;

    case 'owner':
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM farms 
            WHERE created_by = ?
        ");
        $stmt->execute([$staff_id]);
        break;

    case 'manager':
        $stmt = $pdo->prepare("
            SELECT f.id, f.name
            FROM farms f
            JOIN staff s ON s.farm_id = f.id
            WHERE s.id = ?
        ");
        $stmt->execute([$staff_id]);
        break;

    default:
        echo json_encode([]);
        exit;
}

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));