<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

/**
 * MODULE ACCESS
 */
require_permission('feed_store');

/**
 * FARM CONTEXT
 */
$farm_id = farm_id();

/**
 * PAGE TITLE
 */
$page_title = "Dashboard";

$staff_id = $_SESSION['staff_id'];

$message = '';
$alert   = 'success';

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * HANDLE ISSUE
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token');
    }

    $feed_type      = trim($_POST['feed_type'] ?? '');
    $pond_id        = (int)($_POST['pond_id'] ?? 0);
    $fish_batch_id  = !empty($_POST['fish_batch_id']) ? (int)$_POST['fish_batch_id'] : null;
    $qty            = (float)($_POST['quantity_kg'] ?? 0);
    $remarks        = trim($_POST['remarks'] ?? '');

    if ($feed_type === '' || $pond_id <= 0 || $qty <= 0) {
        $message = 'Please complete all required fields.';
        $alert   = 'danger';
    } else {

        try {

            $pdo->beginTransaction();

            /**
             * LOCK POND
             */
            $stmt = $pdo->prepare("
                SELECT id, pond_code
                FROM ponds_tanks
                WHERE id=? AND farm_id=?
                FOR UPDATE
            ");
            $stmt->execute([$pond_id, $farm_id]);
            $pond = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pond) {
                throw new Exception('Invalid pond.');
            }

            /**
             * FIFO STOCK (STRICT)
             */
            $stmt = $pdo->prepare("
                SELECT *
                FROM feed_store
                WHERE feed_type = ?
                AND status = 'active'
                AND available_kg > 0
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY received_date ASC, id ASC
                FOR UPDATE
            ");
            $stmt->execute([$feed_type]);
            $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$stocks) {
                throw new Exception('No available stock.');
            }

            $available = array_sum(array_column($stocks, 'available_kg'));

            if ($available < $qty) {
                throw new Exception("Only ".number_format($available,2)." kg available.");
            }

            $remaining = $qty;
            $usedRows  = 0;

            foreach ($stocks as $row) {

                if ($remaining <= 0) break;

                $take = min($remaining, $row['available_kg']);

                $opening = (float)$row['available_kg'];
                $closing = $opening - $take;
                $cost    = $take * $row['cost_per_kg'];

                $status = $closing <= 0 ? 'finished' : 'active';

                /**
                 * UPDATE STOCK
                 */
                $stmt = $pdo->prepare("
                    UPDATE feed_store
                    SET available_kg = ?,
                        status = ?,
                        last_issue_date = CURDATE(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $closing,
                    $status,
                    $row['id']
                ]);

                /**
                 * CONSUMPTION TRACKING
                 */
                $pdo->prepare("
                    INSERT INTO feed_consumption
                    (feed_store_id,batch_no,farm_id,pond_id,fish_batch_id,quantity_kg,unit_cost,total_cost)
                    VALUES (?,?,?,?,?,?,?,?)
                ")->execute([
                    $row['id'],
                    $row['batch_no'],
                    $farm_id,
                    $pond_id,
                    $fish_batch_id,
                    $take,
                    $row['cost_per_kg'],
                    $cost
                ]);

                /**
                 * LOG
                 */
                $stmt = $pdo->prepare("
                    INSERT INTO feed_store_logs
                    (
                        idempotency_key,date,farm_id,stock_owner_farm_id,warehouse_id,
                        feed_store_id,feed_type,batch_no,
                        opening_stock,received,issued,closing_stock,balance_after,
                        issued_to,pond_id,fish_batch_id,
                        unit_cost,total_cost,running_value,
                        movement_type,status,reference_no,
                        authorized_by,approved_at,requested_by,storekeeper,issued_at,remarks
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->execute([
                    hash('sha256', $row['id'] . microtime(true)),
                    date('Y-m-d'),
                    $farm_id,
                    $row['farm_id'],
                    1,
                    $row['id'],
                    $feed_type,
                    $row['batch_no'],
                    $opening,
                    0,
                    $take,
                    $closing,
                    $closing,
                    $pond['pond_code'],
                    $pond_id,
                    $fish_batch_id,
                    $row['cost_per_kg'],
                    $cost,
                    $cost,
                    'issue',
                    'posted',
                    'ISS-' . date('YmdHis'),
                    $staff_id,
                    date('Y-m-d H:i:s'),
                    $staff_id,
                    $staff_id,
                    date('Y-m-d H:i:s'),
                    $remarks ?: 'Auto FIFO issue'
                ]);

                $remaining -= $take;
                $usedRows++;
            }

            $pdo->commit();

            $message = number_format($qty,2)." kg issued to {$pond['pond_code']} (FIFO: {$usedRows} batch(es)).";
            $alert   = 'success';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $alert   = 'danger';
        }
    }
}

/**
 * LOAD FEEDS
 */
$stmt = $pdo->query("
    SELECT DISTINCT feed_type
    FROM feed_store
    WHERE available_kg > 0 AND status='active'
    ORDER BY feed_type
");
$feeds = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * LOAD PONDS
 */
$stmt = $pdo->prepare("
    SELECT id, pond_code, pond_type
    FROM ponds_tanks
    WHERE farm_id=?
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * KPI
 */
$total_stock = $pdo->query("
    SELECT COALESCE(SUM(available_kg),0)
    FROM feed_store
    WHERE status='active'
")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(issued),0)
    FROM feed_store_logs
    WHERE farm_id=? AND movement_type='issue' AND date=CURDATE()
");
$stmt->execute([$farm_id]);
$today_issue = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Issue Feed</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f5f7fb}
.cardx{
border:none;
border-radius:18px;
box-shadow:0 15px 35px rgba(0,0,0,.05);
}
.hero{
background:linear-gradient(135deg,#0d6efd,#20c997);
color:#fff;
padding:28px;
border-radius:18px;
}
.form-control,.form-select{
border-radius:12px;
padding:12px;
}
.btnx{
border-radius:12px;
padding:12px 18px;
font-weight:600;
}
.kpi{
font-size:30px;
font-weight:700;
}
</style>
</head>
<body>

<div class="container py-5">

<div class="hero mb-4">
<div class="row align-items-center">
<div class="col-md-8">
<h2 class="mb-1">Automated Feed Issue Center</h2>
<div class="opacity-75">Universal Store • FIFO Engine • Multi Farm</div>
</div>
<div class="col-md-4 text-md-end mt-3 mt-md-0">
<a href="index.php" class="btn btn-light btnx">← Back Store</a>
</div>
</div>
</div>

<div class="row g-4 mb-4">

<div class="col-md-6">
<div class="cardx p-4 bg-white">
<small class="text-muted">Total Store Stock</small>
<div class="kpi text-primary"><?= number_format($total_stock,2) ?> kg</div>
</div>
</div>

<div class="col-md-6">
<div class="cardx p-4 bg-white">
<small class="text-muted">Your Farm Used Today</small>
<div class="kpi text-success"><?= number_format($today_issue,2) ?> kg</div>
</div>
</div>

</div>

<?php if($message): ?>
<div class="alert alert-<?= $alert ?> rounded-4 shadow-sm">
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="cardx bg-white">
<div class="p-4">

<h4 class="mb-4">Issue Feed Automatically</h4>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="row g-4">

<div class="col-md-6">
<label class="mb-2 fw-semibold">Feed Type</label>
<select name="feed_type" class="form-select" required>
<option value="">Select Feed</option>
<?php foreach($feeds as $f): ?>
<option value="<?= htmlspecialchars($f) ?>">
<?= htmlspecialchars($f) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<label class="mb-2 fw-semibold">Destination Pond</label>
<select name="pond_id" class="form-select" required>
<option value="">Select Pond</option>
<?php foreach($ponds as $p): ?>
<option value="<?= $p['id'] ?>">
<?= $p['pond_code'] ?> (<?= $p['pond_type'] ?>)
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
<label class="mb-2 fw-semibold">Quantity (kg)</label>
<input type="number" step="0.01" min="0.01" name="quantity_kg"
class="form-control" required>
</div>

<div class="col-md-6">
<label class="mb-2 fw-semibold">Remarks</label>
<input type="text" name="remarks" class="form-control"
placeholder="Optional note">
</div>

<div class="col-12">
<button class="btn btn-primary btnx w-100">
🚀 Auto Issue Feed (FIFO)
</button>
</div>

</div>

</form>

</div>
</div>

</div>
</body>
</html>