<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');
require_role(['super_admin','storekeeper','manager','owner']);

$farm_id = farm_id();

/**
 * SUMMARY KPI
 */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(quantity_kg),0) AS total_stock,
        COALESCE(SUM(total_cost),0) AS stock_value,
        COUNT(*) AS total_batches
    FROM feed_store
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

/**
 * LOW STOCK
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM feed_store
    WHERE farm_id = ?
      AND quantity_kg > 0
      AND quantity_kg <= low_stock_level
");
$stmt->execute([$farm_id]);
$low_stock_count = (int)$stmt->fetchColumn();

/**
 * EXPIRING WITHIN 30 DAYS
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM feed_store
    WHERE farm_id = ?
      AND expiry_date IS NOT NULL
      AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      AND status = 'active'
");
$stmt->execute([$farm_id]);
$expiring_count = (int)$stmt->fetchColumn();

/**
 * MAIN STOCK LIST
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM feed_store
    WHERE farm_id = ?
    ORDER BY
        CASE WHEN status='active' THEN 1 ELSE 2 END,
        received_date ASC,
        updated_at DESC
");
$stmt->execute([$farm_id]);
$feeds = $stmt->fetchAll(PDO::FETCH_ASSOC);

function badgeStatus($status){
    if($status === 'active') return 'success';
    if($status === 'finished') return 'secondary';
    if($status === 'expired') return 'danger';
    return 'dark';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Feed Store | Yotribe Agro</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f5f7fb;
}
.sidebar{
    min-height:100vh;
}
.card{
    border:none;
    border-radius:16px;
}
.kpi{
    color:#fff;
}
.kpi h3{
    margin:0;
    font-weight:700;
}
.table thead th{
    white-space:nowrap;
}
.small-muted{
    font-size:13px;
    color:#6c757d;
}
</style>
</head>
<body>

<div class="container-fluid">
<div class="row">

<!-- SIDEBAR -->
<nav class="col-md-2 bg-white shadow-sm sidebar p-3">
    <h5 class="fw-bold mb-4">Yotribe Agro</h5>

    <ul class="nav flex-column gap-2">
        <li><a href="../dashboard/index.php" class="nav-link">Dashboard</a></li>
        <li><a href="../feeding/index.php" class="nav-link">Feeding</a></li>
        <li><a href="index.php" class="nav-link active fw-bold text-primary">Feed Store</a></li>
        <li><a href="received.php" class="nav-link">Receive Feed</a></li>
        <li><a href="issue.php" class="nav-link">Issue Feed</a></li>
        <li><a href="logs.php" class="nav-link">Logs</a></li>
    </ul>
</nav>

<!-- MAIN -->
<main class="col-md-10 p-4">

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="mb-1">Feed Store Inventory</h3>
        <div class="small-muted">Real-time stock position by batch (FIFO ready)</div>
    </div>

    <div class="d-flex gap-2">
        <a href="received.php" class="btn btn-success">+ Receive Feed</a>
        <a href="issue.php" class="btn btn-primary">Issue Feed</a>
        <a href="logs.php" class="btn btn-dark">Logs</a>
    </div>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card bg-primary kpi shadow-sm p-3">
<div>Total Stock</div>
<h3><?= number_format($summary['total_stock'],2) ?> kg</h3>
</div>
</div>

<div class="col-md-3">
<div class="card bg-success kpi shadow-sm p-3">
<div>Inventory Value</div>
<h3>₦<?= number_format($summary['stock_value'],2) ?></h3>
</div>
</div>

<div class="col-md-3">
<div class="card bg-warning text-dark shadow-sm p-3">
<div>Low Stock Batches</div>
<h3><?= $low_stock_count ?></h3>
</div>
</div>

<div class="col-md-3">
<div class="card bg-danger kpi shadow-sm p-3">
<div>Expiring Soon</div>
<h3><?= $expiring_count ?></h3>
</div>
</div>

</div>

<!-- TABLE -->
<div class="card shadow-sm">
<div class="card-body">

<div class="table-responsive">
<table class="table table-hover align-middle">

<thead class="table-dark">
<tr>
<th>#</th>
<th>Feed Type</th>
<th>Batch No</th>
<th>Qty (kg)</th>
<th>Bags</th>
<th>Bag Size</th>
<th>Cost/Kg</th>
<th>Total Cost</th>
<th>Supplier</th>
<th>Received</th>
<th>Expiry</th>
<th>Status</th>
<th>Alerts</th>
</tr>
</thead>

<tbody>

<?php if($feeds): ?>
<?php foreach($feeds as $i => $f): ?>

<?php
$alertText = [];

if ($f['quantity_kg'] <= $f['low_stock_level'] && $f['quantity_kg'] > 0) {
    $alertText[] = 'Low Stock';
}

if (!empty($f['expiry_date']) && $f['expiry_date'] <= date('Y-m-d', strtotime('+30 days'))) {
    $alertText[] = 'Near Expiry';
}

if ($f['quantity_kg'] <= 0 && $f['status'] === 'active') {
    $alertText[] = 'Should Finish';
}
?>

<tr>
<td><?= $i + 1 ?></td>

<td>
<strong><?= htmlspecialchars($f['feed_type']) ?></strong>
</td>

<td><?= htmlspecialchars($f['batch_no']) ?></td>

<td>
<?= number_format($f['quantity_kg'],2) ?>
</td>

<td><?= (int)$f['bag_count'] ?></td>

<td><?= number_format($f['bag_weight_kg'],2) ?> kg</td>

<td>₦<?= number_format($f['cost_per_kg'],2) ?></td>

<td>₦<?= number_format($f['total_cost'],2) ?></td>

<td><?= htmlspecialchars($f['supplier_name']) ?></td>

<td><?= $f['received_date'] ?></td>

<td>
<?= $f['expiry_date'] ? $f['expiry_date'] : '-' ?>
</td>

<td>
<span class="badge bg-<?= badgeStatus($f['status']) ?>">
<?= ucfirst($f['status']) ?>
</span>
</td>

<td>
<?php if($alertText): ?>
<?php foreach($alertText as $a): ?>
<span class="badge bg-danger me-1"><?= $a ?></span>
<?php endforeach; ?>
<?php else: ?>
<span class="text-muted">OK</span>
<?php endif; ?>
</td>

</tr>

<?php endforeach; ?>
<?php else: ?>

<tr>
<td colspan="13" class="text-center text-muted py-4">
No feed inventory found.
</td>
</tr>

<?php endif; ?>

</tbody>
</table>
</div>

</div>
</div>

</main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>