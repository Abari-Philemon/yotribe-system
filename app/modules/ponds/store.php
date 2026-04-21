<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/pond_code_helper.php';

$farm_id = farm_id();

try {

    $pdo->beginTransaction();

    /**
     * INPUTS (SANITIZED)
     */
    $section_id     = (int) ($_POST['section_id'] ?? 0);
    $sub_section_id = (int) ($_POST['sub_section_id'] ?? 0);
    $pond_type      = $_POST['pond_type'] ?? 'tank';
    $size_label     = trim($_POST['size_label'] ?? '');
    $length_ft      = $_POST['length_ft'] !== '' ? (float)$_POST['length_ft'] : null;
    $width_ft       = $_POST['width_ft'] !== '' ? (float)$_POST['width_ft'] : null;
    $volume_liters  = $_POST['volume_liters'] !== '' ? (float)$_POST['volume_liters'] : null;
    $capacity       = (int) ($_POST['capacity'] ?? 0);
    $status         = $_POST['status'] ?? 'active';

    /**
     * BASIC VALIDATION
     */
    if (!$section_id || !$sub_section_id) {
        throw new Exception("Section and Sub-section are required");
    }

    if ($capacity <= 0) {
        throw new Exception("Capacity must be greater than 0");
    }

    /**
     * VALIDATE SECTION (LOCKED)
     */
    $stmt = $pdo->prepare("
        SELECT id, code 
        FROM sections 
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$section_id, $farm_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        throw new Exception("Invalid section");
    }

    /**
     * VALIDATE SUB-SECTION
     */
    $stmt = $pdo->prepare("
        SELECT id, code 
        FROM sub_sections
        WHERE id = ? AND section_id = ? AND farm_id = ?
    ");
    $stmt->execute([$sub_section_id, $section_id, $farm_id]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sub) {
        throw new Exception("Invalid sub-section");
    }

    /**
     * GENERATE + INSERT (RETRY SAFE)
     */
    $inserted = false;

    for ($i = 0; $i < 5; $i++) {

        try {

            // Generate code
            $pond_code = generatePondCode($pdo, $farm_id, $section_id, $sub_section_id);

            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO ponds_tanks
                (farm_id, section_id, sub_section_id, pond_code, pond_type, size_label, length_ft, width_ft, volume_liters, capacity, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

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
                $capacity,
                $status
            ]);

            $inserted = true;
            break;

        } catch (PDOException $e) {

            // 1062 = duplicate key (race condition)
            if ($e->errorInfo[1] == 1062) {
                continue; // retry
            }

            throw $e;
        }
    }

    if (!$inserted) {
        throw new Exception("Failed to generate unique pond code after retries");
    }

    $pdo->commit();

    header("Location: index.php?success=1");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("Error: " . $e->getMessage());
}