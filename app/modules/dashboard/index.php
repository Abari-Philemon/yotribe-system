<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../middleware/csrf.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

requireModuleAccess('module_name');

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

/**
 * LOAD ALL REQUIRED DATA IN BULK (OPTIMIZED)
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

/**
 * PRELOAD FEED TODAY (GROUPED)
 */
$stmt = $pdo->prepare("
    SELECT pond_id, SUM(quantity_kg) AS fed_today
    FROM feeding_logs
    WHERE farm_id = ? AND date = CURDATE()
    GROUP BY pond_id
");
$stmt->execute([$farm_id]);
$feed_today_map = [];
foreach ($stmt->fetchAll() as $f) {
    $feed_today_map[$f['pond_id']] = (float)$f['fed_today'];
}

/**
 * PRELOAD GROWTH RANGE
 */
$stmt = $pdo->prepare("
    SELECT pond_id, batch_id,
           MIN(avg_weight_g) AS start_w,
           MAX(avg_weight_g) AS end_w
    FROM fish_growth_logs
    WHERE farm_id = ?
    GROUP BY pond_id, batch_id
");
$stmt->execute([$farm_id]);

$growth_map = [];
foreach ($stmt->fetchAll() as $g) {
    $key = $g['pond_id'].'_'.$g['batch_id'];
    $growth_map[$key] = $g;
}

/**
 * PRELOAD TOTAL FEED (PER POND)
 */
$stmt = $pdo->prepare("
    SELECT pond_id, SUM(quantity_kg) AS total_feed
    FROM feeding_logs
    WHERE farm_id = ?
    GROUP BY pond_id
");
$stmt->execute([$farm_id]);

$feed_total_map = [];
foreach ($stmt->fetchAll() as $f) {
    $feed_total_map[$f['pond_id']] = (float)$f['total_feed'];
}

/**
 * LOOP (CLEAN + FAST)
 */
foreach ($stocks as $s) {

    $pond_id  = $s['pond_id'];
    $batch_id = $s['batch_id'];
    $key      = $pond_id.'_'.$batch_id;

    /**
     * GROWTH INTELLIGENCE
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
        $attention[$s['pond_code']] = "{$s['pond_code']}: {$alert}";
    }

    /**
     * FEEDING CONTROL
     */
    $fed_today = $feed_today_map[$pond_id] ?? 0;

    if ($s['current_count'] > 0 && $s['avg_weight_g'] > 0) {

        $biomass = ($s['current_count'] * $s['avg_weight_g']) / 1000;

        if ($s['avg_weight_g'] < 50) $rate = 0.12;
        elseif ($s['avg_weight_g'] < 200) $rate = 0.07;
        else $rate = 0.04;

        $recommended = $biomass * $rate;

        $feeding_data[] = [
            'pond' => $s['pond_code'],
            'recommended' => $recommended,
            'actual' => $fed_today
        ];

        if ($fed_today > $recommended) {
            $attention[$s['pond_code'].'_feed'] = "{$s['pond_code']}: Overfeeding detected";
        }
    }

    /**
     * TRUE FCR (SCIENTIFIC)
     */
    if (isset($growth_map[$key]) && isset($feed_total_map[$pond_id])) {

        $g = $growth_map[$key];

        if ($g['end_w'] > $g['start_w']) {

            $weight_gain = $g['end_w'] - $g['start_w'];
            $biomass_gain = ($weight_gain * $s['current_count']) / 1000;

            if ($biomass_gain > 0) {

                $fcr = $feed_total_map[$pond_id] / $biomass_gain;

                $fcr_data[] = [
                    'pond' => $s['pond_code'],
                    'fcr'  => $fcr
                ];

                if ($fcr > 2) {
                    $attention[$s['pond_code'].'_fcr'] = "{$s['pond_code']}: Poor FCR";
                }
            }
        }
    }
}

/**
 * MORTALITY SPIKE
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
        $attention[$m['pond_code'].'_mort'] = "{$m['pond_code']}: Mortality spike ({$m['deaths']})";
    }
}

/**
 * FINAL CLEAN ARRAY
 */
$attention = array_values($attention);
$page_title = "Dashboard";
/**
 * PASS DATA TO VIEW LAYER
 */
$view_data = [
    'farm_name'      => $farm_name,
    'farm_location'  => $farm_location,
    'farm_size'      => $farm_size,

    'total_biomass'  => $total_biomass,
    'total_feed'     => $total_feed,
    'total_sales'    => $total_sales,
    'total_expenses' => $total_expenses,
    'profit'         => $profit,
    'high_mortality' => $high_mortality,

    'alerts'         => $alerts,
    'growth_data'    => $growth_data,
    'feeding_data'   => $feeding_data,
    'fcr_data'       => $fcr_data,
    'attention'      => $attention,
];

/* your queries here */

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

           <!-- HERO HEADER -->
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">

        <div>
            <h2 class="mb-0 fw-bold"><?= htmlspecialchars($farm_name) ?></h2>
            <small class="text-muted">
                <?= htmlspecialchars($farm_location) ?> • <?= $farm_size ?> Farm
            </small>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <select id="farmSwitcher" class="form-select form-select-sm shadow-sm"></select>
        </div>

    </div>


<!-- ALERT STRIP -->
<div class="alert alert-danger d-flex justify-content-between align-items-start shadow-sm">
    <div>
        <strong>System Alerts</strong><br>

        <?php if (empty($attention)): ?>
            <span class="text-muted">All systems stable</span>
        <?php else: ?>
            <ul class="mb-0">
                <?php foreach ($attention as $a): ?>
                    <li><?= htmlspecialchars($a) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- KPI GRID (EXECUTIVE METRICS) -->
 
<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <small class="text-muted">Biomass</small>
                <h4 class="fw-bold"><?= number_format($total_biomass,2) ?> kg</h4>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <small class="text-muted">Feed Stock</small>
                <h4 class="fw-bold"><?= number_format($total_feed,2) ?> kg</h4>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <small class="text-muted">Revenue</small>
                <h4 class="fw-bold">₦<?= number_format($total_sales,2) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <small class="text-muted">Net Profit</small>
                <h4 class="fw-bold <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                    ₦<?= number_format($profit,2) ?>
                </h4>
            </div>
        </div>
    </div>

</div>

<!-- ANALYTICS TABS -->
<ul class="nav nav-pills mb-3" id="analyticsTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#growth">Growth Intelligence</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#feeding">Feeding Analytics</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#fcr">FCR Analytics</button>
    </li>
</ul>

<div class="tab-content">

    <!-- GROWTH -->
    <div class="tab-pane fade show active" id="growth">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <strong>Growth Intelligence Engine</strong>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Pond</th>
                            <th>SGR</th>
                            <th>Prediction</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($growth_data as $g): ?>
                        <tr>
                            <td class="fw-bold"><?= $g['pond'] ?></td>
                            <td><?= $g['sgr'] ?>%</td>
                            <td><?= round($g['pred'],2) ?>g</td>
                            <td>
                                <span class="badge bg-<?= $g['alert'] ? 'danger' : 'success' ?>">
                                    <?= $g['alert'] ?: 'OPTIMAL' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FEEDING -->
    <div class="tab-pane fade" id="feeding">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <strong>Feeding Efficiency Monitor</strong>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Pond</th>
                            <th>Recommended</th>
                            <th>Actual</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeding_data as $f): ?>
                        <tr>
                            <td class="fw-bold"><?= $f['pond'] ?></td>
                            <td><?= round($f['recommended'],2) ?> kg</td>
                            <td class="<?= $f['actual'] > $f['recommended'] ? 'text-danger' : 'text-success' ?>">
                                <?= round($f['actual'],2) ?> kg
                            </td>
                            <td>
                                <?php if ($f['actual'] > $f['recommended']): ?>
                                    <span class="badge bg-danger">OVERFEED</span>
                                <?php else: ?>
                                    <span class="badge bg-success">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- FCR -->
    <div class="tab-pane fade" id="fcr">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <strong>Feed Conversion Ratio (Scientific Model)</strong>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Pond</th>
                            <th>FCR</th>
                            <th>Efficiency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fcr_data as $f): ?>
                        <tr>
                            <td class="fw-bold"><?= $f['pond'] ?></td>
                            <td><?= round($f['fcr'],2) ?></td>
                            <td>
                                <?php if ($f['fcr'] <= 1.8): ?>
                                    <span class="badge bg-success">EXCELLENT</span>
                                <?php elseif ($f['fcr'] <= 2): ?>
                                    <span class="badge bg-warning text-dark">GOOD</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">POOR</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- CHART SECTION -->
<div class="row g-3 mt-4">

    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                Biomass Trend Analysis
            </div>
            <div class="card-body">
                <canvas id="biomassChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                Sales Performance Trend
            </div>
            <div class="card-body">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
    </div>

</div>

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
<script>
async function loadLiveDashboard() {
    try {
        const res = await fetch('/yotribe-system/app/api/dashboard_realtime.php');
        const data = await res.json();

        document.getElementById('biomass').innerText = Number(data.biomass).toFixed(2);
        document.getElementById('feed').innerText = Number(data.feed).toFixed(2);
        document.getElementById('sales').innerText = Number(data.sales).toLocaleString();
        document.getElementById('profit').innerText = Number(data.profit).toLocaleString();
        document.getElementById('feed_today').innerText = Number(data.feed_today).toFixed(2);
        document.getElementById('alerts').innerText = data.alerts;

    } catch (e) {
        console.log("Live update error", e);
    }
}

/**
 * LIVE LOOP (REAL TIME FEEL)
 */
loadLiveDashboard();
setInterval(loadLiveDashboard, 5000); // every 5 seconds
</script>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
