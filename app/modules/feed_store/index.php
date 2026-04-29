<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');
require_role(['super_admin','storekeeper','manager','owner']);

/**
 * UNIVERSAL STORE
 * feed_store.farm_id = purchasing / owning farm
 */

$current_farm_id = farm_id();

/**
 * KPI SUMMARY (ALL STOCK)
 */
$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(quantity_kg),0) total_stock,
        COALESCE(SUM(quantity_kg * cost_per_kg),0) stock_value,
        COUNT(*) total_batches
    FROM feed_store
    WHERE status IN ('active','finished')
");
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

/**
 * LOW STOCK
 */
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM feed_store
    WHERE quantity_kg > 0
    AND quantity_kg <= low_stock_level
");
$low_stock = (int)$stmt->fetchColumn();

/**
 * EXPIRING
 */
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM feed_store
    WHERE expiry_date IS NOT NULL
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND quantity_kg > 0
    AND status='active'
");
$expiring = (int)$stmt->fetchColumn();

/**
 * FARMS
 */
$stmt = $pdo->query("
    SELECT id,name
    FROM farms
    ORDER BY name
");
$farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * STOCK LIST
 */
$stmt = $pdo->query("
    SELECT fs.*, f.name farm_name
    FROM feed_store fs
    LEFT JOIN farms f ON f.id = fs.farm_id
    ORDER BY
        CASE WHEN fs.status='active' THEN 1 ELSE 2 END,
        fs.received_date ASC,
        fs.id ASC
");
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
<title>Feed Store Control Center</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root{
--bg:#eef2f7;
--card:#ffffff;
--line:#e5e7eb;
--text:#0f172a;
--muted:#64748b;
}

body{
background:var(--bg);
font-family:Inter,Segoe UI,Arial,sans-serif;
color:var(--text);
}

.sidebar{
min-height:100vh;
background:#fff;
border-right:1px solid var(--line);
}

.brand{
font-weight:800;
font-size:24px;
}

.nav-link{
color:#334155;
padding:11px 14px;
border-radius:12px;
}

.nav-link:hover{
background:#eff6ff;
}

.nav-link.active{
background:linear-gradient(135deg,#2563eb,#1d4ed8);
color:#fff !important;
}

.hero{
background:linear-gradient(135deg,#0f172a,#1d4ed8);
color:#fff;
border-radius:22px;
padding:28px;
box-shadow:0 15px 35px rgba(0,0,0,.08);
}

.cardx{
background:#fff;
border:none;
border-radius:20px;
box-shadow:0 10px 30px rgba(0,0,0,.05);
}

.metric{
font-size:30px;
font-weight:800;
}

.table thead th{
white-space:nowrap;
background:#111827;
color:#fff;
border:none;
font-size:13px;
}

.table td{
vertical-align:middle;
}

.badge-soft{
padding:8px 12px;
border-radius:50px;
}

.search{
border-radius:14px;
padding:11px 14px;
}

.small-muted{
font-size:12px;
color:#64748b;
}
</style>
</head>
<body>

<div class="container-fluid">
<div class="row">

<!-- SIDEBAR -->
<div class="col-md-2 sidebar p-4">

<div class="brand mb-4">Yotribe Agro</div>

<ul class="nav flex-column gap-2">
<li><a href="../dashboard/index.php" class="nav-link">Dashboard</a></li>
<li><a href="../feeding/index.php" class="nav-link">Feeding</a></li>
<li><a href="index.php" class="nav-link active">Feed Store</a></li>
<li><a href="receive.php" class="nav-link">Receive Feed</a></li>
<li><a href="issue.php" class="nav-link">Issue Feed</a></li>
<li><a href="logs.php" class="nav-link">Logs</a></li>
</ul>

</div>

<!-- MAIN -->
<div class="col-md-10 p-4">

<!-- HERO -->
<div class="hero mb-4">
<div class="row align-items-center">
<div class="col-md-8">
<h2 class="mb-1">📦 Universal Feed Store</h2>
<div class="opacity-75">
One physical store • Multi-farm ownership tracking • FIFO ready
</div>
</div>

<div class="col-md-4 text-md-end mt-3 mt-md-0">
<a href="receive.php" class="btn btn-success btn-sm">+ Receive</a>
<a href="issue.php" class="btn btn-light btn-sm">Issue Feed</a>
<a href="logs.php" class="btn btn-outline-light btn-sm">Logs</a>
</div>
</div>
</div>

<!-- KPI -->
<div class="row g-4 mb-4">

<div class="col-md-3">
<div class="cardx p-4">
<small class="text-muted">Total Stock</small>
<div class="metric text-primary">
<?= number_format($summary['total_stock'],2) ?> kg
</div>
</div>
</div>

<div class="col-md-3">
<div class="cardx p-4">
<small class="text-muted">Inventory Value</small>
<div class="metric text-success">
₦<?= number_format($summary['stock_value'],2) ?>
</div>
</div>
</div>

<div class="col-md-3">
<div class="cardx p-4">
<small class="text-muted">Low Stock Alerts</small>
<div class="metric text-warning">
<?= $low_stock ?>
</div>
</div>
</div>

<div class="col-md-3">
<div class="cardx p-4">
<small class="text-muted">Expiring Soon</small>
<div class="metric text-danger">
<?= $expiring ?>
</div>
</div>
</div>

</div>

<!-- TABLE -->
<div class="cardx p-4">

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
<h5 class="fw-bold mb-0">Current Batch Inventory</h5>

<input type="text"
id="searchInput"
class="form-control search"
style="max-width:340px"
placeholder="Search feed / batch / supplier / farm">
</div>

<div class="table-responsive">

<table class="table table-hover align-middle mb-0" id="stockTable">

<thead>
<tr>
<th>Feed</th>
<th>Owner Farm</th>
<th>Batch</th>
<th>Available</th>
<th>Bags</th>
<th>Rate</th>
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
<div class="fw-bold"><?= htmlspecialchars($r['feed_type']) ?></div>
<div class="small-muted"><?= number_format($r['bag_weight_kg'],2) ?>kg bags</div>
</td>

<td>
<span class="badge bg-primary badge-soft">
<?= htmlspecialchars($r['farm_name'] ?? 'Unknown') ?>
</span>
</td>

<td><?= htmlspecialchars($r['batch_no']) ?></td>

<td class="fw-bold">
<?= number_format($r['quantity_kg'],2) ?> kg
</td>

<td>
<?= (int)$r['bag_count'] ?>
</td>

<td>
₦<?= number_format($r['cost_per_kg'],2) ?>/kg
<div class="small-muted">
₦<?= number_format($r['total_cost'],2) ?>
</div>
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
        row.style.display =
            row.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
});
</script>

</body>
</html>