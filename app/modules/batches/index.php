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

/**
 * PAGE TITLE
 */
$page_title = "Batches";


$stmt = $pdo->prepare("
    SELECT * 
    FROM fish_batches
    WHERE farm_id = ?
    ORDER BY id DESC
");
$stmt->execute([$farm_id]);

$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__.'/../../includes/header.php';

require_once __DIR__.'/../../includes/sidebar.php';
?>



<div class="d-flex justify-content-between mb-3">
    <h4>Fish Batches</h4>
    <a href="create.php" class="btn btn-primary btn-sm">+ New Batch</a>
</div>

<table class="table table-bordered">
<thead class="table-dark">
<tr>
    <th>Code</th>
    <th>Species</th>
    <th>Source</th>
    <th>Initial</th>
    <th>Current</th>
    <th>Weight (g)</th>
    <th>Date</th>
    <th>Status</th>
</tr>
</thead>

<tbody>
<?php foreach ($batches as $b): ?>
<tr>
    <td><?= htmlspecialchars($b['batch_code']) ?></td>
    <td><?= htmlspecialchars($b['species']) ?></td>
    <td><?= ucfirst($b['source']) ?></td>
    <td><?= number_format($b['initial_count']) ?></td>
    <td>
        <strong><?= number_format($b['current_count']) ?></strong>
    </td>
    <td><?= number_format($b['avg_weight_g'],2) ?></td>
    <td><?= $b['stocking_date'] ?></td>
    <td>
        <span class="badge bg-success"><?= $b['status'] ?></span>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</body>
</html>