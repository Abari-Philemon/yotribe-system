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

/**
 * HANDLE FEEDING (FULL FIFO + COST)
 */
if (isset($_POST['feed'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF");
    }

    $stock_id = (int)$_POST['stock_id'];
    $feed_type = trim($_POST['feed_type']);
    $qty = (float)$_POST['quantity_kg'];
    $remarks = $_POST['remarks'] ?? '';

    try {

        $pdo->beginTransaction();

        /**
         * LOCK STOCK
         */
        $stmt = $pdo->prepare("
        SELECT * FROM pond_stocking
        WHERE id=? AND farm_id=? FOR UPDATE
        ");
        $stmt->execute([$stock_id,$farm_id]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stock) throw new Exception("Invalid stock");

        /**
         * BIOMASS CHECK
         */
        $biomass = ($stock['current_count'] * $stock['avg_weight_g']) / 1000;

        if ($stock['avg_weight_g'] < 50)      $rate = 0.05;
        elseif ($stock['avg_weight_g'] < 200) $rate = 0.03;
        else                                 $rate = 0.02;

        $max_feed = $biomass * $rate;

        if ($qty > $max_feed) {
            throw new Exception("Max allowed: ".round($max_feed,2)." kg");
        }

        /**
         * FIFO STOCK (CORRECT)
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

            /**
             * UPDATE STORE
             */
            $stmt = $pdo->prepare("
            UPDATE feed_store
            SET available_kg=?, quantity_kg=?, status=?, updated_at=NOW()
            WHERE id=?
            ");
            $stmt->execute([$closing,$closing,$status,$r['id']]);

            /**
             * LOG MOVEMENT
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
                'FEEDING',
                $stock['pond_id'],
                $stock['batch_id'],
                $r['cost_per_kg'],
                $cost,
                $cost,
                'feeding',
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

        /**
         * FEED LOG (UPGRADED WITH COST)
         */
        $stmt = $pdo->prepare("
        INSERT INTO feeding_logs
        (date,farm_id,pond_id,batch_id,feed_type,quantity_kg,total_cost,fed_by,remarks)
        VALUES (CURDATE(),?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $farm_id,
            $stock['pond_id'],
            $stock['batch_id'],
            $feed_type,
            $qty,
            $total_cost,
            $staff_id,
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
</head>

<body class="container mt-4">

<h3>Smart Feeding System (FIFO + Cost)</h3>

<?php if($message): ?>
<div class="alert alert-<?= $alert ?>">
<?= $message ?>
</div>
<?php endif; ?>

<?php if($preview): ?>
<div class="alert alert-info">
Biomass: <?= $preview['biomass'] ?> kg<br>
Feed Rate: <?= $preview['rate'] ?>%<br>
Recommended Feed: <strong><?= $preview['recommended'] ?> kg</strong>
</div>
<?php endif; ?>

<form method="POST">

<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="row">

<div class="col-md-4">
<select name="stock_id" class="form-control">
<?php foreach($stocks as $s): ?>
<option value="<?= $s['id'] ?>">
<?= $s['pond_code'] ?> | <?= $s['batch_code'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<select name="feed_type" class="form-control">
<?php foreach($feeds as $f): ?>
<option><?= $f ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2">
<input type="number" step="0.01" name="quantity_kg" class="form-control" placeholder="kg">
</div>

<div class="col-md-3">
<input type="text" name="remarks" class="form-control" placeholder="Remarks">
</div>

</div>

<div class="mt-3">
<button name="preview" class="btn btn-info">Preview Feed</button>
<button name="feed" class="btn btn-success">Execute Feeding</button>
</div>

</form>

</body>
</html>