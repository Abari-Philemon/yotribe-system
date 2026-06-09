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
 * FETCH BATCH (FARM-SAFE)
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

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$status = $batch['status'] ?? 'active';
?>

<div class="container mt-4">

    <h4>Batch Details</h4>

    <div class="card p-3 mt-3">

        <h5><?= htmlspecialchars($batch['batch_code']) ?></h5>

        <p><strong>Species:</strong> <?= htmlspecialchars($batch['species']) ?></p>
        <p><strong>Source:</strong> <?= ucfirst($batch['source']) ?></p>
        <p><strong>Stocking Date:</strong> <?= htmlspecialchars($batch['stocking_date']) ?></p>

        <hr>

        <p><strong>Initial Count:</strong> <?= number_format($batch['initial_count']) ?></p>
        <p><strong>Current Count:</strong> <?= number_format($batch['current_count']) ?></p>
        <p><strong>Avg Weight:</strong> <?= number_format($batch['avg_weight_g'], 2) ?> g</p>

        <hr>

        <p>
            <strong>Status:</strong>
            <span class="badge bg-<?= $status === 'active' ? 'success' : ($status === 'closed' ? 'secondary' : 'danger') ?>">
                <?= ucfirst($status) ?>
            </span>
        </p>

        <?php if ($batch['current_count'] < $batch['initial_count']): ?>
            <div class="alert alert-warning">
                Stock loss detected: <?= $batch['initial_count'] - $batch['current_count'] ?> fish
            </div>
        <?php endif; ?>

        <div class="mt-3">
            <a href="edit.php?id=<?= $batch['id'] ?>" class="btn btn-warning">Edit</a>

            <?php if ($status === 'active'): ?>
                <a href="close.php?id=<?= $batch['id'] ?>" class="btn btn-dark">Close Batch</a>
            <?php endif; ?>

            <a href="index.php" class="btn btn-secondary">Back</a>
        </div>

    </div>

</div>

</body>
</html>