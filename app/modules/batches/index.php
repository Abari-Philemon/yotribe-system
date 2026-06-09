<?php
// ===============================
// DEBUG MODE (REMOVE AFTER FIX)
// ===============================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===============================
// STEP TRACKER (FOR 500 DEBUGGING)
// ===============================
function step($msg) {
    echo "✔ " . $msg . "<br>";
}

step("STEP 1: PHP LOADED");

require_once __DIR__ . '/../../middleware/auth_guard.php';
step("STEP 2: AUTH GUARD LOADED");

require_once __DIR__ . '/../../middleware/farm_guard.php';
step("STEP 3: FARM GUARD LOADED");

require_once __DIR__ . '/../../config/database.php';
step("STEP 4: DATABASE LOADED");

require_once __DIR__ . '/../../helpers/permission.php';
step("STEP 5: PERMISSION FILE LOADED");

// ===============================
// PERMISSION CHECK (LIKELY FAILURE POINT)
// ===============================
require_permission('batches');
step("STEP 6: PERMISSION PASSED");

// ===============================
// FARM CONTEXT
// ===============================
$farm_id = farm_id();
step("STEP 7: FARM ID = " . htmlspecialchars($farm_id));

// ===============================
// SQL FETCH
// ===============================
$stmt = $pdo->prepare("
    SELECT *
    FROM fish_batches
    WHERE farm_id = ?
    ORDER BY id DESC
");

$stmt->execute([$farm_id]);
step("STEP 8: SQL EXECUTED");

$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
step("STEP 9: DATA FETCHED");

// ===============================
// SHOW RAW DATA FIRST (DEBUG)
// ===============================
echo "<hr><pre>";
print_r($batches);
echo "</pre>";

// ===============================
// STOP EXECUTION (DEBUG MODE)
// ===============================
exit;

// ===============================
// NORMAL UI (WILL RUN AFTER DEBUG FIX)
// ===============================
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between mb-3">
        <h4>Fish Batches</h4>
        <a href="create.php" class="btn btn-primary btn-sm">+ New Batch</a>
    </div>

    <?php if (empty($batches)): ?>
        <div class="alert alert-info">
            No fish batches found.
        </div>
    <?php else: ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">

            <thead class="table-dark">
                <tr>
                    <th>Code</th>
                    <th>Species</th>
                    <th>Source</th>
                    <th>Initial</th>
                    <th>Current</th>
                    <th>Avg Weight</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($batches as $b): ?>

                <?php
                    $status = $b['status'] ?? 'active';

                    $badge = match ($status) {
                        'active' => 'bg-success',
                        'closed' => 'bg-secondary',
                        'depleted' => 'bg-danger',
                        default => 'bg-dark'
                    };
                ?>

                <tr>
                    <td><?= htmlspecialchars($b['batch_code']) ?></td>
                    <td><?= htmlspecialchars($b['species']) ?></td>
                    <td><?= ucfirst($b['source']) ?></td>
                    <td><?= number_format($b['initial_count']) ?></td>
                    <td><?= number_format($b['current_count']) ?></td>
                    <td><?= number_format($b['avg_weight_g'], 2) ?></td>
                    <td><?= htmlspecialchars($b['stocking_date']) ?></td>
                    <td>
                        <span class="badge <?= $badge ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>

                    <td>
                        <a href="view.php?id=<?= $b['id'] ?>" class="btn btn-info btn-sm">View</a>
                        <a href="edith.php?id=<?= $b['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="close.php?id=<?= $b['id'] ?>" class="btn btn-dark btn-sm">Close</a>
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