<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

/**
 * VALIDATE REQUEST METHOD
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request');
}

/**
 * SANITIZE INPUT
 */
$pond_code     = trim($_POST['pond_code'] ?? '');
$section_id    = (int)($_POST['section_id'] ?? 0);
$sub_section_id= (int)($_POST['sub_section_id'] ?? 0);
$pond_type     = trim($_POST['pond_type'] ?? '');
$size_label    = trim($_POST['size_label'] ?? '');
$length_ft     = (float)($_POST['length_ft'] ?? 0);
$width_ft      = (float)($_POST['width_ft'] ?? 0);
$volume_liters = (float)($_POST['volume_liters'] ?? 0);
$capacity      = (int)($_POST['capacity'] ?? 0);
$status        = $_POST['status'] ?? 'active';

/**
 * BASIC VALIDATION
 */
if ($pond_code === '' || $section_id <= 0 || $sub_section_id <= 0 || $capacity <= 0) {
    die('Missing required fields');
}

/**
 * ENUM VALIDATION
 */
$valid_types = ['tank', 'tarpaulin'];
if (!in_array($pond_type, $valid_types)) {
    die('Invalid pond type');
}

$valid_status = ['active', 'inactive', 'maintenance'];
if (!in_array($status, $valid_status)) {
    die('Invalid status');
}

/**
 * START TRANSACTION
 */
$pdo->beginTransaction();

try {

    /**
     * 1. VERIFY SECTION BELONGS TO FARM
     */
    $stmt = $pdo->prepare("
        SELECT id FROM sections
        WHERE id = ? AND farm_id = ?
    ");
    $stmt->execute([$section_id, $farm_id]);

    if (!$stmt->fetch()) {
        throw new Exception("Invalid section for this farm");
    }

    /**
     * 2. VERIFY SUB-SECTION BELONGS TO SECTION + FARM
     */
    $stmt = $pdo->prepare("
        SELECT id FROM sub_sections
        WHERE id = ? AND section_id = ? AND farm_id = ?
    ");
    $stmt->execute([$sub_section_id, $section_id, $farm_id]);

    if (!$stmt->fetch()) {
        throw new Exception("Invalid sub-section mapping");
    }

    /**
     * 3. CHECK DUPLICATE POND CODE IN SAME FARM
     */
    $stmt = $pdo->prepare("
        SELECT id FROM ponds_tanks
        WHERE pond_code = ? AND farm_id = ?
    ");
    $stmt->execute([$pond_code, $farm_id]);

    if ($stmt->fetch()) {
        throw new Exception("Pond code already exists in this farm");
    }

    /**
     * 4. AUTO CALCULATE VOLUME (if not provided)
     */
    if ($volume_liters <= 0 && $length_ft > 0 && $width_ft > 0) {
        // simple estimation: ft³ → liters
        $volume_liters = ($length_ft * $width_ft * 3) * 28.3168;
    }

    /**
     * 5. INSERT POND
     */
    $stmt = $pdo->prepare("
        INSERT INTO ponds_tanks
        (farm_id, section_id, sub_section_id, pond_code, pond_type, size_label,
         capacity, length_ft, width_ft, volume_liters, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $farm_id,
        $section_id,
        $sub_section_id,
        $pond_code,
        $pond_type,
        $size_label,
        $capacity,
        $length_ft ?: null,
        $width_ft ?: null,
        $volume_liters ?: null,
        $status
    ]);

    $pdo->commit();

    header("Location: index.php?success=1");
    exit;

} catch (Exception $e) {

    $pdo->rollBack();

    echo "<h3>Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='create.php'>Go Back</a>";
}