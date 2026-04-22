<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_context.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['manager','owner','hatchery']);

$farm_id = farm_id();
$message = '';
$error = '';

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * HANDLE MORTALITY (CORE LOGIC)
 */
if($_SERVER['REQUEST_METHOD'] == 'POST'){

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF");
    }

    $stock_id = (int)$_POST['stock_id'];
    $dead_count = (int)$_POST['dead_count'];
    $cause = trim($_POST['suspected_cause']);
    $action = trim($_POST['action_taken']);

    if ($dead_count <= 0) {
        $error = "Invalid mortality count";
    } else {

        $pdo->beginTransaction();

        try {

            /**
             * LOCK STOCK
             */
            $stmt = $pdo->prepare("
                SELECT * FROM pond_stocking
                WHERE id = ? AND farm_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$stock_id, $farm_id]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stock) {
                throw new Exception("Invalid stock selection");
            }

            if ($dead_count > $stock['current_count']) {
                throw new Exception("Mortality exceeds available fish");
            }

            /**
             * REDUCE STOCK
             */
            $pdo->prepare("
                UPDATE pond_stocking
                SET current_count = current_count - ?
                WHERE id = ?
            ")->execute([$dead_count, $stock_id]);

            /**
             * CLOSE IF EMPTY
             */
            $pdo->prepare("
                UPDATE pond_stocking
                SET status = 'harvested'
                WHERE id = ?
                AND current_count <= 0
            ")->execute([$stock_id]);

            /**
             * LOG (mortality_logs)
             */
            $pdo->prepare("
                INSERT INTO mortality_logs
                (date, farm_id, pond_id, dead_count, suspected_cause, action_taken, reported_by)
                VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)
            ")->execute([
                $farm_id,
                $stock['pond_id'],
                $dead_count,
                $cause,
                $action,
                $_SESSION['staff_id']
            ]);

            /**
             * LOG (stock_movements)
             */
            $pdo->prepare("
                INSERT INTO stock_movements
                (farm_id, type, from_pond_id, batch_id, quantity, movement_date, note)
                VALUES (?, 'mortality', ?, ?, ?, CURDATE(), ?)
            ")->execute([
                $farm_id,
                $stock['pond_id'],
                $stock['batch_id'],
                $dead_count,
                $cause
            ]);

            $pdo->commit();
            $message = "Mortality recorded successfully";

        } catch (Exception $e) {

            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

/**
 * LOAD ACTIVE STOCK (IMPORTANT CHANGE)
 */
$stmt = $pdo->prepare("
    SELECT ps.id, ps.current_count,
           p.pond_code, fb.batch_code
    FROM pond_stocking ps
    JOIN ponds_tanks p ON p.id = ps.pond_id
    JOIN fish_batches fb ON fb.id = ps.batch_id
    WHERE ps.farm_id = ?
    AND ps.status = 'active'
    AND ps.current_count > 0
");
$stmt->execute([$farm_id]);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * RECENT LOGS
 */
$stmt = $pdo->prepare("
    SELECT m.date, p.pond_code, m.dead_count, m.suspected_cause, m.action_taken, s.full_name
    FROM mortality_logs m
    JOIN ponds_tanks p ON m.pond_id = p.id
    JOIN staff s ON m.reported_by = s.id
    WHERE m.farm_id = ?
    ORDER BY m.id DESC
    LIMIT 10
");
$stmt->execute([$farm_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ANALYTICS (LAST 7 DAYS)
 */
$stmt = $pdo->prepare("
    SELECT 
        p.pond_code,
        fb.batch_code,
        SUM(sm.quantity) AS total_dead,
        ps.current_count,
        (SUM(sm.quantity) / (SUM(sm.quantity) + ps.current_count)) * 100 AS mortality_rate
    FROM stock_movements sm
    JOIN ponds_tanks p ON p.id = sm.from_pond_id
    JOIN pond_stocking ps ON ps.pond_id = p.id
    JOIN fish_batches fb ON fb.id = ps.batch_id
    WHERE sm.type = 'mortality'
    AND sm.farm_id = ?
    AND sm.movement_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY p.id, fb.id
");
$stmt->execute([$farm_id]);
$analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ALERT CHECK
 */
$critical_found = false;

foreach ($analytics as $a) {
    if ($a['mortality_rate'] >= 10) {
        $critical_found = true;
        break;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Mortality Module</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container mt-4">

<h3>Mortality Module</h3>

<?php if($critical_found): ?>
<div class="alert alert-danger">
🚨 High mortality detected! Immediate action required.
</div>
<?php endif; ?>

<?php if($message): ?>
<div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- FORM -->
<form method="POST" class="card p-3 mb-4">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<select name="stock_id" class="form-select mb-2" required>
<option value="">Select Pond + Batch</option>
<?php foreach($stocks as $s): ?>
<option value="<?= $s['id'] ?>">
<?= $s['pond_code'] ?> | <?= $s['batch_code'] ?> (<?= $s['current_count'] ?>)
</option>
<?php endforeach; ?>
</select>

<input type="number" name="dead_count" class="form-control mb-2" placeholder="Dead count" required>

<input type="text" name="suspected_cause" class="form-control mb-2" placeholder="Cause">

<textarea name="action_taken" class="form-control mb-2" placeholder="Action taken"></textarea>

<button class="btn btn-danger">Record Mortality</button>
</form>

<!-- ANALYTICS -->
<h5>Mortality Alerts (7 Days)</h5>
<table class="table table-bordered">
<tr>
<th>Pond</th><th>Batch</th><th>Dead</th><th>Alive</th><th>Rate</th><th>Status</th>
</tr>

<?php foreach($analytics as $a):

$rate = round($a['mortality_rate'],2);

if ($rate >= 10) {
    $status = 'CRITICAL';
    $class = 'danger';
} elseif ($rate >= 5) {
    $status = 'WARNING';
    $class = 'warning';
} else {
    $status = 'NORMAL';
    $class = 'success';
}
?>

<tr>
<td><?= $a['pond_code'] ?></td>
<td><?= $a['batch_code'] ?></td>
<td><?= $a['total_dead'] ?></td>
<td><?= $a['current_count'] ?></td>
<td><?= $rate ?>%</td>
<td><span class="badge bg-<?= $class ?>"><?= $status ?></span></td>
</tr>

<?php endforeach; ?>
</table>

<!-- RECENT LOGS -->
<h5>Recent Logs</h5>
<table class="table table-striped">
<tr>
<th>Date</th><th>Pond</th><th>Dead</th><th>Cause</th><th>Action</th><th>Staff</th>
</tr>

<?php foreach($logs as $log): ?>
<tr>
<td><?= $log['date'] ?></td>
<td><?= $log['pond_code'] ?></td>
<td><?= $log['dead_count'] ?></td>
<td><?= htmlspecialchars($log['suspected_cause']) ?></td>
<td><?= htmlspecialchars($log['action_taken']) ?></td>
<td><?= $log['full_name'] ?></td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>