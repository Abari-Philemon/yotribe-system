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

        if ($s['avg_weight_g'] < 50)      $rate = 0.10;
        elseif ($s['avg_weight_g'] < 200) $rate = 0.05;
        else                             $rate = 0.03;

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
        die("CSRF validation failed");
    }

    $stock_id  = (int) $_POST['stock_id'];
    $feed_type = trim($_POST['feed_type']);
    $qty       = (float) $_POST['quantity_kg'];
    $remarks   = trim($_POST['remarks'] ?? '');
    $time      = $_POST['time'] ?? date('H:i:s');

    try {

        if ($qty <= 0) {
            throw new Exception("Invalid feed quantity");
        }

        $pdo->beginTransaction();

        /**
         * LOCK STOCKING
         */
        $stmt = $pdo->prepare("
            SELECT *
            FROM pond_stocking
            WHERE id=? AND farm_id=?
            FOR UPDATE
        ");
        $stmt->execute([$stock_id, $farm_id]);

        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            throw new Exception("Invalid pond stock");
        }

        /**
         * BIOMASS CALCULATION
         */
        $biomass = ($stock['current_count'] * $stock['avg_weight_g']) / 1000;

        if ($stock['avg_weight_g'] < 50) {
            $rate = 0.10;
        } elseif ($stock['avg_weight_g'] < 200) {
            $rate = 0.06;
        } else {
            $rate = 0.04;
        }

        $max_feed = $biomass * $rate;

        if ($qty > $max_feed) {
            throw new Exception(
                "Maximum recommended feed is " .
                number_format($max_feed, 2) . " kg"
            );
        }

        /**
         * LOAD FIFO FEED STOCK
         */
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

        $feed_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$feed_rows) {
            throw new Exception("No feed available");
        }

        $remaining_qty = $qty;
        $total_cost = 0;

        foreach ($feed_rows as $row) {

            if ($remaining_qty <= 0) {
                break;
            }

            $available = (float)$row['available_kg'];

            $take = min($remaining_qty, $available);

            $unit_cost = (float)$row['cost_per_kg'];

            $cost = $take * $unit_cost;

            $closing = $available - $take;

            $new_status = $closing <= 0
                ? 'finished'
                : 'active';

            /**
             * UPDATE FEED STORE
             */
            $stmt = $pdo->prepare("
                UPDATE feed_store
                SET
                    available_kg = ?,
                    quantity_kg = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $closing,
                $closing,
                $new_status,
                $row['id']
            ]);

            /**
             * STORE LOG
             */
            $log_sql = "
                INSERT INTO feed_store_logs (
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
                    pond_id,
                    fish_batch_id,
                    batch_source_id,
                    unit_cost,
                    total_cost,
                    running_value,
                    movement_type,
                    status,
                    reference_no,
                    authorized_by,
                    approved_at,
                    requested_by,
                    storekeeper,
                    issued_at,
                    remarks
                )
                VALUES (
                    ?,?,?,?,?,?,?,?,?,?,
                    ?,?,?,?,?,?,?,?,?,?,
                    ?,?,?,?,?,?,?,?,?
                )
            ";

            $stmt = $pdo->prepare($log_sql);

            $stmt->execute([
                uniqid('FED-'),
                date('Y-m-d'),
                $farm_id,
                $row['farm_id'],
                $row['warehouse_id'],
                $row['id'],
                $feed_type,
                $row['batch_no'],
                $available,
                0,
                $take,
                $closing,
                $closing,
                'POND_FEEDING',
                $stock['pond_id'],
                $stock['batch_id'],
                null,
                $unit_cost,
                $cost,
                $cost,
                'issue',
                'posted',
                'FD-' . date('YmdHis'),
                $staff_id,
                date('Y-m-d H:i:s'),
                $staff_id,
                $staff_id,
                date('Y-m-d H:i:s'),
                $remarks
            ]);

            $remaining_qty -= $take;

            $total_cost += $cost;
        }

        /**
         * NOT ENOUGH FEED
         */
        if ($remaining_qty > 0) {

            throw new Exception(
                "Insufficient feed stock. Remaining shortage: " .
                number_format($remaining_qty, 2) . " kg"
            );
        }

        /**
         * FEEDING LOG
         */
        $stmt = $pdo->prepare("
            INSERT INTO feeding_logs (
                date,
                farm_id,
                pond_id,
                batch_id,
                feed_type,
                quantity_kg,
                fed_by,
                time,
                remarks
            )
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

        $message = "Feeding recorded successfully. "
                 . "Feed: {$qty} kg | "
                 . "Cost: ₦" . number_format($total_cost, 2);

        $alert = 'success';

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = $e->getMessage();
        $alert = 'danger';
    }
}
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
   



<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>🐟 Smart Feeding System</h3>

    <a href="index.php" class="btn btn-light">
        ← Back Store
    </a>
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
// Load farms into dropdown
fetch('/yotribe-system/app/modules/farms/list.php')
.then(res => res.json())
.then(farms => {

    const select = document.getElementById('farmSwitcher');
    select.innerHTML = '';

    farms.forEach(farm => {
        const option = document.createElement('option');
        option.value = farm.id;
        option.text  = farm.name;

        if (farm.id == <?= $farm_id ?>) {
            option.selected = true;
        }

        select.appendChild(option);
    });
});

// Handle farm switch
document.getElementById('farmSwitcher').addEventListener('change', function () {

    fetch('/yotribe-system/app/modules/farms/switch_live.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'farm_id=' + this.value + '&csrf_token=' + CSRF_TOKEN
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            location.reload();
        } else {
            alert(res.message || 'Switch failed');
        }
    });

});
</script>
<script>
    document.getElementById('stock_id')
    .addEventListener('change', updatePreview);

    document.getElementById('qty')
        .addEventListener('input', calcCost);

    updatePreview();
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