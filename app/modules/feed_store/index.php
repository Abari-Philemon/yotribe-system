<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');
require_role(['super_admin','storekeeper','manager','owner']);

$farm_id = farm_id();

/**
 * KPI SUMMARY
 */
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(quantity_kg),0) total_stock,
        COALESCE(SUM(quantity_kg * cost_per_kg),0) stock_value,
        COUNT(*) total_batches
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
$low_stock = (int)$stmt->fetchColumn();

/**
 * EXPIRING
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM feed_store
    WHERE farm_id = ?
    AND expiry_date IS NOT NULL
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND status='active'
");
$stmt->execute([$farm_id]);
$expiring = (int)$stmt->fetchColumn();

/**
 * STOCK LIST
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM feed_store
    WHERE farm_id = ?
    ORDER BY
        CASE WHEN status='active' THEN 1 ELSE 2 END,
        received_date ASC,
        id DESC
");
$stmt->execute([$farm_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function statusColor($status){
    return match($status){
        'active'   => 'success',
        'finished' => 'secondary',
        'expired'  => 'danger',
        default    => 'dark'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Feed Store | Yotribe Agro</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
    --primary:#0d6efd;
    --dark:#111827;
    --muted:#6b7280;
    --card:#ffffff;
    --bg:#f4f7fb;
    --line:#e5e7eb;
}

body{
    background:var(--bg);
    font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;
    color:var(--dark);
}

.sidebar{
    min-height:100vh;
    background:#fff;
    border-right:1px solid var(--line);
}

.brand{
    font-weight:800;
    letter-spacing:.4px;
}

.nav-link{
    color:#374151;
    border-radius:10px;
    padding:10px 14px;
}

.nav-link:hover{
    background:#eef4ff;
    color:var(--primary);
}

.nav-link.active{
    background:linear-gradient(135deg,#0d6efd,#084298);
    color:#fff !important;
}

.page-title{
    font-weight:800;
    font-size:28px;
}

.sub-title{
    color:var(--muted);
    font-size:14px;
}

.glass-card{
    background:rgba(255,255,255,.9);
    border:1px solid rgba(255,255,255,.8);
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.06);
}

.kpi-card{
    padding:20px;
    border-radius:18px;
    color:#fff;
    min-height:120px;
}

.kpi-card small{
    opacity:.9;
    font-size:13px;
}

.kpi-card h3{
    margin-top:10px;
    font-weight:800;
}

.kpi-1{background:linear-gradient(135deg,#0d6efd,#0a58ca);}
.kpi-2{background:linear-gradient(135deg,#198754,#157347);}
.kpi-3{background:linear-gradient(135deg,#f59e0b,#d97706);}
.kpi-4{background:linear-gradient(135deg,#dc3545,#b02a37);}

.table-wrap{
    border-radius:18px;
    overflow:hidden;
}

.table thead th{
    background:#111827;
    color:#fff;
    font-size:13px;
    border:none;
    white-space:nowrap;
}

.table tbody td{
    vertical-align:middle;
    border-color:#f1f1f1;
}

.badge-soft{
    padding:7px 10px;
    border-radius:30px;
}

.search-box{
    border-radius:12px;
    padding:10px 14px;
}

.top-actions .btn{
    border-radius:12px;
    padding:10px 16px;
}

.stock-number{
    font-weight:700;
}

.feed-name{
    font-weight:700;
}

.muted{
    color:var(--muted);
    font-size:12px;
}
</style>
</head>
<body>

<div class="container-fluid">
<div class="row">

<!-- SIDEBAR -->
<div class="col-md-2 sidebar p-4">

<div class="brand fs-4 mb-4">Yotribe Agro</div>

<ul class="nav flex-column gap-2">
<li><a href="../dashboard/index.php" class="nav-link">Dashboard</a></li>
<li><a href="../feeding/index.php" class="nav-link">Feeding</a></li>
<li><a href="index.php" class="nav-link active">Feed Store</a></li>
<li><a href="received.php" class="nav-link">Receive Feed</a></li>
<li><a href="issue.php" class="nav-link">Issue Feed</a></li>
<li><a href="logs.php" class="nav-link">Logs</a></li>
</ul>

</div>

<!-- MAIN -->
<div class="col-md-10 p-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
<div>
<div class="page-title">Feed Store Control Center</div>
<div class="sub-title">Inventory intelligence, FIFO readiness, batch visibility and stock alerts.</div>
</div>

<div class="top-actions d-flex gap-2">
<a href="received.php" class="btn btn-success">+ Receive Feed</a>
<a href="issue.php" class="btn btn-primary">Issue Feed</a>
<a href="logs.php" class="btn btn-dark">View Logs</a>
</div>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="kpi-card kpi-1">
<small>Total Stock</small>
<h3><?= number_format($summary['total_stock'],2) ?> kg</h3>
</div>
</div>

<div class="col-md-3">
<div class="kpi-card kpi-2">
<small>Inventory Value</small>
<h3>₦<?= number_format($summary['stock_value'],2) ?></h3>
</div>
</div>

<div class="col-md-3">
<div class="kpi-card kpi-3 text-dark">
<small>Low Stock Alerts</small>
<h3><?= $low_stock ?></h3>
</div>
</div>

<div class="col-md-3">
<div class="kpi-card kpi-4">
<small>Expiring Soon</small>
<h3><?= $expiring ?></h3>
</div>
</div>

</div>

<!-- TABLE CARD -->
<div class="glass-card p-4">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
<h5 class="mb-0 fw-bold">Current Batch Inventory</h5>

<input type="text" id="searchInput" class="form-control search-box" placeholder="Search feed / batch / supplier..." style="max-width:320px;">
</div>

<div class="table-responsive table-wrap">

<table class="table table-hover align-middle mb-0" id="stockTable">

<thead>
<tr>
<th>Feed</th>
<th>Batch</th>
<th>Stock</th>
<th>Bags</th>
<th>Cost</th>
<th>Supplier</th>
<th>Received</th>
<th>Expiry</th>
<th>Status</th>
<th>Alert</th>
</tr>
</thead>

<tbody>

<?php foreach($rows as $r): ?>

<?php
$alert = 'OK';
$alertClass = 'success';

if ($r['quantity_kg'] <= $r['low_stock_level'] && $r['quantity_kg'] > 0){
    $alert = 'Low Stock';
    $alertClass = 'warning';
}

if (!empty($r['expiry_date']) && $r['expiry_date'] <= date('Y-m-d', strtotime('+30 days'))){
    $alert = 'Near Expiry';
    $alertClass = 'danger';
}

if ($r['status'] === 'expired'){
    $alert = 'Expired';
    $alertClass = 'danger';
}
?>

<tr>
<td>
<div class="feed-name"><?= htmlspecialchars($r['feed_type']) ?></div>
<div class="muted"><?= number_format($r['bag_weight_kg'],2) ?>kg bags</div>
</td>

<td>
<strong><?= htmlspecialchars($r['batch_no']) ?></strong>
</td>

<td class="stock-number">
<?= number_format($r['quantity_kg'],2) ?> kg
</td>

<td>
<?= (int)$r['bag_count'] ?>
</td>

<td>
₦<?= number_format($r['cost_per_kg'],2) ?>/kg
<div class="muted">₦<?= number_format($r['total_cost'],2) ?></div>
</td>

<td><?= htmlspecialchars($r['supplier_name']) ?></td>

<td><?= $r['received_date'] ?></td>

<td><?= $r['expiry_date'] ?: '-' ?></td>

<td>
<span class="badge bg-<?= statusColor($r['status']) ?> badge-soft">
<?= ucfirst($r['status']) ?>
</span>
</td>

<td>
<span class="badge bg-<?= $alertClass ?> badge-soft">
<?= $alert ?>
</span>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</div>

</div>
</div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function () {
    const value = this.value.toLowerCase();
    const rows = document.querySelectorAll('#stockTable tbody tr');

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
});
</script>

</body>
</html>