<?php
require '../../middleware/auth_guard.php';
require '../../middleware/farm_guard.php';
require '../../config/database.php';
require '../../config/config.php';

require_role(['storekeeper','manager','owner']);

$farm_id = farm_id();
$message = '';
$alert_type = 'success';
$suggested_feed = null;

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD ACTIVE STOCK (CRITICAL CHANGE)
 */
$stmt = $pdo->prepare("
    SELECT 
        ps.id,
        ps.pond_id,
        ps.batch_id,
        ps.current_count,
        ps.avg_weight_g,
        p.pond_code,
        p.pond_type,
        fb.batch_code
    FROM pond_stocking ps
    JOIN ponds_tanks p ON p.id = ps.pond_id
    JOIN fish_batches fb ON fb.id = ps.batch_id
    WHERE ps.farm_id = ?
    AND ps.status = 'active'
    AND ps.current_count > 0
    ORDER BY p.pond_code
");
$stmt->execute([$farm_id]);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * HANDLE FEEDING
 */
if($_SERVER['REQUEST_METHOD'] == 'POST'){

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF");
    }

    $stock_id = (int)$_POST['stock_id'];
    $feed_type = trim($_POST['feed_type']);
    $qty       = (float)$_POST['quantity_kg'];
    $time      = $_POST['time'] ?? date('H:i:s');
    $remarks   = $_POST['remarks'];

    if ($qty <= 0) {
        $message = "Invalid quantity";
        $alert_type = 'danger';
    } else {

        try {

            $pdo->beginTransaction();

            /**
             * LOCK STOCK ROW
             */
            $stmt = $pdo->prepare("
                SELECT * FROM pond_stocking
                WHERE id = ? AND farm_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$stock_id, $farm_id]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stock) {
                throw new Exception("Invalid selection");
            }

            /**
             * BIOMASS (BATCH LEVEL)
             */
            $fish_count = (int)$stock['current_count'];
            $avg_weight = (float)$stock['avg_weight_g'];

            if ($fish_count <= 0) {
                throw new Exception("No fish in this batch");
            }

            if ($avg_weight <= 0) {
                throw new Exception("Record growth first");
            }

            $biomass_kg = ($fish_count * $avg_weight) / 1000;

            /**
             * DYNAMIC FEED RATE
             */
            if ($avg_weight < 50) {
                $feed_rate = 0.05;
            } elseif ($avg_weight < 200) {
                $feed_rate = 0.03;
            } else {
                $feed_rate = 0.02;
            }

            $max_feed = $biomass_kg * $feed_rate;
            $suggested_feed = round($max_feed, 2);

            if ($qty > $max_feed) {
                throw new Exception("Overfeeding. Max allowed: {$suggested_feed} kg");
            }

            /**
             * LOAD FEED STOCK (FIFO)
             */
            $stmt = $pdo->prepare("
                SELECT id, quantity_kg
                FROM feed_store
                WHERE farm_id = ? AND feed_type = ?
                AND quantity_kg > 0
                ORDER BY updated_at ASC
                FOR UPDATE
            ");
            $stmt->execute([$farm_id, $feed_type]);
            $feedStocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$feedStocks) {
                throw new Exception("No feed stock available");
            }

            $total_available = array_sum(array_column($feedStocks, 'quantity_kg'));

            if ($total_available < $qty) {
                throw new Exception("Insufficient feed. Available: {$total_available} kg");
            }

            /**
             * FIFO DEDUCTION
             */
            $remaining = $qty;

            foreach ($feedStocks as $fs) {

                if ($remaining <= 0) break;

                $deduct = min($remaining, $fs['quantity_kg']);

                $stmt = $pdo->prepare("
                    UPDATE feed_store
                    SET quantity_kg = quantity_kg - ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$deduct, $fs['id']]);

                $remaining -= $deduct;
            }

            /**
             * INSERT FEED LOG (UPGRADED)
             */
            $stmt = $pdo->prepare("
                INSERT INTO feeding_logs
                (date, farm_id, pond_id, batch_id, feed_type, quantity_kg, fed_by, time, remarks)
                VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $farm_id,
                $stock['pond_id'],
                $stock['batch_id'], // 🔥 KEY ADDITION
                $feed_type,
                $qty,
                $_SESSION['staff_id'],
                $time,
                $remarks
            ]);

            $pdo->commit();

            $message = "Feeding recorded (Max: {$suggested_feed} kg)";
            $alert_type = 'success';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $alert_type = 'danger';
        }
    }
}

/**
 * LOAD FEED TYPES
 */
$stmt = $pdo->prepare("
    SELECT DISTINCT feed_type
    FROM feed_store
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);
$feeds = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * DAILY FEED TOTAL
 */
$stmt = $pdo->prepare("
    SELECT SUM(quantity_kg)
    FROM feeding_logs
    WHERE farm_id = ? AND date = CURDATE()
");
$stmt->execute([$farm_id]);
$today_feed = $stmt->fetchColumn() ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
<title>Batch Feeding Module</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container mt-4">

<h3>Batch Feeding Module</h3>

<div class="alert alert-info">
Total Feed Used Today: <strong><?= round($today_feed,2) ?> kg</strong>
</div>

<?php if($message): ?>
<div class="alert alert-<?= $alert_type ?>">
<?= $message ?>
<?php if($suggested_feed): ?>
<br><small>Suggested Feed: <?= $suggested_feed ?> kg</small>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="card p-3 mb-4">

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="row">

<div class="col-md-4 mb-2">
<label>Select Batch (Pond + Batch)</label>
<select name="stock_id" class="form-select" required>
<?php foreach($stocks as $s): ?>
<option value="<?= $s['id'] ?>">
<?= $s['pond_code'] ?> | <?= $s['batch_code'] ?> 
(<?= $s['current_count'] ?> fish)
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3 mb-2">
<label>Feed Type</label>
<select name="feed_type" class="form-select" required>
<?php foreach($feeds as $f): ?>
<option value="<?= $f ?>"><?= $f ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-2 mb-2">
<label>Quantity (kg)</label>
<input type="number" step="0.01" name="quantity_kg" class="form-control" required>
</div>

<div class="col-md-2 mb-2">
<label>Time</label>
<input type="time" name="time" class="form-control" value="<?= date('H:i') ?>">
</div>

<div class="col-md-12 mb-2">
<label>Remarks</label>
<textarea name="remarks" class="form-control"></textarea>
</div>

<div class="col-12">
<button class="btn btn-primary">Log Feeding</button>
</div>

</div>
</form>

</div>

</body>
</html>