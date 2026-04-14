<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('reports');

$date = $_GET['date'] ?? date('Y-m-d');

// Fetch Feeding
$feeding = $pdo->prepare("
    SELECT p.pond_code, f.feed_type, f.quantity_kg
    FROM feeding_logs f
    JOIN ponds_tanks p ON p.id = f.pond_id
    WHERE f.date=?
");
$feeding->execute([$date]);

// Fetch Mortality
$mortality = $pdo->prepare("
    SELECT p.pond_code, m.dead_count, m.suspected_cause
    FROM mortality_logs m
    JOIN ponds_tanks p ON p.id = m.pond_id
    WHERE m.date=?
");
$mortality->execute([$date]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Operations Report | <?= $date ?> | Yotribe Agro</title>
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
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/water/index.php">Water Quality</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Reports</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 ms-sm-auto px-md-4">

            <!-- Hamburger for mobile -->
            <nav class="navbar navbar-light bg-light d-md-none mb-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        ☰ Menu
                    </button>
                    <span class="navbar-brand mb-0 h1">Daily Operations Report</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Daily Operations Report — <?= $date ?></h1>
                <form class="d-flex" method="get">
                    <input type="date" name="date" class="form-control form-control-sm me-2" value="<?= $date ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                </form>
            </div>

            <!-- Feeding Table -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    Feeding Records
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Pond</th>
                                <th>Feed Type</th>
                                <th>Quantity (kg)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($feeding->rowCount()): ?>
                                <?php foreach($feeding as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['pond_code']) ?></td>
                                    <td><?= htmlspecialchars($f['feed_type']) ?></td>
                                    <td><?= number_format($f['quantity_kg'],2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No feeding records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mortality Table -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-danger text-white">
                    Mortality Records
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Pond</th>
                                <th>Deaths</th>
                                <th>Suspected Cause</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($mortality->rowCount()): ?>
                                <?php foreach($mortality as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['pond_code']) ?></td>
                                    <td><?= $m['dead_count'] ?></td>
                                    <td><?= htmlspecialchars($m['suspected_cause']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No mortality records found.</td>
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
