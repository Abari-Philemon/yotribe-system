<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$farm_id = farm_id();

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    /**
     * INPUT SANITIZATION
     */
    $batch_code     = trim($_POST['batch_code'] ?? '');
    $source         = $_POST['source'] ?? 'purchase';
    $species        = trim($_POST['species'] ?? 'catfish');
    $initial_count  = (int) ($_POST['initial_count'] ?? 0);
    $avg_weight     = (float) ($_POST['avg_weight_g'] ?? 0);
    $stocking_date  = $_POST['stocking_date'] ?? null;

    /**
     * VALIDATION
     */
    if ($batch_code === '') {
        throw new Exception("Batch code is required");
    }

    if ($initial_count <= 0) {
        throw new Exception("Initial fish count must be greater than 0");
    }

    if (!$stocking_date) {
        throw new Exception("Stocking date is required");
    }

    /**
     * START TRANSACTION
     */
    $pdo->beginTransaction();

    /**
     * DUPLICATE CHECK (SAFE UNDER TRANSACTION)
     */
    $stmt = $pdo->prepare("
        SELECT id 
        FROM fish_batches 
        WHERE batch_code = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$batch_code, $farm_id]);

    if ($stmt->fetch()) {
        throw new Exception("Batch code already exists");
    }

    /**
     * INSERT BATCH
     */
    $stmt = $pdo->prepare("
        INSERT INTO fish_batches
        (
            farm_id,
            batch_code,
            source,
            species,
            initial_count,
            current_count,
            avg_weight_g,
            stocking_date,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");

    $stmt->execute([
        $farm_id,
        $batch_code,
        $source,
        $species,
        $initial_count,
        $initial_count,   // IMPORTANT: initial = current at creation
        $avg_weight,
        $stocking_date
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Batch created successfully',
        'data' => [
            'batch_code' => $batch_code
        ]
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}