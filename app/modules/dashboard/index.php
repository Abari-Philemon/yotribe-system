<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';


authorize('dashboard');

/**
 * Farm context (from middleware)


/**
 *
 * KPI Queries
 */

$farm_id   = farm_id();
$farm_name = farm_name();
// Biomass
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(estimated_weight_kg),0)
    FROM fish_inventory
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);
$total_biomass = (float)$stmt->fetchColumn();

// Feed
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(quantity_kg),0)
    FROM feed_store
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);
$total_feed = (float)$stmt->fetchColumn();

// Sales
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount),0)
    FROM sales
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);
$total_sales = (float)$stmt->fetchColumn();

// Expenses
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM expenses
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);
$total_expenses = (float)$stmt->fetchColumn();

// Profit
$profit = $total_sales - $total_expenses;

// Mortality
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM mortality_logs
    WHERE farm_id = ?
      AND dead_count > 50
      AND date = CURDATE()
");
$stmt->execute([$farm_id]);
$high_mortality = (int)$stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard | Yotribe Agro</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/yotribe-system/public/css/custom.css">
<script src="/yotribe-system/public/js/Chart.min.js"></script>
</head>

<body>
<div class="container-fluid">
<div class="row">

<!-- SIDEBAR -->
<nav id="sidebar" class="col-md-2 d-md-block bg-light sidebar collapse vh-100">
    <div class="pt-3 text-center">
        <img src="/yotribe-system/public/uploads/logo8.png" class="img-fluid mb-2" style="max-height:140px">
        <div class="fw-bold"><?= htmlspecialchars($farm_name) ?></div>

        <!-- FARM SWITCH BUTTON -->
        <a href="/yotribe-system/app/modules/farms/select.php" 
           class="btn btn-sm btn-outline-primary mt-2">
           Switch Farm
        </a>
    </div>

    <ul class="nav flex-column mt-4">
        <li class="nav-item"><a class="nav-link active" href="#">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/feeding/index.php">Feeding</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/finance/index.php">Finance</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/feed_store/index.php">Feed Store</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/mortality/index.php">Mortality</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/hatchery/index.php">Hatchery</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/maggot/index.php">Maggot Production</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/water/index.php">Water Quality</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/reports/index.php">Reports</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="/yotribe-system/app/auth/logout.php">Logout</a></li>
    </ul>
</nav>

<!-- MAIN -->
<main class="col-md-10 ms-sm-auto px-md-4">

<!-- MOBILE NAV -->
<nav class="navbar navbar-light bg-light d-md-none mb-3">
    <button class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#sidebar">
        ☰ Menu
    </button>
    <span class="navbar-brand">Dashboard</span>
</nav>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h3">Executive Dashboard</h1>
    <span class="badge bg-dark">Farm: <?= htmlspecialchars($farm_name) ?></span>
</div>

<!-- KPI CARDS -->
<div class="row g-3">

<div class="col-md-3">
<div class="card bg-success text-white shadow">
<div class="card-body">
<h6>Biomass (kg)</h6>
<h4><?= number_format($total_biomass,2) ?></h4>
</div></div></div>

<div class="col-md-3">
<div class="card bg-primary text-white shadow">
<div class="card-body">
<h6>Feed Stock (kg)</h6>
<h4><?= number_format($total_feed,2) ?></h4>
</div></div></div>

<div class="col-md-3">
<div class="card bg-info text-white shadow">
<div class="card-body">
<h6>Sales</h6>
<h4><?= number_format($total_sales,2) ?></h4>
</div></div></div>

<div class="col-md-3">
<div class="card bg-warning text-dark shadow">
<div class="card-body">
<h6>Profit</h6>
<h4><?= number_format($profit,2) ?></h4>
</div></div></div>

<div class="col-md-3">
<div class="card bg-secondary text-white shadow">
<div class="card-body">
<h6>Expenses</h6>
<h4><?= number_format($total_expenses,2) ?></h4>
</div></div></div>

<div class="col-md-3">
<div class="card bg-danger text-white shadow">
<div class="card-body">
<h6>Mortality Alerts</h6>
<h4><?= $high_mortality ?> Today</h4>
</div></div></div>

</div>

<!-- CHARTS -->
<div class="row mt-4">

<div class="col-md-6">
<div class="card shadow">
<div class="card-body">
<h6>Weekly Biomass Trend</h6>
<canvas id="biomassChart"></canvas>
</div></div></div>

<div class="col-md-6">
<div class="card shadow">
<div class="card-body">
<h6>Weekly Sales Trend</h6>
<canvas id="salesChart"></canvas>
</div></div></div>

</div>

</main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Biomass Chart
fetch('charts.php?type=biomass&farm_id=<?= $farm_id ?>')
.then(r => r.json())
.then(d => new Chart(document.getElementById('biomassChart'), {
    type: 'line',
    data: {
        labels: d.labels,
        datasets: [{
            label: 'Biomass',
            data: d.values,
            borderWidth: 2
        }]
    }
}));

// Sales Chart
fetch('charts.php?type=sales&farm_id=<?= $farm_id ?>')
.then(r => r.json())
.then(d => new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: d.labels,
        datasets: [{
            label: 'Sales',
            data: d.values
        }]
    }
}));
</script>

</body>
</html>