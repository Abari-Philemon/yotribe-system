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
 * HANDLE ISSUE
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF Token');
    }

    $feed_type = trim($_POST['feed_type'] ?? '');
    $pond_id   = (int)($_POST['pond_id'] ?? 0);
    $qty       = (float)($_POST['quantity_kg'] ?? 0);
    $remarks   = trim($_POST['remarks'] ?? '');

    if ($feed_type === '' || $pond_id <= 0 || $qty <= 0) {
        $message = "Please complete all required fields.";
        $alert   = "danger";
    } else {

        try {

            $pdo->beginTransaction();

            /**
             * VALIDATE POND
             */
            $stmt = $pdo->prepare("
                SELECT id, pond_code
                FROM ponds_tanks
                WHERE id = ? AND farm_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$pond_id, $farm_id]);
            $pond = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pond) {
                throw new Exception("Invalid pond selected.");
            }

            /**
             * FIFO STOCK
             */
            $stmt = $pdo->prepare("
                SELECT *
                FROM feed_store
                WHERE farm_id = ?
                  AND feed_type = ?
                  AND quantity_kg > 0
                  AND status = 'active'
                ORDER BY received_date ASC, id ASC
                FOR UPDATE
            ");
            $stmt->execute([$farm_id, $feed_type]);
            $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$stocks) {
                throw new Exception("No stock available for {$feed_type}");
            }

            $available = array_sum(array_column($stocks, 'quantity_kg'));

            if ($available < $qty) {
                throw new Exception(
                    "Only " . number_format($available,2) . "kg available."
                );
            }

            $remaining  = $qty;
            $total_cost = 0;

            foreach ($stocks as $stock) {

                if ($remaining <= 0) break;

                $take = min($remaining, $stock['quantity_kg']);
                $new_qty = $stock['quantity_kg'] - $take;
                $cost = $take * $stock['cost_per_kg'];

                $status = $new_qty <= 0 ? 'finished' : 'active';

                $stmt = $pdo->prepare("
                    UPDATE feed_store
                    SET quantity_kg = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$new_qty, $status, $stock['id']]);

                $stmt = $pdo->prepare("
                    INSERT INTO feed_store_logs
                    (
                        farm_id, feed_store_id, pond_id,
                        feed_type, batch_no, movement_type,
                        quantity_kg, unit_cost, total_cost,
                        remarks, done_by, created_at
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, 'issue',
                        ?, ?, ?, ?, ?, NOW()
                    )
                ");

                $stmt->execute([
                    $farm_id,
                    $stock['id'],
                    $pond_id,
                    $feed_type,
                    $stock['batch_no'],
                    $take,
                    $stock['cost_per_kg'],
                    $cost,
                    $remarks,
                    $_SESSION['staff_id']
                ]);

                $remaining -= $take;
                $total_cost += $cost;
            }

            $pdo->commit();

            $message = "{$qty}kg issued to {$pond['pond_code']} successfully.";
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

/**
 * LOAD DATA
 */
$stmt = $pdo->prepare("
    SELECT DISTINCT feed_type
    FROM feed_store
    WHERE farm_id = ?
      AND quantity_kg > 0
      AND status = 'active'
    ORDER BY feed_type
");
$stmt->execute([$farm_id]);
$feeds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("
    SELECT id, pond_code, pond_type
    FROM ponds_tanks
    WHERE farm_id = ?
    ORDER BY pond_code
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * DASHBOARD STATS
 */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(quantity_kg),0)
    FROM feed_store
    WHERE farm_id = ?
      AND status='active'
");
$stmt->execute([$farm_id]);
$total_stock = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(quantity_kg),0)
    FROM feed_store_logs
    WHERE farm_id = ?
      AND movement_type='issue'
      AND DATE(created_at)=CURDATE()
");
$stmt->execute([$farm_id]);
$today_issue = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Issue Feed</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f4f7fb;
}
.top-card{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.06);
}
.form-card{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 30px rgba(0,0,0,.07);
}
.header-box{
    background:linear-gradient(135deg,#0d6efd,#0dcaf0);
    color:#fff;
    border-radius:18px;
    padding:25px;
}
.btn-main{
    border-radius:12px;
    padding:12px 18px;
    font-weight:600;
}
.form-control,.form-select{
    border-radius:12px;
    padding:12px;
}
.label{
    font-weight:600;
    margin-bottom:6px;
}
.stat{
    font-size:28px;
    font-weight:700;
}
</style>
</head>

<body>

<div class="container py-5">

    <!-- Header -->
    <div class="header-box mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-1">🐟 Feed Issue Center</h2>
                <div class="opacity-75">
                    Fully Automated FIFO Feed Distribution
                </div>
            </div>

            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="index.php" class="btn btn-light btn-main">
                    ← Back to Store
                </a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-4 mb-4">

        <div class="col-md-6">
            <div class="card top-card p-4">
                <small class="text-muted">Current Feed Stock</small>
                <div class="stat text-primary">
                    <?= number_format($total_stock,2) ?> kg
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card top-card p-4">
                <small class="text-muted">Issued Today</small>
                <div class="stat text-success">
                    <?= number_format($today_issue,2) ?> kg
                </div>
            </div>
        </div>

    </div>

    <?php if($message): ?>
        <div class="alert alert-<?= $alert ?> shadow-sm rounded-4">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="card form-card">
        <div class="card-body p-4">

            <h4 class="mb-4">Issue Feed Automatically</h4>

            <form method="POST">
                <input type="hidden"
                       name="csrf_token"
                       value="<?= $_SESSION['csrf_token'] ?>">

                <div class="row g-4">

                    <div class="col-md-6">
                        <label class="label">Feed Type</label>
                        <select name="feed_type"
                                class="form-select"
                                required>
                            <option value="">Select Feed</option>
                            <?php foreach($feeds as $feed): ?>
                                <option value="<?= $feed ?>">
                                    <?= $feed ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="label">Destination Pond</label>
                        <select name="pond_id"
                                class="form-select"
                                required>
                            <option value="">Select Pond</option>
                            <?php foreach($ponds as $pond): ?>
                                <option value="<?= $pond['id'] ?>">
                                    <?= $pond['pond_code'] ?>
                                    (<?= $pond['pond_type'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="label">Quantity (kg)</label>
                        <input type="number"
                               step="0.01"
                               min="0.01"
                               name="quantity_kg"
                               class="form-control"
                               placeholder="Enter quantity"
                               required>
                    </div>

                    <div class="col-md-6">
                        <label class="label">Remarks</label>
                        <input type="text"
                               name="remarks"
                               class="form-control"
                               placeholder="Optional note">
                    </div>

                    <div class="col-12 mt-2">
                        <button class="btn btn-primary btn-main w-100">
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