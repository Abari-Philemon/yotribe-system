<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';
require_once __DIR__ . '/../../helpers/permission.php';

requireModuleAccess('module_name');

$farm_id = farm_id();

$message = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $production_unit = $_POST['production_unit'];
    $input_material = $_POST['input_material'];
    $quantity_produced = $_POST['quantity_produced'];
    $used_or_stored = $_POST['used_or_stored'];

    $stmt = $pdo->prepare("
        INSERT INTO maggot_logs (date, production_unit, input_material, quantity_produced, used_or_stored, officer)
        VALUES (CURDATE(), ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$production_unit, $input_material, $quantity_produced, $used_or_stored, $_SESSION['staff_id']]);

    $message = "Maggot production logged successfully!";
}

// Fetch last 10 logs
$stmt = $pdo->query("
    SELECT m.date, m.production_unit, m.input_material, m.quantity_produced, m.used_or_stored, s.full_name AS officer
    FROM maggot_logs m
    JOIN staff s ON m.officer = s.id
    ORDER BY m.id DESC
    LIMIT 10
");
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maggot Production | Yotribe Agro</title>
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
                    <li class="nav-item"><a class="nav-link active" href="/yotribe-system/app/modules/maggot/index.php">Maggot Production</a></li>
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
                    <span class="navbar-brand mb-0 h1">Maggot Production</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Maggot Production Module</h1>
            </div>

            <?php if($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <!-- Production Form -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Production Unit</label>
                            <input type="text" name="production_unit" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Input Material</label>
                            <textarea name="input_material" class="form-control" required></textarea>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Quantity Produced (kg)</label>
                            <input type="number" step="0.01" name="quantity_produced" class="form-control" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Used or Stored</label>
                            <input type="text" name="used_or_stored" class="form-control">
                        </div>

                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-success">Log Production</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Maggot Logs -->
            <h4>Recent Maggot Production Records</h4>
            <div class="card shadow-sm mb-4">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Production Unit</th>
                                <th>Input Material</th>
                                <th>Quantity Produced (kg)</th>
                                <th>Used or Stored</th>
                                <th>Officer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($logs): ?>
                                <?php foreach($logs as $log): ?>
                                <tr>
                                    <td><?= $log['date'] ?></td>
                                    <td><?= htmlspecialchars($log['production_unit']) ?></td>
                                    <td><?= htmlspecialchars($log['input_material']) ?></td>
                                    <td><?= number_format($log['quantity_produced'],2) ?></td>
                                    <td><?= htmlspecialchars($log['used_or_stored']) ?></td>
                                    <td><?= $log['officer'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No maggot production records found.</td>
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
