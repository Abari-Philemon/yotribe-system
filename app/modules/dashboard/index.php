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

/* your queries here */

require_once __DIR__ .'/../../includes/header.php';
require_once __DIR__ .'/../../includes/sidebar.php';
?>


<!-- MAIN -->
<div class="main">

<!-- MOBILE NAV -->
<button class="btn btn-primary d-md-none mb-3" onclick="toggleSidebar()">☰ Menu</button>

<!-- HERO -->
<div class="hero mb-4 d-flex justify-content-between align-items-center flex-wrap">
<div>
<h4><?= htmlspecialchars($farm_name) ?></h4>
<small><?= $farm_location ?></small>
</div>

<select id="farmSwitcher" class="form-select form-select-sm" style="width:auto;">
<option>Loading...</option>
</select>
</div>

<!-- ALERT -->
<div class="alert alert-danger">
<strong>⚠ Attention</strong>
<?php if(empty($attention)): ?>
<div class="text-muted">No issues</div>
<?php else: ?>
<ul>
<?php foreach($attention as $a): ?>
<li><?= htmlspecialchars($a) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="kpi">
<small>Biomass</small>
<h4><?= number_format($total_biomass,2) ?> kg</h4>
</div>
</div>

<div class="col-md-3">
<div class="kpi">
<small>Feed</small>
<h4><?= number_format($total_feed,2) ?> kg</h4>
</div>
</div>

<div class="col-md-3">
<div class="kpi">
<small>Sales</small>
<h4>₦<?= number_format($total_sales,2) ?></h4>
</div>
</div>

<div class="col-md-3">
<div class="kpi">
<small>Profit</small>
<h4 class="<?= $profit >=0 ? 'text-success':'text-danger' ?>">
₦<?= number_format($profit,2) ?>
</h4>
</div>
</div>

</div>

<!-- CHARTS -->
<div class="row g-4 mb-4">

<div class="col-md-6">
<div class="cardx">
<h6>Biomass Trend</h6>
<canvas id="biomassChart"></canvas>
</div>
</div>

<div class="col-md-6">
<div class="cardx">
<h6>Sales Trend</h6>
<canvas id="salesChart"></canvas>
</div>
</div>

</div>

</div>

<?php require_once '/../../includes/footer.php'; ?>

</body>
</html>