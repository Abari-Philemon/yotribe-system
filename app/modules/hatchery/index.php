<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();
require_role(['hatchery','manager','owner']);

$farm_id = $_SESSION['farm_id'];
$message = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $batch_id = $_POST['batch_id'];
    $stage = $_POST['stage'];
    $quantity = $_POST['quantity'];
    $survival_rate = $_POST['survival_rate'];

    $stmt = $pdo->prepare("
        INSERT INTO hatchery_logs (date, batch_id, stage, quantity, survival_rate, officer) 
        VALUES (CURDATE(), ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$batch_id, $stage, $quantity, $survival_rate, $_SESSION['staff_id']]);

    $message = "Hatchery log recorded successfully!";
}

// Fetch last 10 hatchery logs
$stmt = $pdo->prepare("
    SELECT h.date, h.batch_id, h.stage, h.quantity, h.survival_rate, s.full_name AS officer
    FROM hatchery_logs h
    JOIN staff s ON h.officer = s.id
    WHERE h.batch_id IS NOT NULL
    ORDER BY h.id DESC
    LIMIT 10
");
$stmt->execute();
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hatchery Module | Yotribe Agro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/yotribe-system/public/css/custom.css">
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav id="sidebar" class="col-md-2 col-12 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/dashboard/index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/feeding/index.php">Feeding</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/feed_store/index.php">Feed Store</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/hatchery/index.php">Hatchery</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/mortality/index.php">Mortality</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/reports/index.php">Reports</a></li>
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
                    <span class="navbar-brand mb-0 h1">Hatchery Module</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Hatchery Module</h1>
            </div>

            <?php if($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <!-- Hatchery Form -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Batch ID</label>
                            <input type="text" name="batch_id" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Stage</label>
                            <select name="stage" class="form-select">
                                <option value="egg">Egg</option>
                                <option value="larvae">Larvae</option>
                                <option value="fry">Fry</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Survival Rate (%)</label>
                            <input type="number" step="0.01" name="survival_rate" class="form-control">
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-success">Log Hatchery</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Hatchery Logs -->
            <h4>Recent Hatchery Records</h4>
            <div class="card shadow-sm mb-4">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Batch ID</th>
                                <th>Stage</th>
                                <th>Quantity</th>
                                <th>Survival Rate (%)</th>
                                <th>Officer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($logs): ?>
                                <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?= $log['date'] ?></td>
                                    <td><?= htmlspecialchars($log['batch_id']) ?></td>
                                    <td><?= ucfirst($log['stage']) ?></td>
                                    <td><?= $log['quantity'] ?></td>
                                    <td><?= $log['survival_rate'] ?></td>
                                    <td><?= $log['officer'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No hatchery records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
