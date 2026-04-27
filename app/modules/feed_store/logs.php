<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');
require_role(['super_admin','storekeeper','manager','owner']);

$farm_id = farm_id();

/**
 * FILTERS
 */
$from      = $_GET['from']      ?? date('Y-m-01');
$to        = $_GET['to']        ?? date('Y-m-d');
$feed_type = trim($_GET['feed_type'] ?? '');
$action    = trim($_GET['action'] ?? '');

/**
 * LOAD FEED TYPES
 */
$stmt = $pdo->prepare("
    SELECT DISTINCT feed_type
    FROM feed_store_logs
    WHERE farm_id = ?
    ORDER BY feed_type
");
$stmt->execute([$farm_id]);
$feed_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * BUILD QUERY
 */
$sql = "
SELECT 
    l.*,
    s.full_name
FROM feed_store_logs l
LEFT JOIN staff s ON s.id = l.storekeeper
WHERE l.farm_id = ?
AND l.date BETWEEN ? AND ?
";

$params = [$farm_id, $from, $to];

if ($feed_type !== '') {
    $sql .= " AND l.feed_type = ? ";
    $params[] = $feed_type;
}

if ($action === 'receive') {
    $sql .= " AND l.received > 0 ";
}

if ($action === 'issue') {
    $sql .= " AND l.issued > 0 ";
}

$sql .= " ORDER BY l.id DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * SUMMARY
 */
$stmt = $pdo->prepare("
SELECT
COALESCE(SUM(received),0),
COALESCE(SUM(issued),0)
FROM feed_store_logs
WHERE farm_id = ?
AND date BETWEEN ? AND ?
");
$stmt->execute([$farm_id, $from, $to]);

$sum = $stmt->fetch(PDO::FETCH_NUM);

$total_received = (float)$sum[0];
$total_issued   = (float)$sum[1];
$net_movement   = $total_received - $total_issued;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Feed Store Logs</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f6f9;
}
.card{
    border:none;
    border-radius:14px;
}
.stat-card{
    color:#fff;
}
.table thead th{
    white-space:nowrap;
}
.badge-soft{
    padding:6px 10px;
    border-radius:30px;
}
</style>
</head>
<body>

<div class="container-fluid py-4">

<div class="row mb-4">
<div class="col-md-12">

<div class="card shadow bg-dark text-white">
<div class="card-body d-flex justify-content-between align-items-center flex-wrap">
<div>
<h3 class="mb-1">Feed Store Logs</h3>
<small>Receipts, issues, stock movement history</small>
</div>

<div class="mt-2 mt-md-0">
<a href="index.php" class="btn btn-light btn-sm">Stock</a>
<a href="receive.php" class="btn btn-success btn-sm">Receive</a>
<a href="issue.php" class="btn btn-primary btn-sm">Issue</a>
</div>
</div>
</div>

</div>
</div>

<!-- FILTER -->
<div class="card shadow mb-4">
<div class="card-body">

<form method="GET" class="row g-3">

<div class="col-md-2">
<label class="form-label">From</label>
<input type="date" name="from" value="<?= $from ?>" class="form-control">
</div>

<div class="col-md-2">
<label class="form-label">To</label>
<input type="date" name="to" value="<?= $to ?>" class="form-control">
</div>

<div class="col-md-3">
<label class="form-label">Feed Type</label>
<select name="feed_type" class="form-select">
<option value="">All</option>
<?php foreach($feed_types as $f): ?>
<option value="<?= htmlspecialchars($f) ?>" <?= $feed_type === $f ? 'selected' : '' ?>>
<?= htmlspecialchars($f) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Movement</label>
<select name="action" class="form-select">
<option value="">All</option>
<option value="receive" <?= $action=='receive'?'selected':'' ?>>Received</option>
<option value="issue" <?= $action=='issue'?'selected':'' ?>>Issued</option>
</select>
</div>

<div class="col-md-2 d-grid">
<label class="form-label">&nbsp;</label>
<button class="btn btn-dark">Filter Logs</button>
</div>

</form>

</div>
</div>

<!-- SUMMARY -->
<div class="row g-3 mb-4">

<div class="col-md-4">
<div class="card shadow stat-card bg-success">
<div class="card-body">
<h6>Total Received</h6>
<h3><?= number_format($total_received,2) ?> kg</h3>
</div>
</div>
</div>

<div class="col-md-4">
<div class="card shadow stat-card bg-primary">
<div class="card-body">
<h6>Total Issued</h6>
<h3><?= number_format($total_issued,2) ?> kg</h3>
</div>
</div>
</div>

<div class="col-md-4">
<div class="card shadow stat-card <?= $net_movement >= 0 ? 'bg-secondary':'bg-danger' ?>">
<div class="card-body">
<h6>Net Movement</h6>
<h3><?= number_format($net_movement,2) ?> kg</h3>
</div>
</div>
</div>

</div>

<!-- TABLE -->
<div class="card shadow">
<div class="card-body table-responsive">

<table class="table table-hover table-striped align-middle">

<thead class="table-dark">
<tr>
<th>Date</th>
<th>Feed Type</th>
<th>Batch</th>
<th>Opening</th>
<th>Received</th>
<th>Issued</th>
<th>Closing</th>
<th>Destination</th>
<th>Staff</th>
<th>Remarks</th>
</tr>
</thead>

<tbody>

<?php if($logs): ?>
<?php foreach($logs as $row): ?>

<tr>

<td><?= htmlspecialchars($row['date']) ?></td>

<td><?= htmlspecialchars($row['feed_type']) ?></td>

<td>
<span class="badge bg-secondary">
<?= htmlspecialchars($row['batch_no']) ?>
</span>
</td>

<td><?= number_format($row['opening_stock'],2) ?></td>

<td>
<?php if($row['received'] > 0): ?>
<span class="badge bg-success">
+<?= number_format($row['received'],2) ?>
</span>
<?php else: ?> -
<?php endif; ?>
</td>

<td>
<?php if($row['issued'] > 0): ?>
<span class="badge bg-primary">
-<?= number_format($row['issued'],2) ?>
</span>
<?php else: ?> -
<?php endif; ?>
</td>

<td><?= number_format($row['closing_stock'],2) ?></td>

<td><?= htmlspecialchars($row['issued_to']) ?></td>

<td><?= htmlspecialchars($row['full_name'] ?? '-') ?></td>

<td><?= htmlspecialchars($row['remarks']) ?></td>

</tr>

<?php endforeach; ?>
<?php else: ?>

<tr>
<td colspan="10" class="text-center text-muted py-4">
No logs found for selected filter
</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>
</div>

</div>
</body>
</html>