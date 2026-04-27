<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');
require_role(['super_admin','storekeeper','manager','owner']);

$farm_id = farm_id();
$message = '';
$alert   = 'success';

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * HANDLE RECEIVE FEED
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $feed_type         = trim($_POST['feed_type']);
    $batch_no          = trim($_POST['batch_no']);
    $received_date     = $_POST['received_date'];
    $manufacture_date  = $_POST['manufacture_date'] ?: null;
    $expiry_date       = $_POST['expiry_date'] ?: null;
    $supplier_name     = trim($_POST['supplier_name']);
    $bag_count         = (int)$_POST['bag_count'];
    $bag_weight_kg     = (float)$_POST['bag_weight_kg'];
    $cost_per_bag      = (float)$_POST['cost_per_bag'];
    $notes             = trim($_POST['notes']);

    if ($bag_count <= 0 || $bag_weight_kg <= 0 || $cost_per_bag < 0) {
        $message = "Invalid values supplied.";
        $alert = "danger";
    } else {

        $quantity_kg = $bag_count * $bag_weight_kg;
        $cost_per_kg = $cost_per_bag / $bag_weight_kg;
        $total_cost  = $bag_count * $cost_per_bag;

        try {

            $pdo->beginTransaction();

            /**
             * CREATE STOCK BATCH
             */
            $stmt = $pdo->prepare("
                INSERT INTO feed_store (
                    feed_type,
                    farm_id,
                    batch_no,
                    received_date,
                    manufacture_date,
                    expiry_date,
                    supplier_name,
                    quantity_kg,
                    initial_quantity_kg,
                    cost_per_kg,
                    total_cost,
                    status,
                    notes,
                    bag_count,
                    bag_weight_kg
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $feed_type,
                $farm_id,
                $batch_no,
                $received_date,
                $manufacture_date,
                $expiry_date,
                $supplier_name,
                $quantity_kg,
                $quantity_kg,
                $cost_per_kg,
                $total_cost,
                'active',
                $notes,
                $bag_count,
                $bag_weight_kg
            ]);

            /**
             * STORE LOG
             */
            $stmt = $pdo->prepare("
                INSERT INTO feed_store_logs (
                    date,
                    farm_id,
                    feed_type,
                    batch_no,
                    opening_stock,
                    received,
                    issued,
                    closing_stock,
                    issued_to,
                    authorized_by,
                    storekeeper,
                    remarks
                ) VALUES (
                    CURDATE(),?,?,?,?,?,?,?,?,?,?,?
                )
            ");

            $stmt->execute([
                $feed_type,
                $batch_no,
                0,
                $quantity_kg,
                0,
                $quantity_kg,
                'STORE RECEIPT',
                $_SESSION['staff_id'],
                $_SESSION['staff_id'],
                'Feed received into store'
            ]);

            $pdo->commit();

            $message = "Feed received successfully.";
            $alert   = "success";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $alert   = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Receive Feed</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f6f9;
}
.card{
    border:none;
    border-radius:14px;
}
.top-card{
    background:linear-gradient(135deg,#198754,#157347);
    color:#fff;
}
</style>
</head>
<body>

<div class="container py-4">

<div class="card top-card shadow mb-4">
<div class="card-body">
<h3 class="mb-1">Receive Feed Stock</h3>
<small>Add new feed bags into inventory</small>
</div>
</div>

<?php if($message): ?>
<div class="alert alert-<?= $alert ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="card shadow">
<div class="card-body">

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="row g-3">

<div class="col-md-4">
<label class="form-label">Feed Type</label>
<input type="text" name="feed_type" class="form-control" required placeholder="e.g Coppens 2mm">
</div>

<div class="col-md-4">
<label class="form-label">Batch No</label>
<input type="text" name="batch_no" class="form-control" required placeholder="Auto / Supplier Batch">
</div>

<div class="col-md-4">
<label class="form-label">Supplier Name</label>
<input type="text" name="supplier_name" class="form-control">
</div>

<div class="col-md-4">
<label class="form-label">Received Date</label>
<input type="date" name="received_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
</div>

<div class="col-md-4">
<label class="form-label">Manufacture Date</label>
<input type="date" name="manufacture_date" class="form-control">
</div>

<div class="col-md-4">
<label class="form-label">Expiry Date</label>
<input type="date" name="expiry_date" class="form-control">
</div>

<div class="col-md-4">
<label class="form-label">Bag Count</label>
<input type="number" name="bag_count" id="bag_count" class="form-control" required value="1">
</div>

<div class="col-md-4">
<label class="form-label">Bag Size (kg)</label>
<select name="bag_weight_kg" id="bag_weight_kg" class="form-select">
<option value="15">15kg</option>
<option value="5">5kg</option>
<option value="25">25kg</option>
</select>
</div>

<div class="col-md-4">
<label class="form-label">Cost Per Bag (₦)</label>
<input type="number" step="0.01" name="cost_per_bag" id="cost_per_bag" class="form-control" required>
</div>

<div class="col-md-4">
<label class="form-label">Total Quantity</label>
<input type="text" id="total_kg" class="form-control bg-light" readonly>
</div>

<div class="col-md-4">
<label class="form-label">Cost / KG</label>
<input type="text" id="cost_per_kg" class="form-control bg-light" readonly>
</div>

<div class="col-md-4">
<label class="form-label">Total Cost</label>
<input type="text" id="total_cost" class="form-control bg-light" readonly>
</div>

<div class="col-12">
<label class="form-label">Notes</label>
<textarea name="notes" class="form-control" rows="3"></textarea>
</div>

<div class="col-12">
<button class="btn btn-success px-4">Receive Feed</button>
<a href="index.php" class="btn btn-secondary">Back</a>
</div>

</div>
</form>

</div>
</div>
</div>

<script>
function calc(){

    let bags = parseFloat(document.getElementById('bag_count').value) || 0;
    let size = parseFloat(document.getElementById('bag_weight_kg').value) || 0;
    let cost = parseFloat(document.getElementById('cost_per_bag').value) || 0;

    let totalKg = bags * size;
    let totalCost = bags * cost;
    let perKg = size > 0 ? cost / size : 0;

    document.getElementById('total_kg').value = totalKg.toFixed(2) + ' kg';
    document.getElementById('cost_per_kg').value = perKg.toFixed(2);
    document.getElementById('total_cost').value = totalCost.toFixed(2);
}

document.querySelectorAll('#bag_count,#bag_weight_kg,#cost_per_bag')
.forEach(el => el.addEventListener('input', calc));

calc();
</script>

</body>
</html>