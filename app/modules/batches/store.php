<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

try {

    $batch_code     = trim($_POST['batch_code']);
    $source         = $_POST['source'] ?? 'purchase';
    $species        = trim($_POST['species'] ?? 'catfish');
    $initial_count  = (int) $_POST['initial_count'];
    $avg_weight     = (float) ($_POST['avg_weight_g'] ?? 0);
    $stocking_date  = $_POST['stocking_date'];

    /**
     * VALIDATION
     */
    if ($initial_count <= 0) {
        throw new Exception("Initial count must be greater than 0");
    }

    /**
     * PREVENT DUPLICATE BATCH CODE
     */
    $stmt = $pdo->prepare("
        SELECT id FROM fish_batches 
        WHERE batch_code = ? AND farm_id = ?
    ");
    $stmt->execute([$batch_code, $farm_id]);

    if ($stmt->fetch()) {
        throw new Exception("Batch code already exists");
    }

    /**
     * INSERT
     * NOTE: current_count = initial_count (VERY IMPORTANT)
     */
    $stmt = $pdo->prepare("
        INSERT INTO fish_batches
        (farm_id, batch_code, source, species, initial_count, current_count, avg_weight_g, stocking_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $farm_id,
        $batch_code,
        $source,
        $species,
        $initial_count,
        $initial_count, // <-- CRITICAL
        $avg_weight,
        $stocking_date
    ]);

    header("Location: index.php?success=1");
    exit;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}