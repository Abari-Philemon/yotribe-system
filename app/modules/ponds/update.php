<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = $_SESSION['active_farm_id'] ?? 0;

$id = (int) ($_POST['id'] ?? 0);

if (!$id) {
    die('Invalid request');
}

// Validate ownership first
$stmt = $pdo->prepare("
    SELECT id FROM ponds_tanks 
    WHERE id = ? AND farm_id = ?
");
$stmt->execute([$id, $farm_id]);

if (!$stmt->fetch()) {
    die('Unauthorized');
}

// Collect inputs
$section_id     = (int) $_POST['section_id'];
$pond_code      = trim($_POST['pond_code']);
$pond_type      = trim($_POST['pond_type']);
$size_label     = trim($_POST['size_label']);
$length_ft      = $_POST['length_ft'] ?: null;
$width_ft       = $_POST['width_ft'] ?: null;
$volume_liters  = $_POST['volume_liters'] ?: null;
$status         = $_POST['status'];

// Update
$stmt = $pdo->prepare("
    UPDATE ponds_tanks SET
        section_id = ?,
        pond_code = ?,
        pond_type = ?,
        size_label = ?,
        length_ft = ?,
        width_ft = ?,
        volume_liters = ?,
        status = ?
    WHERE id = ? AND farm_id = ?
");

$stmt->execute([
    $section_id,
    $pond_code,
    $pond_type,
    $size_label,
    $length_ft,
    $width_ft,
    $volume_liters,
    $status,
    $id,
    $farm_id
]);

header("Location: index.php");
exit;