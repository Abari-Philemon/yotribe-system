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
$from              = $_GET['from'] ?? date('Y-m-01');
$to                = $_GET['to'] ?? date('Y-m-d');
$feed_type         = trim($_GET['feed_type'] ?? '');
$movement_type     = trim($_GET['movement_type'] ?? '');
$consumer_farm_id  = (int)($_GET['consumer_farm_id'] ?? 0);

/**
 * FEED TYPES
 */
$stmt = $pdo->query("
    SELECT DISTINCT feed_type
    FROM feed_store
    ORDER BY feed_type
");
$feed_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
 * LOG QUERY
 * farm_id = consuming/requesting farm
 * stock_owner_farm_id = original buyer
 */
$sql = "
SELECT
    l.*,
    p.pond_code,
    sf.name AS consumer_farm,
    ofm.name AS owner_farm,
    st.full_name
FROM feed_store_logs l
LEFT JOIN ponds_tanks p ON p.id = l.pond_id
LEFT JOIN farms sf ON sf.id = l.farm_id
LEFT JOIN farms ofm ON ofm.id = l.stock_owner_farm_id
LEFT JOIN staff st ON st.id = l.storekeeper
WHERE l.date BETWEEN ? AND ?
";

$params = [$from, $to];

if ($feed_type !== '') {
    $sql .= " AND l.feed_type = ? ";
    $params[] = $feed_type;
}

if ($movement_type !== '') {
    $sql .= " AND l.movement_type = ? ";
    $params[] = $movement_type;
}

if ($consumer_farm_id > 0) {
    $sql .= " AND l.farm_id = ? ";
    $params[] = $consumer_farm_id;
}

$sql .= " ORDER BY l.id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * SUMMARY
 */
$stmt = $pdo->prepare("
SELECT
    COALESCE(SUM(received),0) AS total_received,
    COALESCE(SUM(issued),0)   AS total_issued,
    COALESCE(SUM(total_cost),0) AS total_value
FROM feed_store_logs
WHERE date BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$sum = $stmt->fetch(PDO::FETCH_ASSOC);

$total_received = (float)$sum['total_received'];
$total_issued   = (float)$sum['total_issued'];
$total_value    = (float)$sum['total_value'];
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
    background:#eef2f7;
}
.cardx{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 30px rgba(0,0,0,.06);
}
.hero{
    background:linear-gradient(135deg,#0f172a,#1d4ed8);
    color:#fff;
}
.metric{
    font-size:28px;
    font-weight:700;
}
.table thead th{
    white-space:nowrap;
}
.badge-soft{
    padding:7px 12px;
    border-radius:50px;
}
</style>
</head>
<body>

<div class="container-fluid py-4">

<!-- HEADER -->
<div class="cardx hero p-4 mb-4">
<div class="row align-items-center">
<div class="col-md-8">
<h2 class="mb-1">📦 Feed Store Movement Logs</h2>
<div class="opacity-75">
Universal Store • Full Receive / Issue / Cost Tracking
</div>
</div>

<div class="col-md-4 text-md-end mt-3 mt-md-0">
<a href="index.php" class="btn btn-light btn-sm">Stock</a>
<a href="receive.php" class="btn btn-success btn-sm">Receive</a>
<a href="issue.php" class="btn btn-primary btn-sm">Issue</a>
</div>
</div>
</div>

<!-- FILTER -->
<div class="cardx p-4 mb-4">
<form method="GET" class="row g-3">

<div class="col-md-2">
<label class="form-label">From</label>
<input type="date" name="from" value="<?= $from ?>" class="form-control">
</div>

<div class="col-md-2">
<label class="form-label">To</label>
<input type="date" name="to" value="<?= $to ?>" class="form-control">
</div>

<div class="col-md-2">
<label class="form-label">Feed Type</label>
<select name="feed_type" class="form-select">
<option value="">All</option>
<?php foreach($feed_types as $ft): ?>
<option value="<?= $ft ?>" <?= $feed_type==$ft?'selected':'' ?>>
<?= htmlspecialchars($ft) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2">
<label class="form-label">Movement</label>
<select name="movement_type" class="form-select">
<option value="">All</option>
<option value="receive" <?= $movement_type=='receive'?'selected':'' ?>>Receive</option>
<option value="issue" <?= $movement_type=='issue'?'selected':'' ?>>Issue</option>
<option value="adjustment" <?= $movement_type=='adjustment'?'selected':'' ?>>Adjustment</option>
<option value="return" <?= $movement_type=='return'?'selected':'' ?>>Return</option>
</select>
</div>

<div class="col-md-2">
<label class="form-label">Consumer Farm</label>
<select name="consumer_farm_id" class="form-select">
<option value="0">All</option>
<?php foreach($farms as $f): ?>
<option value="<?= $f['id'] ?>" <?= $consumer_farm_id==$f['id']?'selected':'' ?>>
<?= htmlspecialchars($f['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2 d-grid">
<label class="form-label">&nbsp;</label>
<button class="btn btn-dark">Apply Filter</button>
</div>

</form>
</div>

<!-- SUMMARY -->
<div class="row g-4 mb-4">

<div class="col-md-4">
<div class="cardx p-4 bg-success text-white">
<small>Total Received</small>
<div class="metric"><?= number_format($total_received,2) ?> kg</div>
</div>
</div>

<div class="col-md-4">
<div class="cardx p-4 bg-primary text-white">
<small>Total Issued</small>
<div class="metric"><?= number_format($total_issued,2) ?> kg</div>
</div>
</div>

<div class="col-md-4">
<div class="cardx p-4 bg-dark text-white">
<small>Total Movement Value</small>
<div class="metric">₦<?= number_format($total_value,2) ?></div>
</div>
</div>

</div>

<!-- TABLE -->
<div class="cardx p-0 overflow-hidden">

<div class="table-responsive">
<table class="table table-hover align-middle mb-0">

<thead class="table-dark">
<tr>
<th>Date</th>
<th>Type</th>
<th>Feed</th>
<th>Batch</th>
<th>Qty</th>
<th>Consumer Farm</th>
<th>Owner Farm</th>
<th>Pond</th>
<th>Unit Cost</th>
<th>Total Cost</th>
<th>Staff</th>
<th>Remarks</th>
</tr>
</thead>

<tbody>

<?php if($logs): ?>
<?php foreach($logs as $row): ?>

<tr>
<td><?= htmlspecialchars($row['date']) ?></td>

<td>
<?php if($row['movement_type']=='receive'): ?>
<span class="badge bg-success badge-soft">Receive</span>
<?php elseif($row['movement_type']=='issue'): ?>
<span class="badge bg-primary badge-soft">Issue</span>
<?php elseif($row['movement_type']=='return'): ?>
<span class="badge bg-warning text-dark badge-soft">Return</span>
<?php else: ?>
<span class="badge bg-secondary badge-soft">Adjust</span>
<?php endif; ?>
</td>

<td><?= htmlspecialchars($row['feed_type']) ?></td>

<td>
<span class="badge bg-dark">
<?= htmlspecialchars($row['batch_no']) ?>
</span>
</td>

<td>
<?php
$qty = $row['received'] > 0 ? $row['received'] : $row['issued'];
echo number_format($qty,2).' kg';
?>
</td>

<td><?= htmlspecialchars($row['consumer_farm'] ?? '-') ?></td>
<td><?= htmlspecialchars($row['owner_farm'] ?? '-') ?></td>
<td><?= htmlspecialchars($row['pond_code'] ?? '-') ?></td>

<td>₦<?= number_format((float)$row['unit_cost'],2) ?></td>
<td>₦<?= number_format((float)$row['total_cost'],2) ?></td>

<td><?= htmlspecialchars($row['full_name'] ?? '-') ?></td>

<td><?= htmlspecialchars($row['remarks']) ?></td>
</tr>

<?php endforeach; ?>
<?php else: ?>

<tr>
<td colspan="12" class="text-center py-5 text-muted">
No records found
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