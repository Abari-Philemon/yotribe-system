<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid batch ID");
}

/**
 * FETCH BATCH
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM fish_batches
    WHERE id = ? AND farm_id = ?
");
$stmt->execute([$id, $farm_id]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die("Batch not found");
}

if ($batch['status'] !== 'active') {
    die("Only active batches can be edited");
}

/**
 * UPDATE HANDLER
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $pdo->beginTransaction();

        $species        = trim($_POST['species']);
        $avg_weight     = (float) $_POST['avg_weight_g'];

        // NEW: quantity adjustment (delta-based)
        $adjustment     = (int) ($_POST['adjustment'] ?? 0);
        $reason         = trim($_POST['reason'] ?? '');

        $current_count  = (int)$batch['current_count'];
        $new_count      = $current_count + $adjustment;

        if ($new_count < 0) {
            throw new Exception("Invalid adjustment: stock cannot go below zero");
        }

        /**
         * UPDATE BATCH
         */
        $stmt = $pdo->prepare("
            UPDATE fish_batches
            SET
                species = ?,
                avg_weight_g = ?,
                current_count = ?
            WHERE id = ? AND farm_id = ?
        ");

        $stmt->execute([
            $species,
            $avg_weight,
            $new_count,
            $id,
            $farm_id
        ]);

        /**
         * OPTIONAL: LOG (if you later create audit table)
         */
        // You can plug audit logs here later

        $pdo->commit();

        header("Location: view.php?id=" . $id . "&updated=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Update failed: " . $e->getMessage());
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container mt-4">

    <h4>Edit Batch</h4>

    <div class="alert alert-info">
        Current Stock: <strong><?= number_format($batch['current_count']) ?></strong>
    </div>

    <form method="POST" class="card p-3 mt-3">

        <!-- Species -->
        <div class="mb-3">
            <label>Species</label>
            <input type="text" name="species" class="form-control"
                   value="<?= htmlspecialchars($batch['species']) ?>" required>
        </div>

        <!-- Avg Weight -->
        <div class="mb-3">
            <label>Average Weight (g)</label>
            <input type="number" step="0.01" name="avg_weight_g"
                   class="form-control"
                   value="<?= htmlspecialchars($batch['avg_weight_g']) ?>">
        </div>

        <hr>

        <!-- Quantity Adjustment -->
        <div class="mb-3">
            <label>Stock Adjustment</label>
            <input type="number" name="adjustment" class="form-control"
                   value="0">
            <small class="text-muted">
                Use negative values for mortality (e.g. -50) or positive for additions (e.g. +20)
            </small>
        </div>

        <!-- Reason (optional but useful for audit later) -->
        <div class="mb-3">
            <label>Reason (optional)</label>
            <input type="text" name="reason" class="form-control"
                   placeholder="e.g. mortality, restocking, correction">
        </div>

        <button class="btn btn-primary">Update Batch</button>
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>

    </form>

</div>

</body>
</html>