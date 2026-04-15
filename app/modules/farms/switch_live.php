<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/csrf.php';
require_once __DIR__ . '/../../config/database.php';

$csrf_token = $_POST['csrf_token'] ?? '';
csrf_verify($csrf_token);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$role     = $_SESSION['role'] ?? '';
$staff_id = $_SESSION['staff_id'] ?? 0;
$farm_id  = (int)($_POST['farm_id'] ?? 0);

if (!$farm_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid farm']);
    exit;
}

/**
 * 🔒 ACCESS CONTROL (CRITICAL)
 */
switch ($role) {

    case 'super_admin':
        // Can access any farm
        $stmt = $pdo->prepare("SELECT id, name FROM farms WHERE id = ?");
        $stmt->execute([$farm_id]);
        break;

    case 'owner':
        // Only farms they created
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM farms 
            WHERE id = ? AND created_by = ?
        ");
        $stmt->execute([$farm_id, $staff_id]);
        break;

    case 'manager':
        // Only assigned farm
        $stmt = $pdo->prepare("
            SELECT f.id, f.name
            FROM farms f
            JOIN staff s ON s.farm_id = f.id
            WHERE f.id = ? AND s.id = ?
        ");
        $stmt->execute([$farm_id, $staff_id]);
        break;

    default:
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
}
unset($_SESSION['active_farm_id']);
unset($_SESSION['active_farm_name']);

$farm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$farm) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Access denied to this farm'
    ]);
    exit;
}

/**
 * ✅ SET ACTIVE FARM
 */
$_SESSION['active_farm_id']   = $farm['id'];
$_SESSION['active_farm_name'] = $farm['name'];

echo json_encode([
    'status' => 'success',
    'farm' => $farm
]);
