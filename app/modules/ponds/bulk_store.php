<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

try {

    $pdo->beginTransaction();

    /**
     * INPUTS
     */
    $section_id     = (int) $_POST['section_id'];
    $sub_section_id = (int) $_POST['sub_section_id'];
    $qty            = (int) $_POST['quantity'];
    $capacity       = (int) $_POST['capacity'];
    $pond_type      = $_POST['pond_type'] ?? 'tank';

    $size_label     = trim($_POST['size_label'] ?? '');
    $length_ft      = $_POST['length_ft'] !== '' ? (float)$_POST['length_ft'] : null;
    $width_ft       = $_POST['width_ft'] !== '' ? (float)$_POST['width_ft'] : null;
    $volume_liters  = $_POST['volume_liters'] !== '' ? (float)$_POST['volume_liters'] : null;

    /**
     * VALIDATION
     */
    if ($qty <= 0 || $qty > 500) {
        throw new Exception("Quantity must be between 1–500");
    }

    if ($capacity <= 0) {
        throw new Exception("Capacity must be > 0");
    }

    /**
     * VALIDATE SUBSECTION
     */
    $stmt = $pdo->prepare("
        SELECT code 
        FROM sub_sections
        WHERE id = ? AND section_id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$sub_section_id, $section_id, $farm_id]);

    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sub) {
        throw new Exception("Invalid sub-section");
    }

    $prefix = $sub['code'];

    /**
     * GET LAST SEQUENCE
     */
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(pond_code, '-', -1) AS UNSIGNED))
        FROM ponds_tanks
        WHERE farm_id = ?
        AND sub_section_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$farm_id, $sub_section_id]);

    $last_seq = (int)$stmt->fetchColumn();
    $start = $last_seq + 1;

    /**
     * INSERT
     */
    $stmt = $pdo->prepare("
        INSERT INTO ponds_tanks
        (farm_id, section_id, sub_section_id, pond_code, pond_type, size_label, length_ft, width_ft, volume_liters, capacity, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    for ($i = 0; $i < $qty; $i++) {

        $seq = str_pad($start + $i, 2, '0', STR_PAD_LEFT);
        $pond_code = "{$prefix}-{$seq}";

        $stmt->execute([
            $farm_id,
            $section_id,
            $sub_section_id,
            $pond_code,
            $pond_type,
            $size_label ?: null,
            $length_ft,
            $width_ft,
            $volume_liters,
            $capacity
        ]);
    }

    $pdo->commit();

    header("Location: index.php?bulk=success");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("Bulk Error: " . $e->getMessage());
}