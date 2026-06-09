<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

/**
 * MODULE ACCESS
 */
require_permission('batches');

/**
 * FARM CONTEXT
 */
$farm_id = farm_id();
$page_title = "Batches";

/**
 * FETCH BATCHES
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM fish_batches
    WHERE farm_id = ?
    ORDER BY id DESC
");
$stmt->execute([$farm_id]);

$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Fish Batches</h4>
        <a href="create.php" class="btn btn-primary btn-sm">
            + New Batch
        </a>
    </div>

    <?php if (empty($batches)): ?>
        <div class="alert alert-info">
            No fish batches found. Create your first batch to begin.
        </div>
    <?php else: ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">

            <thead class="table-dark">
                <tr>
                    <th>Code</th>
                    <th>Species</th>
                    <th>Source</th>
                    <th>Initial</th>
                    <th>Current</th>
                    <th>Avg Weight (g)</th>
                    <th>Stocking Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($batches as $b): ?>

                <?php
                    $status = $b['status'] ?? 'active';

                    $statusClass = match ($status) {
                        'active'   => 'bg-success',
                        'closed'   => 'bg-secondary',
                        'depleted' => 'bg-danger',
                        default    => 'bg-dark'
                    };

                    $current = $b['current_count'] ?? 0;
                    $initial = $b['initial_count'] ?? 0;
                ?>

                <tr>
                    <td><?= htmlspecialchars($b['batch_code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($b['species'] ?? '-') ?></td>
                    <td><?= ucfirst($b['source'] ?? '-') ?></td>

                    <td><?= number_format((int)$initial) ?></td>

                    <td>
                        <strong><?= number_format((int)$current) ?></strong>
                        <?php if ($current < $initial): ?>
                            <small class="text-danger d-block">
                                ↓ Loss detected
                            </small>
                        <?php endif; ?>
                    </td>

                    <td><?= number_format((float)($b['avg_weight_g'] ?? 0), 2) ?></td>

                    <td><?= htmlspecialchars($b['stocking_date'] ?? '-') ?></td>

                    <td>
                        <span class="badge <?= $statusClass ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>

                    <td>
                        <div class="btn-group btn-group-sm">

                            <a href="view.php?id=<?= $b['id'] ?>"
                               class="btn btn-info">
                                View
                            </a>

                            <a href="edit.php?id=<?= $b['id'] ?>"
                               class="btn btn-warning">
                                Edit
                            </a>

                            <?php if ($status === 'active'): ?>
                                <a href="close.php?id=<?= $b['id'] ?>"
                                   class="btn btn-dark">
                                    Close
                                </a>
                            <?php endif; ?>

                        </div>
                    </td>

                </tr>

            <?php endforeach; ?>
            </tbody>

        </table>
    </div>

    <?php endif; ?>

</div>

</body>
</html>