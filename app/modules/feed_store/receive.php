<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');
require_role(['super_admin','storekeeper','manager','owner']);

$farm_id  = farm_id();                 // farm paying for stock
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
 * AUTO BATCH NUMBER
 */
function makeBatchNo()
{
    return 'FB-' . date('YmdHis') . '-' . rand(100,999);
}

/**
 * HANDLE RECEIVE
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token');
    }

    $feed_type        = trim($_POST['feed_type'] ?? '');
    $batch_no         = trim($_POST['batch_no'] ?? '');
    $received_date    = $_POST['received_date'] ?? date('Y-m-d');
    $manufacture_date = !empty($_POST['manufacture_date']) ? $_POST['manufacture_date'] : null;
    $expiry_date      = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $supplier_name    = trim($_POST['supplier_name'] ?? '');
    $bag_count        = (int)($_POST['bag_count'] ?? 0);
    $bag_weight_kg    = (float)($_POST['bag_weight_kg'] ?? 0);
    $cost_per_bag     = (float)($_POST['cost_per_bag'] ?? 0);
    $low_stock_level  = (float)($_POST['low_stock_level'] ?? 50);
    $notes            = trim($_POST['notes'] ?? '');

    if ($batch_no === '') {
        $batch_no = makeBatchNo();
    }

    if ($feed_type === '') {
        $message = 'Feed type is required.';
        $alert   = 'danger';

    } elseif ($bag_count <= 0 || $bag_weight_kg <= 0 || $cost_per_bag < 0) {
        $message = 'Invalid quantity or cost.';
        $alert   = 'danger';

    } else {

        $quantity_kg = $bag_count * $bag_weight_kg;
        $cost_per_kg = $cost_per_bag / $bag_weight_kg;
        $total_cost  = $bag_count * $cost_per_bag;

        try {

            $pdo->beginTransaction();

            /**
             * UNIQUE BATCH
             */
            $stmt = $pdo->prepare("
                SELECT id
                FROM feed_store
                WHERE batch_no = ?
                LIMIT 1
            ");
            $stmt->execute([$batch_no]);

            if ($stmt->fetch()) {
                throw new Exception('Batch number already exists.');
            }

            /**
             * INSERT STOCK
             * farm_id = farm that paid for stock
             */
            $stmt = $pdo->prepare("
                INSERT INTO feed_store
                (
                    feed_type,
                    farm_id,
                    batch_no,
                    received_date,
                    manufacture_date,
                    expiry_date,
                    supplier_name,
                    quantity_kg,
                    available_kg,
                    initial_quantity_kg,
                    cost_per_kg,
                    total_cost,
                    low_stock_level,
                    status,
                    notes,
                    bag_count,
                    bag_weight_kg,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()
                )
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
                $quantity_kg,
                $cost_per_kg,
                $total_cost,
                $low_stock_level,
                'active',
                $notes,
                $bag_count,
                $bag_weight_kg
            ]);

            $stock_id = $pdo->lastInsertId();

            /**
             * LOG RECEIVE
             */
            $stmt = $pdo->prepare("
                INSERT INTO feed_store_logs
                (
                    idempotency_key,
                    date,
                    farm_id,
                    stock_owner_farm_id,
                    warehouse_id,
                    feed_store_id,
                    feed_type,
                    batch_no,
                    opening_stock,
                    received,
                    issued,
                    closing_stock,
                    balance_after,
                    issued_to,
                    unit_cost,
                    total_cost,
                    movement_type,
                    reference_no,
                    authorized_by,
                    storekeeper,
                    approved_at,
                    remarks,
                    status
                )
                VALUES
                (
                    ?,CURDATE(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
                )
            ");

            $stmt->execute([
                uniqid('REC-'),
                $farm_id,
                $farm_id,
                1,
                $stock_id,
                $feed_type,
                $batch_no,
                0,
                $quantity_kg,
                0,
                $quantity_kg,
                $quantity_kg,
                'MAIN STORE',
                $cost_per_kg,
                $total_cost,
                'receive',
                'PO-' . date('YmdHis'),
                $staff_id,
                $staff_id,
                date('Y-m-d H:i:s'),
                'Feed received into warehouse',
                'posted'
            ]);

            $pdo->commit();

            $message = 'Feed received successfully. Batch: '.$batch_no;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receive Feed</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{background:#f4f7fb}
.cardx{
border:none;
border-radius:18px;
box-shadow:0 15px 35px rgba(0,0,0,.05);
}
.hero{
background:linear-gradient(135deg,#198754,#20c997);
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
.metric{
font-size:14px;
color:#6c757d;
}
.big{
font-size:28px;
font-weight:700;
}
</style>
</head>
<body>

<div class="container py-5">

<div class="hero mb-4">
<div class="row align-items-center">
<div class="col-md-8">
<h2 class="mb-1">Feed Receiving Center</h2>
<div class="opacity-75">Warehouse Intake • Cost Tracking • Batch Control</div>
</div>
<div class="col-md-4 text-md-end mt-3 mt-md-0">
<a href="index.php" class="btn btn-light btnx">← Back Store</a>
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

<h4 class="mb-4">Receive Feed Stock</h4>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="row g-4">

<div class="col-md-4">
<label class="fw-semibold mb-2">Feed Type</label>
<input type="text" name="feed_type" class="form-control"
placeholder="e.g Coppens 2mm" required>
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Batch No</label>
<input type="text" name="batch_no" class="form-control"
placeholder="Leave blank for auto batch">
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Supplier Name</label>
<input type="text" name="supplier_name" class="form-control">
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Received Date</label>
<input type="date" name="received_date"
value="<?= date('Y-m-d') ?>"
class="form-control" required>
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Manufacture Date</label>
<input type="date" name="manufacture_date" class="form-control">
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Expiry Date</label>
<input type="date" name="expiry_date" class="form-control">
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Bag Count</label>
<input type="number" min="1" value="1"
name="bag_count" id="bag_count"
class="form-control" required>
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Bag Size</label>
<select name="bag_weight_kg" id="bag_weight_kg" class="form-select">
<option value="15">15kg</option>
<option value="5">5kg</option>
<option value="25">25kg</option>
</select>
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Cost Per Bag (₦)</label>
<input type="number" step="0.01"
name="cost_per_bag" id="cost_per_bag"
class="form-control" required>
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Low Stock Alert (kg)</label>
<input type="number" step="0.01"
name="low_stock_level"
value="50"
class="form-control">
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Total Quantity</label>
<input type="text" id="total_kg"
class="form-control bg-light" readonly>
</div>

<div class="col-md-4">
<label class="fw-semibold mb-2">Cost / KG</label>
<input type="text" id="cost_per_kg_calc"
class="form-control bg-light" readonly>
</div>

<div class="col-md-6">
<label class="fw-semibold mb-2">Total Cost</label>
<input type="text" id="total_cost"
class="form-control bg-light" readonly>
</div>

<div class="col-md-6">
<label class="fw-semibold mb-2">Notes</label>
<input type="text" name="notes" class="form-control">
</div>

<div class="col-12">
<button class="btn btn-success btnx w-100">
📦 Receive Feed Stock
</button>
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
document.getElementById('cost_per_kg_calc').value = perKg.toFixed(2);
document.getElementById('total_cost').value = totalCost.toFixed(2);

}

document.querySelectorAll('#bag_count,#bag_weight_kg,#cost_per_bag')
.forEach(el => el.addEventListener('input', calc));

calc();
</script>

</body>
</html>