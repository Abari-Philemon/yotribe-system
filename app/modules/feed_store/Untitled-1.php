<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');
require_role(['super_admin','storekeeper','manager','owner']);

$farm_id      = farm_id();
$warehouse_id = 1;
$staff_id     = $_SESSION['staff_id'];

$message = '';
$alert   = 'success';

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * AUTO BATCH
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

    $batch_no         = trim($_POST['batch_no']) ?: makeBatchNo();
    $received_date    = $_POST['received_date'] ?? date('Y-m-d');
    $manufacture_date = $_POST['manufacture_date'] ?: null;
    $expiry_date      = $_POST['expiry_date'] ?: null;
    $supplier_name    = trim($_POST['supplier_name'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');

    $feed_types = $_POST['feed_type'] ?? [];
    $bag_counts = $_POST['bag_count'] ?? [];
    $bag_sizes  = $_POST['bag_weight_kg'] ?? [];
    $costs      = $_POST['cost_per_bag'] ?? [];

    if (empty($feed_types)) {
        $message = "Please add at least one feed line.";
        $alert = "danger";
    } else {

        try {

            $pdo->beginTransaction();

            /**
             * CHECK UNIQUE BATCH
             */
            $stmt = $pdo->prepare("SELECT id FROM feed_batches WHERE batch_no=? LIMIT 1");
            $stmt->execute([$batch_no]);

            if ($stmt->fetch()) {
                throw new Exception("Batch already exists.");
            }

            /**
             * CREATE BATCH HEADER
             */
            $stmt = $pdo->prepare("
                INSERT INTO feed_batches
                (batch_no,farm_id,warehouse_id,supplier_name,received_date,manufacture_date,expiry_date,total_cost,notes)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $batch_no,
                $farm_id,
                $warehouse_id,
                $supplier_name,
                $received_date,
                $manufacture_date,
                $expiry_date,
                0,
                $notes
            ]);

            $batch_id = $pdo->lastInsertId();

            $batch_total_cost = 0;
            $valid_rows = 0;

            /**
             * PROCESS EACH LINE
             */
            for ($i = 0; $i < count($feed_types); $i++) {

                $feed_type = trim($feed_types[$i] ?? '');
                $bags      = (int)($bag_counts[$i] ?? 0);
                $size      = (float)($bag_sizes[$i] ?? 0);
                $cost_bag  = (float)($costs[$i] ?? 0);

                if ($feed_type === '' || $bags <= 0 || $size <= 0 || $cost_bag <= 0) {
                    throw new Exception("Invalid input at row " . ($i + 1));
                }

                $valid_rows++;

                $qty_kg   = $bags * $size;
                $cost_kg  = $cost_bag / $size;
                $total    = $bags * $cost_bag;

                $batch_total_cost += $total;

                /**
                 * INSERT STORE
                 */
                $stmt = $pdo->prepare("
                    INSERT INTO feed_store
                    (
                        feed_type,farm_id,warehouse_id,batch_no,
                        received_date,manufacture_date,expiry_date,supplier_name,
                        quantity_kg,damaged_kg,reserved_kg,available_kg,initial_quantity_kg,
                        cost_per_kg,total_cost,low_stock_level,status,notes,
                        bag_count,bag_weight_kg,unit_cost_per_bag
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->execute([
                    $feed_type,$farm_id,$warehouse_id,$batch_no,
                    $received_date,$manufacture_date,$expiry_date,$supplier_name,
                    $qty_kg,0,0,$qty_kg,$qty_kg,
                    $cost_kg,$total,50,'active',$notes,
                    $bags,$size,$cost_bag
                ]);

                $stock_id = $pdo->lastInsertId();

                /**
                 * LOG
                 */
                $stmt = $pdo->prepare("
                    INSERT INTO feed_store_logs
                    (
                        idempotency_key,date,farm_id,stock_owner_farm_id,warehouse_id,
                        feed_store_id,feed_type,batch_no,
                        opening_stock,received,issued,closing_stock,balance_after,
                        issued_to,pond_id,fish_batch_id,batch_source_id,
                        unit_cost,total_cost,running_value,
                        movement_type,status,reference_no,
                        authorized_by,approved_at,requested_by,storekeeper,issued_at,remarks
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->execute([
                    hash('sha256', $batch_no . $i . microtime(true)),
                    date('Y-m-d'),
                    $farm_id,
                    $farm_id,
                    $warehouse_id,
                    $stock_id,
                    $feed_type,
                    $batch_no,
                    0,
                    $qty_kg,
                    0,
                    $qty_kg,
                    $qty_kg,
                    'MAIN STORE',
                    null,null,null,
                    $cost_kg,
                    $total,
                    $total,
                    'receive',
                    'posted',
                    'PO-' . date('YmdHis'),
                    $staff_id,
                    date('Y-m-d H:i:s'),
                    $staff_id,
                    $staff_id,
                    date('Y-m-d H:i:s'),
                    'Batch receive'
                ]);
            }

            if ($valid_rows === 0) {
                throw new Exception("No valid feed rows.");
            }

            /**
             * UPDATE BATCH TOTAL
             */
            $stmt = $pdo->prepare("UPDATE feed_batches SET total_cost=? WHERE id=?");
            $stmt->execute([$batch_total_cost, $batch_id]);

            $pdo->commit();

            $message = "Batch received successfully.";
            $alert = "success";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $alert = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receive Feed</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">

<h3 class="mb-4">Multi-Feed Batch Receive</h3>

<?php if($message): ?>
<div class="alert alert-<?= $alert ?>">
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<!-- FEED ROWS -->
<div id="feedRows">

    <div class="row g-2 feed-row mb-2">

    <div class="col-md-3">
    <input name="feed_type[]" class="form-control" placeholder="Feed type (2mm)">
    </div>

    <div class="col-md-2">
    <input name="bag_count[]" type="number" class="form-control" placeholder="Bags">
    </div>

    <div class="col-md-2">
    <select name="bag_weight_kg[]" class="form-select">
    <option value="15">15kg</option>
    <option value="5">5kg</option>
    <option value="25">25kg</option>
    </select>
    </div>

    <div class="col-md-3">
    <input name="cost_per_bag[]" type="number" class="form-control" placeholder="Cost per bag">
    </div>

    <div class="col-md-2">
    <button type="button" class="btn btn-danger removeRow">X</button>
    </div>

    </div>

</div>

<button type="button" id="addRow" class="btn btn-dark mb-3">+ Add Feed Line</button>

<hr>

<div class="row g-3">

<div class="col-md-4">
<input type="text" name="batch_no" class="form-control" placeholder="Batch No (auto)">
</div>

<div class="col-md-4">
<input type="text" name="supplier_name" class="form-control" placeholder="Supplier">
</div>

<div class="col-md-4">
<input type="date" name="received_date" value="<?= date('Y-m-d') ?>" class="form-control">
</div>

<div class="col-md-4">
<input type="date" name="manufacture_date" class="form-control">
</div>

<div class="col-md-4">
<input type="date" name="expiry_date" class="form-control">
</div>

<div class="col-md-12">
<input type="text" name="notes" class="form-control" placeholder="Notes">
</div>

</div>

<button class="btn btn-success w-100 mt-4">Receive Batch</button>

</form>

</div>

<script>
// Add row
document.getElementById('addRow').onclick = function () {
    let row = document.querySelector('.feed-row').cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = '');
    document.getElementById('feedRows').appendChild(row);
};

// Remove row
document.addEventListener('click', function(e){
    if(e.target.classList.contains('removeRow')){
        if(document.querySelectorAll('.feed-row').length > 1){
            e.target.closest('.feed-row').remove();
        }
    }
});

// Row calculator
function calculateRow(row){
    let bags = parseFloat(row.querySelector('[name="bag_count[]"]').value) || 0;
    let size = parseFloat(row.querySelector('[name="bag_weight_kg[]"]').value) || 0;
    let cost = parseFloat(row.querySelector('[name="cost_per_bag[]"]').value) || 0;

    let totalKg = bags * size;
    let costKg = size > 0 ? cost / size : 0;

    if(!row.querySelector('.calc')){
        let el = document.createElement('small');
        el.className = 'calc text-muted';
        row.appendChild(el);
    }

    row.querySelector('.calc').innerText =
        ` → ${totalKg.toFixed(2)}kg | ₦/kg ${costKg.toFixed(2)}`;
}

// Listen changes
document.addEventListener('input', function(e){
    if(
        e.target.name === 'bag_count[]' ||
        e.target.name === 'bag_weight_kg[]' ||
        e.target.name === 'cost_per_bag[]'
    ){
        calculateRow(e.target.closest('.feed-row'));
    }
});
</script>

</body>
</html>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const CSRF_TOKEN = "<?= csrf_token() ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Biomass Chart (secure - no farm_id in URL)
fetch('charts.php?type=biomass')
.then(r => r.json())
.then(d => new Chart(document.getElementById('biomassChart'), {
    type: 'line',
    data: {
        labels: d.labels,
        datasets: [{
            label: 'Biomass',
            data: d.values,
            borderWidth: 2
        }]
    }
}));

// Sales Chart
fetch('charts.php?type=sales')
.then(r => r.json())
.then(d => new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: d.labels,
        datasets: [{
            label: 'Sales',
            data: d.values
        }]
    }
}));
</script>
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
        <select id="farmSwitcher" class="form-select form-select-sm" style="width:auto;">
            <option>Loading farms...</option>
        </select>
