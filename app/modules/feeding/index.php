<?php
require '../../middleware/auth_guard.php';
require '../../config/database.php';
require '../../config/config.php'; // For BASE_URL
require_role(['storekeeper','manager','owner']);

$farm_id = $_SESSION['farm_id'];
$message = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $pond_id = $_POST['pond_id'];
    $feed_type = $_POST['feed_type'];
    $quantity_kg = $_POST['quantity_kg'];
    $time = $_POST['time'] ?? date('H:i:s');
    $remarks = $_POST['remarks'];

    // Check available stock
    $stmt = $pdo->prepare("SELECT id, quantity_kg FROM feed_store WHERE feed_type = ? ORDER BY updated_at ASC LIMIT 1");
    $stmt->execute([$feed_type]);
    $feed_stock = $stmt->fetch();

    if(!$feed_stock || $feed_stock['quantity_kg'] < $quantity_kg){
        $message = "Not enough feed in stock!";
        $alert_type = 'danger';
    } else {
        // Deduct feed from stock
        $stmt = $pdo->prepare("UPDATE feed_store SET quantity_kg = quantity_kg - ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$quantity_kg, $feed_stock['id']]);

        // Insert into feeding logs
        $stmt = $pdo->prepare("INSERT INTO feeding_logs (date, farm_id, pond_id, feed_type, quantity_kg, fed_by, time, remarks) VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$farm_id, $pond_id, $feed_type, $quantity_kg, $_SESSION['staff_id'], $time, $remarks]);

        $message = "Feeding logged successfully!";
        $alert_type = 'success';
    }
}

// Fetch ponds and feed types
$stmt = $pdo->prepare("SELECT * FROM ponds_tanks WHERE farm_id = ?");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DISTINCT feed_type FROM feed_store");
$feeds = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feeding Module - Yotribe Agro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/custom.css">
<style>
/* Optional: fix sidebar height */
.sidebar { min-height: 100vh; }
</style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav id="sidebar" class="col-md-2 col-12 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/app/modules/dashboard/index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="<?= BASE_URL ?>/app/modules/feeding/index.php">Feeding</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/app/modules/feed_store/index.php">Feed Store</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/app/modules/reports/index.php">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/app/modules/export/pdf.php">Export PDF</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 ms-sm-auto px-md-4">
            <!-- Hamburger Toggle for small screens -->
            <nav class="navbar navbar-light bg-light d-md-none mb-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        ☰ Menu
                    </button>
                    <span class="navbar-brand mb-0 h1">Feeding Module</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Feeding Module</h1>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?= $alert_type ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Pond</label>
                            <select name="pond_id" class="form-select" required>
                                <?php foreach($ponds as $pond): ?>
                                    <option value="<?= $pond['id'] ?>"><?= $pond['pond_code'] ?> (<?= $pond['pond_type'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Feed Type</label>
                            <select name="feed_type" class="form-select" required>
                                <?php foreach($feeds as $feed): ?>
                                    <option value="<?= $feed ?>"><?= $feed ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Quantity (kg)</label>
                            <input type="number" step="0.01" name="quantity_kg" class="form-control" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Time</label>
                            <input type="time" name="time" class="form-control" value="<?= date('H:i') ?>">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Log Feeding</button>
                        </div>
                    </form>
                </div>
            </div>

            <h3>Feeding History (Last 10 Entries)</h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Pond</th>
                            <th>Feed Type</th>
                            <th>Quantity (kg)</th>
                            <th>Fed By</th>
                            <th>Time</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT f.date, p.pond_code, f.feed_type, f.quantity_kg, s.full_name AS fed_by, f.time, f.remarks
                            FROM feeding_logs f
                            JOIN ponds_tanks p ON f.pond_id = p.id
                            JOIN staff s ON f.fed_by = s.id
                            WHERE f.farm_id = ?
                            ORDER BY f.id DESC
                            LIMIT 10
                        ");
                        $stmt->execute([$farm_id]);
                        $logs = $stmt->fetchAll();

                        foreach($logs as $log):
                        ?>
                        <tr>
                            <td><?= $log['date'] ?></td>
                            <td><?= $log['pond_code'] ?></td>
                            <td><?= $log['feed_type'] ?></td>
                            <td><?= $log['quantity_kg'] ?></td>
                            <td><?= $log['fed_by'] ?></td>
                            <td><?= $log['time'] ?></td>
                            <td><?= $log['remarks'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
