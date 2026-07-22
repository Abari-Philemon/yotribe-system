<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid batch ID");
}

try {

    $pdo->beginTransaction();

    /**
     * VERIFY OWNERSHIP + STATUS
     */
    $stmt = $pdo->prepare("
        SELECT status
        FROM fish_batches
        WHERE id = ? AND farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$id, $farm_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new Exception("Batch not found");
    }

    if ($batch['status'] !== 'active') {
        throw new Exception("Batch is already closed or inactive");
    }

    /**
     * CLOSE BATCH
     */
    $stmt = $pdo->prepare("
        UPDATE fish_batches
        SET status = 'closed'
        WHERE id = ? AND farm_id = ?
    ");
    $stmt->execute([$id, $farm_id]);

    $pdo->commit();

    header("Location: index.php?closed=1");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die("Error: " . $e->getMessage());
}