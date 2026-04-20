<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/pond_code_helper.php';

$section_id     = (int) $_POST['section_id'];
$sub_section_id = (int) $_POST['sub_section_id'];

$pond_code = generatePondCode($pdo, $farm_id, $section_id, $sub_section_id);

$farm_id = farm_id();

try {

    $pdo->beginTransaction();

    /**
     * INPUTS
     */
    $section_id     = (int) $_POST['section_id'];
    $sub_section_id = (int) $_POST['sub_section_id'];
    $pond_type      = $_POST['pond_type'] ?? 'tank';
    $size_label     = $_POST['size_label'] ?? null;
    $length_ft      = $_POST['length_ft'] ?: null;
    $width_ft       = $_POST['width_ft'] ?: null;
    $volume_liters  = $_POST['volume_liters'] ?: null;
    $capacity       = (int) $_POST['capacity'];
    $status         = $_POST['status'] ?? 'active';

    /**
     * VALIDATE SECTION
     */
    $stmt = $pdo->prepare("
        SELECT code FROM sections 
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$section_id, $farm_id]);
    $section = $stmt->fetch();

    if (!$section) {
        throw new Exception("Invalid section");
    }

    $section_code = strtoupper($section['code']);

    /**
     * VALIDATE SUB-SECTION
     */
    $stmt = $pdo->prepare("
        SELECT id FROM sub_sections
        WHERE id = ? AND section_id = ? AND farm_id = ?
    ");
    $stmt->execute([$sub_section_id, $section_id, $farm_id]);

    if (!$stmt->fetch()) {
        throw new Exception("Invalid sub-section selection");
    }

    /**
     * FARM SUFFIX
     */
    $farm_suffix = ($farm_id == 1) ? 'A' : 'B';

    /**
     * LOCK + GET LAST SEQUENCE
     */
    $stmt = $pdo->prepare("
        SELECT pond_code 
        FROM ponds_tanks
        WHERE farm_id = ? 
          AND section_id = ?
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$farm_id, $section_id]);
    $last = $stmt->fetch();

    $next_number = 1;

    if ($last && preg_match('/-(\d+)/', $last['pond_code'], $m)) {
        $next_number = (int)$m[1] + 1;
    }

    /**
     * BUILD CODE LOOP (RETRY SAFE)
     */
    do {

        $sequence = str_pad($next_number, 2, '0', STR_PAD_LEFT);

        $pond_code = "{$section_code}-{$sequence}{$farm_suffix}{$sub_code}";

        // check existence
        $stmt = $pdo->prepare("
            SELECT id FROM ponds_tanks WHERE pond_code = ?
        ");
        $stmt->execute([$pond_code]);

        $exists = $stmt->fetch();

        if ($exists) {
            $next_number++;
        }

    } while ($exists);

    /**
     * INSERT
     */
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
        $size_label,
        $length_ft,
        $width_ft,
        $volume_liters,
        $capacity,
        $status
    ]);

    $pdo->commit();

    header("Location: index.php?success=1");
    exit;

} catch (Exception $e) {

    $pdo->rollBack();

    die("Error: " . $e->getMessage());
}