<?php
require '../../middleware/auth_guard.php';
require '../../middleware/farm_guard.php';
require '../../config/database.php';

require_role(['storekeeper','manager','owner']);

$farm_id = farm_id();
$staff_id = $_SESSION['staff_id'];

$message = '';
$alert = 'success';
$preview = null;

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD STOCKING
 */
$stmt = $pdo->prepare("
SELECT ps.*, p.pond_code, fb.batch_code
FROM pond_stocking ps
JOIN ponds_tanks p ON p.id = ps.pond_id
JOIN fish_batches fb ON fb.id = ps.batch_id
WHERE ps.farm_id=? AND ps.status='active' AND ps.current_count>0
ORDER BY p.pond_code
");
$stmt->execute([$farm_id]);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * LOAD FEED TYPES
 */
$stmt = $pdo->prepare("
SELECT DISTINCT feed_type
FROM feed_store
WHERE quantity_kg > 0 AND status='active'
ORDER BY feed_type
");
$stmt->execute();
$feeds = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * PREVIEW CALCULATION
 */
if (isset($_POST['preview'])) {

    $stock_id = (int)$_POST['stock_id'];

    $stmt = $pdo->prepare("SELECT * FROM pond_stocking WHERE id=? AND farm_id=?");
    $stmt->execute([$stock_id, $farm_id]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($s) {

        $biomass = ($s['current_count'] * $s['avg_weight_g']) / 1000;

        if ($s['avg_weight_g'] < 50)      $rate = 0.05;
        elseif ($s['avg_weight_g'] < 200) $rate = 0.03;
        else                             $rate = 0.02;

        $recommended = $biomass * $rate;

        $preview = [
            'biomass' => round($biomass,2),
            'rate' => $rate * 100,
            'recommended' => round($recommended,2)
        ];
    }
}
if (isset($_POST['feed'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF");
    }

    $stock_id = (int)$_POST['stock_id'];
    $feed_type = trim($_POST['feed_type']);
    $qty = (float)$_POST['quantity_kg'];
    $remarks = $_POST['remarks'] ?? '';
    $time = $_POST['time'] ?? date('H:i:s'); // ✅ FIXED

    try {

        $pdo->beginTransaction();

        // LOCK STOCK
        $stmt = $pdo->prepare("
            SELECT * FROM pond_stocking
            WHERE id=? AND farm_id=? FOR UPDATE
        ");
        $stmt->execute([$stock_id,$farm_id]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) throw new Exception("Invalid stock");

        // BIOMASS CHECK
        $biomass = ($stock['current_count'] * $stock['avg_weight_g']) / 1000;

        if ($stock['avg_weight_g'] < 50)      $rate = 0.10;
        elseif ($stock['avg_weight_g'] < 200) $rate = 0.06;
        else                                 $rate = 0.04;

        $max_feed = $biomass * $rate;

        if ($qty > $max_feed) {
            throw new Exception("Max allowed: ".round($max_feed,2)." kg");
        }

        // FIFO
        $stmt = $pdo->prepare("
            SELECT *
            FROM feed_store
            WHERE feed_type=? 
            AND status='active'
            AND available_kg > 0
            ORDER BY received_date ASC, id ASC
            FOR UPDATE
        ");
        $stmt->execute([$feed_type]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) throw new Exception("No feed available");

        $remaining = $qty;
        $total_cost = 0;

        foreach ($rows as $r) {

            if ($remaining <= 0) break;

            $take = min($remaining, $r['available_kg']);
            $cost = $take * $r['cost_per_kg'];

            $closing = $r['available_kg'] - $take;
            $status = $closing <= 0 ? 'finished' : 'active';

            // UPDATE STORE
            $stmt = $pdo->prepare("
                UPDATE feed_store
                SET available_kg=?, quantity_kg=?, status=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([$closing,$closing,$status,$r['id']]);

            // LOG
            $stmt = $pdo->prepare("
                INSERT INTO feed_store_logs (
                    idempotency_key,date,farm_id,stock_owner_farm_id,
                    warehouse_id,feed_store_id,feed_type,batch_no,
                    opening_stock,received,issued,closing_stock,balance_after,
                    issued_to,pond_id,fish_batch_id,batch_source_id,
                    unit_cost,total_cost,running_value,
                    movement_type,status,reference_no,
                    authorized_by,approved_at,requested_by,storekeeper,issued_at,remarks
                )
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                uniqid('FED-'),
                date('Y-m-d'),
                $farm_id,
                $r['farm_id'],
                $r['warehouse_id'],
                $r['id'],
                $feed_type,
                $r['batch_no'],
                $r['available_kg'],
                0,
                $take,
                $closing,
                $closing,
                'POND_FEEDING',
                $stock['pond_id'],
                $stock['batch_id'],
                null,
                $r['cost_per_kg'],
                $cost,
                $cost,
                'issue',
                'posted',
                'FD-'.date('YmdHis'),
                $staff_id,
                date('Y-m-d H:i:s'),
                $staff_id,
                $staff_id,
                date('Y-m-d H:i:s'),
                $remarks
            ]);

            $remaining -= $take;
            $total_cost += $cost;
        }

        // ✅ CORRECT feeding_logs (matches your table)
        $stmt = $pdo->prepare("
            INSERT INTO feeding_logs
            (date,farm_id,pond_id,batch_id,feed_type,quantity_kg,fed_by,time,remarks)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            date('Y-m-d'),
            $farm_id,
            $stock['pond_id'],
            $stock['batch_id'],
            $feed_type,
            $qty,
            $staff_id,
            $time,
            $remarks
        ]);

        $pdo->commit();

        $message = "Fed {$qty} kg | Cost: ₦".number_format($total_cost,2);
        $alert = 'success';

    } catch (Exception $e) {

        if ($pdo->inTransaction()) $pdo->rollBack();

        $message = $e->getMessage();
        $alert = 'danger';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Smart Feeding System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f7fb;
}
.cardx{
    border:none;
    border-radius:18px;
    box-shadow:0 15px 35px rgba(0,0,0,.05);
}
.kpi{
    font-size:28px;
    font-weight:700;
}
.badge-soft{
    background:#e9f7ef;
    color:#198754;
    padding:6px 10px;
    border-radius:10px;
}
</style>
</head>

<body class="container py-4">

<h3 class="mb-4">🐟 Smart Feeding System</h3>
</div>
<a href="index.php" class="btn btn-light btnx">← Back Store</a>
</div>

<!-- KPI STRIP -->
<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="cardx p-3 bg-white">
        <small>Live Biomass</small>
        <h4 id="rt_biomass">--</h4>
        </div>
    </div>

    <div class="col-md-3">
        <div class="cardx p-3 bg-white">
        <small>Feed Today</small>
        <h4 id="rt_feed">--</h4>
        </div>
    </div>

    <div class="col-md-3">
        <div class="cardx p-3 bg-white">
        <small>Feed Cost</small>
        <h4 id="rt_cost">--</h4>
        </div>
    </div>

    <div class="col-md-3">
        <div class="cardx p-3 bg-white">
        <small>Active Ponds</small>
        <h4 id="rt_ponds">--</h4>
        </div>
    </div>

</div>

<small id="rt_time" class="text-muted"></small>

<!-- ALERT -->
<?php if($message): ?>
<div class="alert alert-<?= $alert ?>"><?= $message ?></div>
<?php endif; ?>

<div class="cardx bg-white p-4">

<form method="POST" id="feedForm">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="row g-4">

<!-- STOCK -->
<div class="col-md-6">
<label class="fw-semibold mb-2">Pond + Batch</label>
<select name="stock_id" id="stock_id" class="form-select">
<?php foreach($stocks as $s): ?>
<option 
value="<?= $s['id'] ?>"
data-count="<?= $s['current_count'] ?>"
data-weight="<?= $s['avg_weight_g'] ?>"
>
<?= $s['pond_code'] ?> | <?= $s['batch_code'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<!-- FEED TYPE -->
<div class="col-md-6">
<label class="fw-semibold mb-2">Feed Type</label>
<select name="feed_type" id="feed_type" class="form-select">
<?php foreach($feeds as $f): ?>
<option value="<?= $f ?>"><?= $f ?></option>
<?php endforeach; ?>
</select>
<small id="stock_info" class="text-muted"></small>
</div>

<!-- QTY -->
<div class="col-md-4">
<label class="fw-semibold mb-2">Quantity (kg)</label>
<input type="number" step="0.01" name="quantity_kg" id="qty" class="form-control">
</div>

<!-- TIME -->
<div class="col-md-4">
<label class="fw-semibold mb-2">Time</label>
<input type="time" name="time" class="form-control" value="<?= date('H:i') ?>">
</div>

<!-- REMARK -->
<div class="col-md-4">
<label class="fw-semibold mb-2">Remarks</label>
<input type="text" name="remarks" class="form-control">
</div>

<div class="col-12 mt-3">
<button name="feed" class="btn btn-success w-100">🚀 Execute Feeding</button>
</div>

</div>
</form>

</div>

<script>
    function updatePreview(){

        let stock = document.getElementById('stock_id').selectedOptions[0];

        let count = parseFloat(stock.dataset.count || 0);
        let weight = parseFloat(stock.dataset.weight || 0);

        let biomass = (count * weight) / 1000;

        let rate = 0.05;
        if(weight < 50) rate = 0.10;
        else if(weight < 200) rate = 0.06;

        let recommended = biomass * rate;

        document.getElementById('rt_biomass').innerText = biomass.toFixed(2) + ' kg';
        document.getElementById('rt_feed').innerText = recommended.toFixed(2) + ' kg';

        calcCost();
    }

    function calcCost(){

        let qty = parseFloat(document.getElementById('qty').value) || 0;
        let avg_price = 500;

        let cost = qty * avg_price;

        document.getElementById('rt_cost').innerText = '₦' + cost.toLocaleString();
    }
</script>

</body>
</html>