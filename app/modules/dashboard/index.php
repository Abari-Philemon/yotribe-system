<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../middleware/csrf.php';
require_once __DIR__ . '/../../config/database.php';


authorize('dashboard');

/**
 * FARM CONTEXT (SECURE)
 */
$farm_id = farm_id();


/**
 * FETCH FARM DETAILS
 */
$stmt = $pdo->prepare("
    SELECT name, location, size
    FROM farms
    WHERE id = ?
");
$stmt->execute([$farm_id]);
$farm = $stmt->fetch(PDO::FETCH_ASSOC);

$farm_name     = $farm['name'] ?? 'Unknown Farm';
$farm_location = $farm['location'] ?? '';
$farm_size     = ucfirst($farm['size'] ?? '');

/**
 * KPI QUERIES
 */

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

$stmt = $pdo->prepare("
    SELECT pa.*, p.pond_code 
    FROM pond_alerts pa
    JOIN ponds_tanks p ON p.id = pa.pond_id
    WHERE pa.farm_id = ?
    ORDER BY pa.id DESC
    LIMIT 5
");
$stmt->execute([farm_id()]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../../helpers/growth_helper.php';

/**
 * LOAD ACTIVE STOCKING
 */
$stmt = $pdo->prepare("
    SELECT 
        ps.pond_id,
        ps.batch_id,
        ps.current_count,
        ps.avg_weight_g,
        p.pond_code
    FROM pond_stocking ps
    JOIN ponds_tanks p ON p.id = ps.pond_id
    WHERE ps.farm_id = ? AND ps.status = 'active'
");
$stmt->execute([$farm_id]);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attention = [];
$growth_data = [];
$feeding_data = [];
$fcr_data = [];

foreach ($stocks as $s) {

    $pond_id  = $s['pond_id'];
    $batch_id = $s['batch_id'];

    /**
     * GROWTH
     */
    $sgr       = calculateSGR($pdo, $pond_id, $batch_id);
    $predicted = predictNextWeight($pdo, $pond_id, $batch_id);
    $alert     = growthAlert($pdo, $pond_id, $batch_id);

    $growth_data[] = [
        'pond' => $s['pond_code'],
        'sgr'  => $sgr,
        'pred' => $predicted,
        'alert'=> $alert
    ];

    if ($alert) {
        $attention[] = "{$s['pond_code']}: {$alert}";
    }

    /**
     * FEEDING TODAY
     */
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity_kg),0)
        FROM feeding_logs
        WHERE pond_id = ? AND date = CURDATE()
    ");
    $stmt->execute([$pond_id]);
    $fed_today = (float)$stmt->fetchColumn();

    if ($s['current_count'] > 0 && $s['avg_weight_g'] > 0) {

        $biomass = ($s['current_count'] * $s['avg_weight_g']) / 1000;

        if ($s['avg_weight_g'] < 50) $rate = 0.05;
        elseif ($s['avg_weight_g'] < 200) $rate = 0.03;
        else $rate = 0.02;

        $recommended = $biomass * $rate;

        $feeding_data[] = [
            'pond' => $s['pond_code'],
            'recommended' => $recommended,
            'actual' => $fed_today
        ];

        if ($fed_today > $recommended) {
            $attention[] = "{$s['pond_code']}: Overfeeding detected";
        }
    }

    /**
     * FCR (IMPROVED)
     */
    $stmt = $pdo->prepare("
        SELECT 
            SUM(fl.quantity_kg) AS feed,
            MIN(gl.avg_weight_g) AS start_w,
            MAX(gl.avg_weight_g) AS end_w
        FROM feeding_logs fl
        JOIN fish_growth_logs gl 
            ON gl.batch_id = ? AND gl.pond_id = ?
        WHERE fl.pond_id = ?
    ");
    $stmt->execute([$batch_id, $pond_id, $pond_id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($f && $f['end_w'] > $f['start_w']) {

        $weight_gain = $f['end_w'] - $f['start_w'];
        $biomass_gain = ($weight_gain * $s['current_count']) / 1000;

        if ($biomass_gain > 0) {
            $fcr = $f['feed'] / $biomass_gain;

            $fcr_data[] = [
                'pond' => $s['pond_code'],
                'fcr'  => $fcr
            ];

            if ($fcr > 2) {
                $attention[] = "{$s['pond_code']}: Poor FCR";
            }
        }
    }
}

/**
 * MORTALITY SPIKE (SMARTER)
 */
$stmt = $pdo->prepare("
    SELECT p.pond_code, SUM(m.dead_count) AS deaths
    FROM mortality_logs m
    JOIN ponds_tanks p ON p.id = m.pond_id
    WHERE m.farm_id = ?
    AND m.date >= CURDATE() - INTERVAL 3 DAY
    GROUP BY m.pond_id
");
$stmt->execute([$farm_id]);

foreach ($stmt->fetchAll() as $m) {
    if ($m['deaths'] > 30) {
        $attention[] = "{$m['pond_code']}: Mortality spike ({$m['deaths']})";
    }
}
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
        <small class="text-muted d-block"><?= htmlspecialchars($farm_size) ?> Farm</small>
        <small class="text-muted"><?= htmlspecialchars($farm_location) ?></small>

        <!-- SWITCH FARM -->
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
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/ponds/index.php">Ponds</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/stocking/index.php">Stocking</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/mortality/index.php">Mortality</a></li>
        <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/growth/index.php">Growth</a></li>
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
    <div class="alert alert-danger shadow-sm">
    <strong>⚠ Attention Required</strong>

    <?php if (empty($attention)): ?>
        <div class="text-muted">No critical issues detected</div>
    <?php else: ?>
        <ul class="mb-0">
            <?php foreach ($attention as $a): ?>
                <li><?= htmlspecialchars($a) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    </div>

    <div class="d-flex align-items-center gap-2">

        <!-- FARM DROPDOWN -->
        <select id="farmSwitcher" class="form-select form-select-sm" style="width:auto;">
            <option>Loading farms...</option>
        </select>

    </div>

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
<h6>Total Sales (₦)</h6>
<h4><?= number_format($total_sales,2) ?></h4>
</div></div></div>

<div class="col-md-3">
<div class="card bg-warning text-dark shadow">
<div class="card-body">
<h6>Net Profit (₦)</h6>
<h4><?= number_format($profit,2) ?></h4>
</div></div></div>

<div class="col-md-3">
<div class="card bg-secondary text-white shadow">
<div class="card-body">
<h6>Total Expenses (₦)</h6>
<h4><?= number_format($total_expenses,2) ?></h4>
</div></div></div>

<div class="col-md-3">
<div class="card bg-danger text-white shadow">
<div class="card-body">
<h6>Mortality Alerts</h6>
<h4><?= $high_mortality ?> Today</h4>
</div></div></div>

</div>
<div class="card mt-4 shadow">
<div class="card-body">
<h6>Growth Intelligence</h6>

<table class="table table-sm">
<tr><th>Pond</th><th>SGR</th><th>Prediction</th><th>Status</th></tr>

<?php foreach ($growth_data as $g): ?>
<tr>
<td><?= $g['pond'] ?></td>
<td><?= $g['sgr'] ?? '-' ?>%</td>
<td><?= $g['pred'] ? round($g['pred'],2).'g' : '-' ?></td>
<td><?= $g['alert'] ?? 'OK' ?></td>
</tr>
<?php endforeach; ?>

</table>
</div>
</div>
<div class="card mt-4 shadow">
<div class="card-body">
<h6>Feeding Control (Today)</h6>

<table class="table table-sm">
<tr><th>Pond</th><th>Recommended</th><th>Actual</th></tr>

<?php foreach ($feeding_data as $f): ?>
<tr>
<td><?= $f['pond'] ?></td>
<td><?= round($f['recommended'],2) ?> kg</td>
<td class="<?= $f['actual'] > $f['recommended'] ? 'text-danger' : 'text-success' ?>">
<?= round($f['actual'],2) ?> kg
</td>
</tr>
<?php endforeach; ?>

</table>
</div>
</div>
<div class="card mt-4 shadow">
<div class="card-body">
<h6>FCR Monitoring</h6>

<table class="table table-sm">
<tr><th>Pond</th><th>FCR</th></tr>

<?php foreach ($fcr_data as $f): ?>
<tr>
<td><?= $f['pond'] ?></td>
<td class="<?= $f['fcr'] > 2 ? 'text-danger' : 'text-success' ?>">
<?= round($f['fcr'],2) ?>
</td>
</tr>
<?php endforeach; ?>

</table>
</div>
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
<div class="card mt-4">
<div class="card-body">
<h6>⚠ Capacity Alerts</h6>

<?php if (empty($alerts)): ?>
    <p class="text-muted">No alerts</p>
<?php else: ?>
    <?php foreach ($alerts as $a): ?>
        <div class="alert alert-<?= 
            $a['level'] === 'critical' ? 'danger' :
            ($a['level'] === 'high' ? 'warning' : 'info')
        ?>">
            <strong><?= $a['pond_code'] ?></strong> — <?= $a['message'] ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</div>
</div>

</main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const CSRF_TOKEN = "<?= csrf_token() ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Biomass Chart (secure - no farm_id in URL)
fetch('charts.php?type=biomass')
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
fetch('charts.php?type=sales')
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
<script>
// Load farms into dropdown
fetch('/yotribe-system/app/modules/farms/list.php')
.then(res => res.json())
.then(farms => {

    const select = document.getElementById('farmSwitcher');
    select.innerHTML = '';

    farms.forEach(farm => {
        const option = document.createElement('option');
        option.value = farm.id;
        option.text  = farm.name;

        if (farm.id == <?= $farm_id ?>) {
            option.selected = true;
        }

        select.appendChild(option);
    });
});

// Handle farm switch
document.getElementById('farmSwitcher').addEventListener('change', function () {

    fetch('/yotribe-system/app/modules/farms/switch_live.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'farm_id=' + this.value + '&csrf_token=' + CSRF_TOKEN
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            location.reload();
        } else {
            alert(res.message || 'Switch failed');
        }
    });

});
</script>

</body>
</html>