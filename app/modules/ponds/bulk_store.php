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

    $prefix = $sub['code']; // e.g GO-03A

    /**
     * GET LAST USED SEQUENCE (LOCKED)
     */
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(pond_code, '-', -1) AS UNSIGNED)) AS last_seq
        FROM ponds_tanks
        WHERE farm_id = ?
        AND sub_section_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$farm_id, $sub_section_id]);

    $last_seq = (int) $stmt->fetchColumn();

    $start = $last_seq + 1;

    /**
     * INSERT LOOP (NO GENERATOR NEEDED)
     */
    $stmt = $pdo->prepare("
        INSERT INTO ponds_tanks
        (farm_id, section_id, sub_section_id, pond_code, pond_type, capacity, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
    ");

    for ($i = 0; $i < $qty; $i++) {

        $seq = $start + $i;
        $seqFormatted = str_pad($seq, 2, '0', STR_PAD_LEFT);

        $pond_code = "{$prefix}-{$seqFormatted}";

        $stmt->execute([
            $farm_id,
            $section_id,
            $sub_section_id,
            $pond_code,
            $pond_type,
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