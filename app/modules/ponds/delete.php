<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = $_SESSION['active_farm_id'] ?? 0;

$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    die('Invalid ID');
}

// Ensure it belongs to current farm
$stmt = $pdo->prepare("
    SELECT id FROM ponds_tanks 
    WHERE id = ? AND farm_id = ?
");
$stmt->execute([$id, $farm_id]);

if (!$stmt->fetch()) {
    die('Unauthorized');
}

// Delete
$stmt = $pdo->prepare("
    DELETE FROM ponds_tanks 
    WHERE id = ? AND farm_id = ?
");
$stmt->execute([$id, $farm_id]);

header("Location: index.php");
exit;