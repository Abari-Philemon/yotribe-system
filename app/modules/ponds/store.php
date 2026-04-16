<?php
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

$stmt = $pdo->prepare("
INSERT INTO ponds_tanks
(farm_id, section_id, pond_code, pond_type, size_label, capacity, length_ft, width_ft, volume_liters, status)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $farm_id,
    $_POST['section_id'],
    $_POST['pond_code'],
    $_POST['pond_type'],
    $_POST['size_label'],
    $_POST['capacity'],
    $_POST['length_ft'],
    $_POST['width_ft'],
    $_POST['volume_liters'],
    $_POST['status']
]);

header("Location: index.php");