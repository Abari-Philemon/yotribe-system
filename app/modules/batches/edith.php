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
 * UPDATE LOGIC
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $pdo->beginTransaction();

        $species       = trim($_POST['species']);
        $avg_weight    = (float)$_POST['avg_weight_g'];

        $stmt = $pdo->prepare("
            UPDATE fish_batches
            SET species = ?, avg_weight_g = ?
            WHERE id = ? AND farm_id = ?
        ");

        $stmt->execute([$species, $avg_weight, $id, $farm_id]);

        $pdo->commit();

        header("Location: view.php?id=" . $id);
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

    <form method="POST" class="card p-3 mt-3">

        <div class="mb-3">
            <label>Species</label>
            <input type="text" name="species" class="form-control"
                   value="<?= htmlspecialchars($batch['species']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Average Weight (g)</label>
            <input type="number" step="0.01" name="avg_weight_g"
                   class="form-control"
                   value="<?= htmlspecialchars($batch['avg_weight_g']) ?>">
        </div>

        <button class="btn btn-primary">Update</button>
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>

    </form>

</div>

</body>
</html>