<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/pond_code_helper.php';

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

    if ($qty <= 0 || $qty > 500) {
        throw new Exception("Invalid quantity (1–500 allowed)");
    }

    /**
     * VALIDATE RELATION
     */
    $stmt = $pdo->prepare("
        SELECT id FROM sub_sections
        WHERE id = ? AND section_id = ? AND farm_id = ?
    ");
    $stmt->execute([$sub_section_id, $section_id, $farm_id]);

    if (!$stmt->fetch()) {
        throw new Exception("Invalid section/sub-section mapping");
    }

    /**
     * BULK INSERT LOOP
     */
    for ($i = 0; $i < $qty; $i++) {

        $success = false;

        for ($retry = 0; $retry < 5; $retry++) {

            try {

                $code = generatePondCode($pdo, $farm_id, $section_id, $sub_section_id);

                $stmt = $pdo->prepare("
                    INSERT INTO ponds_tanks
                    (farm_id, section_id, sub_section_id, pond_code, pond_type, capacity, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");

                $stmt->execute([
                    $farm_id,
                    $section_id,
                    $sub_section_id,
                    $code,
                    $pond_type,
                    $capacity
                ]);

                $success = true;
                break;

            } catch (PDOException $e) {

                if ($e->errorInfo[1] == 1062) {
                    continue; // retry duplicate
                }

                throw $e;
            }
        }

        if (!$success) {
            throw new Exception("Failed generating unique pond code");
        }
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