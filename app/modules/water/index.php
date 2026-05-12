<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';
require_once __DIR__ . '/../../helpers/permission.php';

requireModuleAccess('module_name');

$farm_id = farm_id();
require_role(['manager','owner']);

$farm_id = $_SESSION['farm_id'];
$message = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $pond_id = $_POST['pond_id'];
    $temperature = $_POST['temperature'];
    $ph = $_POST['ph'];
    $dissolved_oxygen = $_POST['dissolved_oxygen'];
    $observation = $_POST['observation'];

    $stmt = $pdo->prepare("
        INSERT INTO water_quality_logs 
        (date, farm_id, pond_id, temperature, ph, dissolved_oxygen, observation, checked_by)
        VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$farm_id, $pond_id, $temperature, $ph, $dissolved_oxygen, $observation, $_SESSION['staff_id']]);

    $message = "Water quality logged successfully!";
}

// Fetch ponds
$stmt = $pdo->prepare("SELECT * FROM ponds_tanks WHERE farm_id = ?");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll();

// Fetch last 10 water logs
$stmt = $pdo->prepare("
    SELECT w.date, p.pond_code, w.temperature, w.ph, w.dissolved_oxygen, w.observation, s.full_name AS checked_by
    FROM water_quality_logs w
    JOIN ponds_tanks p ON w.pond_id = p.id
    JOIN staff s ON w.checked_by = s.id
    WHERE w.farm_id = ?
    ORDER BY w.id DESC
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
<title>Water Quality | Yotribe Agro</title>
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
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/maggot/index.php">Maggot Production</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/yotribe-system/app/modules/water/index.php">Water Quality</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/reports/index.php">Reports</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 ms-sm-auto px-md-4">

            <!-- Hamburger for small screens -->
            <nav class="navbar navbar-light bg-light d-md-none mb-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        ☰ Menu
                    </button>
                    <span class="navbar-brand mb-0 h1">Water Quality</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Water Quality Module</h1>
            </div>

            <?php if($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <!-- Water Quality Form -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Pond</label>
                            <select name="pond_id" class="form-select" required>
                                <?php foreach($ponds as $pond): ?>
                                    <option value="<?= $pond['id'] ?>"><?= $pond['pond_code'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Temperature (°C)</label>
                            <input type="number" step="0.1" name="temperature" class="form-control" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">pH</label>
                            <input type="number" step="0.1" name="ph" class="form-control" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Dissolved Oxygen (mg/L)</label>
                            <input type="number" step="0.1" name="dissolved_oxygen" class="form-control" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Observation</label>
                            <input type="text" name="observation" class="form-control">
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-primary">Log Water Quality</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Water Logs -->
            <h4>Recent Water Quality Records</h4>
            <div class="card shadow-sm mb-4">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Pond</th>
                                <th>Temperature (°C)</th>
                                <th>pH</th>
                                <th>Dissolved Oxygen (mg/L)</th>
                                <th>Observation</th>
                                <th>Checked By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($logs): ?>
                                <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?= $log['date'] ?></td>
                                    <td><?= htmlspecialchars($log['pond_code']) ?></td>
                                    <td><?= $log['temperature'] ?></td>
                                    <td><?= $log['ph'] ?></td>
                                    <td><?= $log['dissolved_oxygen'] ?></td>
                                    <td><?= htmlspecialchars($log['observation']) ?></td>
                                    <td><?= $log['checked_by'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No water quality records found.</td>
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
