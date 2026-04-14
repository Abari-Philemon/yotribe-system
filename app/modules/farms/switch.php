<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Only allow POST requests
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: select.php");
    exit;
}

/**
 * Session data
 */
$role     = $_SESSION['role'] ?? '';
$staff_id = $_SESSION['staff_id'] ?? 0;
$farm_id  = (int) ($_POST['farm_id'] ?? 0);

/**
 * Role restriction
 */
if (!in_array($role, ['super_admin', 'owner', 'manager'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

/**
 * Validate farm access based on role
 */
switch ($role) {

    case 'super_admin':
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM farms 
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$farm_id]);
        break;

    case 'owner':
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM farms 
            WHERE id = ? AND owner_id = ?
            LIMIT 1
        ");
        $stmt->execute([$farm_id, $staff_id]);
        break;

    case 'manager':
        $stmt = $pdo->prepare("
            SELECT f.id, f.name
            FROM farms f
            INNER JOIN staff s ON s.farm_id = f.id
            WHERE f.id = ? AND s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$farm_id, $staff_id]);
        break;
}

/**
 * Fetch farm
 */
$farm = $stmt->fetch(PDO::FETCH_ASSOC);

/**
 * Invalid selection
 */
if (!$farm) {
    http_response_code(400);
    exit('Invalid farm selection');
}

/**
 * Set ACTIVE farm session
 */
$_SESSION['active_farm_id']   = (int)$farm['id'];
$_SESSION['active_farm_name'] = $farm['name'];

/**
 * Redirect to dashboard
 */
header("Location: /yotribe-system/app/modules/dashboard/index.php");
exit;