<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

if (!in_array($_SESSION['role'], ['owner','super_admin'])) {
    http_response_code(403);
    exit;
}

$farm_id = (int) ($_POST['farm_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, name FROM farms WHERE id = ?");
$stmt->execute([$farm_id]);
$farm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$farm) {
    die('Invalid farm');
}

$_SESSION['active_farm_id']   = $farm['id'];
$_SESSION['active_farm_name'] = $farm['name'];

header("Location: /yotribe-system/app/modules/dashboard/index.php");
