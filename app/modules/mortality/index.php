<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_role(['manager','owner','hatchery']);

$farm_id = $_SESSION['farm_id'];
$message = '';

// Log mortality
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $pond_id = $_POST['pond_id'];
    $dead_count = $_POST['dead_count'];
    $suspected_cause = $_POST['suspected_cause'];
    $action_taken = $_POST['action_taken'];

    $stmt = $pdo->prepare("
        INSERT INTO mortality_logs (date, farm_id, pond_id, dead_count, suspected_cause, action_taken, reported_by) 
        VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$farm_id, $pond_id, $dead_count, $suspected_cause, $action_taken, $_SESSION['staff_id']]);

    $message = "Mortality logged successfully!";
}

// Fetch ponds
$stmt = $pdo->prepare("SELECT * FROM ponds_tanks WHERE farm_id = ?");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll();

// Fetch last 10 mortality logs
$stmt = $pdo->prepare("
    SELECT m.date, p.pond_code, m.dead_count, m.suspected_cause, m.action_taken, s.full_name AS reported_by
    FROM mortality_logs m
    JOIN ponds_tanks p ON m.pond_id = p.id
    JOIN staff s ON m.reported_by = s.id
    WHERE m.farm_id = ?
    ORDER BY m.id DESC
    LIMIT 10
");
$stmt->execute([$farm_id]);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mortality Module | Yotribe Agro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/yotribe-system/public/css/custom.css">
<style>
.sidebar { min-height: 100vh; }
.table thead th { vertical-align: middle; }
</style>
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
                    <li class="nav-item"><a class="nav-link active" href="#">Mortality</a></li>
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
                    <span class="navbar-brand mb-0 h1">Mortality Module</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Mortality Module</h1>
            </div>

            <?php if($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <!-- Mortality Form -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Pond</label>
                            <select name="pond_id" class="form-select" required>
                                <?php foreach($ponds as $pond): ?>
                                    <option value="<?= $pond['id'] ?>"><?= $pond['pond_code'] ?> (<?= $pond['pond_type'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Number Dead</label>
                            <input type="number" name="dead_count" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Suspected Cause</label>
                            <input type="text" name="suspected_cause" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Action Taken</label>
                            <textarea name="action_taken" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-danger">Log Mortality</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Mortality Logs -->
            <h4>Recent Mortality Records</h4>
            <div class="card shadow-sm mb-4">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Pond</th>
                                <th>Dead Count</th>
                                <th>Suspected Cause</th>
                                <th>Action Taken</th>
                                <th>Reported By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($logs): ?>
                                <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?= $log['date'] ?></td>
                                    <td><?= $log['pond_code'] ?></td>
                                    <td><?= $log['dead_count'] ?></td>
                                    <td><?= htmlspecialchars($log['suspected_cause']) ?></td>
                                    <td><?= htmlspecialchars($log['action_taken']) ?></td>
                                    <td><?= $log['reported_by'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No mortality records found.</td>
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
