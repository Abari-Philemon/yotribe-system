<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$allowed_roles = ['owner','super_admin','manager'];

if (!in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    exit('Unauthorized');
}

if (!isset($_POST['farm_id'])) {
    exit('Invalid request');
}

$farm_id = (int) $_POST['farm_id'];

/**
 * Verify farm belongs to user
 */
$stmt = $pdo->prepare("
    SELECT f.id, f.name
    FROM farms f
    JOIN staff_farms sf ON sf.farm_id = f.id
    WHERE sf.staff_id = ?
      AND f.id = ?
      AND f.status = 'active'
");
$stmt->execute([$_SESSION['staff_id'], $farm_id]);
$farm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$farm) {
    exit('Farm access denied');
}

/**
 * Switch context
 */
$_SESSION['farm_id']   = $farm['id'];
$_SESSION['farm_name'] = $farm['name'];

header("Location: index.php");
exit;
