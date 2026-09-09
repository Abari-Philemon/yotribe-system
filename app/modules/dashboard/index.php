<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../middleware/csrf.php';
require_once __DIR__ . '/../../config/database.php'; 
require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/../../helpers/rbac.php';

/**
 * MODULE ACCESS
 */
require_permission('dashboard');

/**
 * FARM CONTEXT
 */
$farm_id = farm_id();

/**
 * PAGE TITLE
 */
$page_title = "Dashboard";


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

// Fish Population
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(current_count), 0)
    FROM pond_stocking
    WHERE farm_id = ?
      AND status = 'active'
");
$stmt->execute([$farm_id]);
$total_fish_population = (int)$stmt->fetchColumn();

// Active Batches
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM fish_batches
    WHERE farm_id = ?
      AND status = 'active'
");
$stmt->execute([$farm_id]);
$active_batches = (int)$stmt->fetchColumn();

// Harvest Inventory
$stmt = $pdo->prepare("
    SELECT
        COUNT(DISTINCT hp.batch_id) AS harvested_batches,
        COALESCE(SUM(hp.harvested_count), 0) AS harvested_fish,
        COALESCE(SUM(hp.harvested_weight_kg), 0) AS harvested_weight_kg
    FROM harvest_ponds hp
    INNER JOIN harvests h
        ON h.id = hp.harvest_id
    WHERE h.farm_id = ?
");
$stmt->execute([$farm_id]);

$harvest_inventory = $stmt->fetch(PDO::FETCH_ASSOC);

$harvested_batches    = (int)($harvest_inventory['harvested_batches'] ?? 0);
$total_harvested_fish = (int)($harvest_inventory['harvested_fish'] ?? 0);
$total_harvested_weight = (float)($harvest_inventory['harvested_weight_kg'] ?? 0);

/**
 * AVAILABLE HARVEST INVENTORY
 */

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(hp.available_count), 0) AS available_fish,
        COALESCE(SUM(hp.available_weight_kg), 0) AS available_weight_kg
    FROM harvest_ponds hp
    INNER JOIN harvests h
        ON h.id = hp.harvest_id
    WHERE h.farm_id = ?
");
$stmt->execute([$farm_id]);

$available_inventory = $stmt->fetch(PDO::FETCH_ASSOC);

$available_harvest_fish = (int)($available_inventory['available_fish'] ?? 0);
$available_harvest_weight = (float)($available_inventory['available_weight_kg'] ?? 0);

// Feed
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(quantity_kg),0)
    FROM feed_store
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);
$total_feed = (float)$stmt->fetchColumn();

/**
 * POND OPERATIONS SNAPSHOT
 */

// Total ponds
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM ponds_tanks
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);
$total_ponds = (int)$stmt->fetchColumn();


// Active ponds
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM ponds_tanks
    WHERE farm_id = ?
      AND status = 'active'
");
$stmt->execute([$farm_id]);
$active_ponds = (int)$stmt->fetchColumn();


// Occupied active ponds
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM ponds_tanks p
    WHERE p.farm_id = ?
      AND p.status = 'active'
      AND EXISTS (
          SELECT 1
          FROM pond_stocking ps
          WHERE ps.pond_id = p.id
            AND ps.farm_id = ?
            AND ps.status = 'active'
            AND ps.current_count > 0
      )
");
$stmt->execute([$farm_id, $farm_id]);
$occupied_ponds = (int)$stmt->fetchColumn();


// Empty active ponds
$empty_active_ponds = max(
    0,
    $active_ponds - $occupied_ponds
);

/**
 * FINANCIAL PERMISSION
 */
$user_role = $_SESSION['role'] ?? '';

$can_view_financials = in_array(
    $user_role,
    [
        'owner',
        'super_admin',
       
    ]
);

/**
 * SALES / EXPENSES / PROFIT
 * Only visible to owner, admin and manager
 */
$total_sales = 0;
$total_expenses = 0;
$profit = 0;

if ($can_view_financials) {

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount),0)
        FROM sales
        WHERE farm_id = ?
    ");
    $stmt->execute([$farm_id]);
    $total_sales = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0)
        FROM expenses
        WHERE farm_id = ?
    ");
    $stmt->execute([$farm_id]);
    $total_expenses = (float)$stmt->fetchColumn();

    $profit = $total_sales - $total_expenses;
}
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

    'total_ponds'         => $total_ponds,
    'active_ponds'        => $active_ponds,
    'occupied_ponds'      => $occupied_ponds,
    'empty_active_ponds'  => $empty_active_ponds,

    'active_batches'          => $active_batches,
    'total_fish_population'   => $total_fish_population,

    'harvested_batches'       => $harvested_batches,
    'total_harvested_fish'    => $total_harvested_fish,
    'total_harvested_weight'  => $total_harvested_weight,

    'available_harvest_fish'   => $available_harvest_fish,
    'available_harvest_weight' => $available_harvest_weight,

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

<!-- =========================================================
     YOTRIBE EXECUTIVE DASHBOARD HEADER
     ========================================================= -->

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3 px-4">

        <div class="row align-items-center g-3">

            <!-- FARM IDENTITY -->
            <div class="col-lg-7 col-md-8">

                <div class="d-flex align-items-center gap-3">

                    <!-- FARM ICON -->
                    <div class="d-flex align-items-center justify-content-center
                                bg-success bg-opacity-10 text-success rounded-circle"
                         style="width:52px;height:52px;">

                        <i class="bi bi-water fs-4"></i>

                    </div>

                    <!-- FARM INFORMATION -->
                    <div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">

                            <h3 class="mb-0 fw-bold">
                                <?= htmlspecialchars($farm_name) ?>
                            </h3>

                            <span class="badge bg-success">
                                Active Farm
                            </span>

                        </div>

                        <div class="text-muted small mt-1">

                            <span>
                                <i class="bi bi-geo-alt me-1"></i>
                                <?= htmlspecialchars($farm_location) ?>
                            </span>

                            <span class="mx-2">•</span>

                            <span>
                                <?= htmlspecialchars($farm_size) ?> Farm
                            </span>

                        </div>

                    </div>

                </div>

            </div>


            <!-- DASHBOARD CONTEXT -->
            <div class="col-lg-5 col-md-4">

                <div class="d-flex justify-content-md-end align-items-center
                            gap-3 flex-wrap">

                    <!-- DATE / TIME -->
                    <div class="text-md-end">

                        <div class="fw-semibold" id="dashboardDate">
                            <?= date('l, d F Y') ?>
                        </div>

                        <div class="text-muted small" id="dashboardTime">
                            <?= date('h:i A') ?>
                        </div>

                    </div>


                    <!-- FARM SWITCHER -->
                    <div style="min-width:190px;">

                        <label for="farmSwitcher"
                               class="form-label small text-muted mb-1">
                            Current Farm
                        </label>

                        <select id="farmSwitcher"
                                class="form-select form-select-sm shadow-sm">

                        </select>

                    </div>

                </div>

            </div>

        </div>

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

<!-- =========================================================
     KPI GRID — YOTRIBE EXECUTIVE METRICS
     ========================================================= -->

<div class="row g-3 mb-4">

    <!-- LIVE FISH POPULATION -->
    <div class="<?= $can_view_financials ? 'col-md-3' : 'col-md-6' ?>">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>
                        <small class="text-muted">
                            Live Fish Population
                        </small>

                        <h4 class="fw-bold mb-0">
                            <?= number_format($total_fish_population) ?>
                        </h4>

                        <small class="text-success">
                            Currently in ponds
                        </small>
                    </div>

                    <div class="text-success">
                        <i class="bi bi-fish fs-3"></i>
                    </div>

                </div>

            </div>
        </div>
    </div>


    <!-- ACTIVE BATCHES -->
    <div class="<?= $can_view_financials ? 'col-md-3' : 'col-md-6' ?>">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>
                        <small class="text-muted">
                            Active Batches
                        </small>

                        <h4 class="fw-bold mb-0">
                            <?= number_format($active_batches) ?>
                        </h4>

                        <small class="text-primary">
                            Currently growing
                        </small>
                    </div>

                    <div class="text-primary">
                        <i class="bi bi-box-seam fs-3"></i>
                    </div>

                </div>

            </div>
        </div>
    </div>


    <!-- HARVESTED BATCHES -->
    <div class="<?= $can_view_financials ? 'col-md-3' : 'col-md-6' ?>">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>
                        <small class="text-muted">
                            Harvested Batches
                        </small>

                        <h4 class="fw-bold mb-0">
                            <?= number_format($harvested_batches) ?>
                        </h4>

                        <small class="text-warning">
                            Batches with harvest records
                        </small>
                    </div>

                    <div class="text-warning">
                        <i class="bi bi-basket2 fs-3"></i>
                    </div>

                </div>

            </div>
        </div>
    </div>


    <!-- HARVESTED FISH -->
    <div class="<?= $can_view_financials ? 'col-md-3' : 'col-md-6' ?>">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>
                        <small class="text-muted">
                            Harvested Fish
                        </small>

                        <h4 class="fw-bold mb-0">
                            <?= number_format($total_harvested_fish) ?>
                        </h4>

                        <small class="text-muted">
                            Total fish harvested
                        </small>
                    </div>

                    <div class="text-info">
                        <i class="bi bi-box-arrow-down fs-3"></i>
                    </div>

                </div>

            </div>
        </div>
    </div>


    <!-- HARVESTED WEIGHT -->
    <div class="<?= $can_view_financials ? 'col-md-3' : 'col-md-6' ?>">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>
                        <small class="text-muted">
                            Harvested Weight
                        </small>

                        <h4 class="fw-bold mb-0">
                            <?= number_format($total_harvested_weight, 2) ?> kg
                        </h4>

                        <small class="text-muted">
                            Total harvested biomass
                        </small>
                    </div>

                    <div class="text-success">
                        <i class="bi bi-speedometer2 fs-3"></i>
                    </div>

                </div>

            </div>
        </div>
    </div>


    <!-- BIOMASS -->
    <div class="<?= $can_view_financials ? 'col-md-3' : 'col-md-6' ?>">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>
                        <small class="text-muted">
                            Current Biomass
                        </small>

                        <h4 class="fw-bold mb-0">
                            <?= number_format($total_biomass, 2) ?> kg
                        </h4>

                        <small class="text-muted">
                            Estimated live biomass
                        </small>
                    </div>

                    <div class="text-primary">
                        <i class="bi bi-bar-chart-line fs-3"></i>
                    </div>

                </div>

            </div>
        </div>
    </div>


    <!-- FEED STOCK -->
    <div class="<?= $can_view_financials ? 'col-md-3' : 'col-md-6' ?>">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-start">

                    <div>
                        <small class="text-muted">
                            Feed Stock
                        </small>

                        <h4 class="fw-bold mb-0">
                            <?= number_format($total_feed, 2) ?> kg
                        </h4>

                        <small class="text-muted">
                            Current feed inventory
                        </small>
                    </div>

                    <div class="text-warning">
                        <i class="bi bi-boxes fs-3"></i>
                    </div>

                </div>

            </div>
        </div>
    </div>


    <?php if ($can_view_financials): ?>

        <!-- REVENUE -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Revenue
                            </small>

                            <h4 class="fw-bold text-primary mb-0">
                                ₦<?= number_format($total_sales, 2) ?>
                            </h4>

                            <small class="text-muted">
                                Total sales
                            </small>
                        </div>

                        <div class="text-primary">
                            <i class="bi bi-cash-stack fs-3"></i>
                        </div>

                    </div>

                </div>
            </div>
        </div>


        <!-- NET PROFIT -->
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Net Profit
                            </small>

                            <h4 class="fw-bold <?= $profit >= 0 ? 'text-success' : 'text-danger' ?> mb-0">
                                ₦<?= number_format($profit, 2) ?>
                            </h4>

                            <small class="text-muted">
                                Revenue less expenses
                            </small>
                        </div>

                        <div class="<?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                            <i class="bi bi-graph-up-arrow fs-3"></i>
                        </div>

                    </div>

                </div>
            </div>
        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     POND OPERATIONS SNAPSHOT
     ========================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <div>
            <strong>Pond Operations</strong>
            <div class="text-muted small">
                Current pond utilization for this farm
            </div>
        </div>

        <span class="badge bg-light text-dark border">
            <?= number_format($total_ponds) ?> Total Ponds
        </span>

    </div>

    <div class="card-body">

        <div class="row g-3">

            <!-- TOTAL PONDS -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Total Ponds
                            </small>

                            <h4 class="fw-bold mb-1">
                                <?= number_format($total_ponds) ?>
                            </h4>

                            <small class="text-muted">
                                Registered ponds
                            </small>
                        </div>

                        <i class="bi bi-grid-3x3-gap fs-3 text-primary"></i>

                    </div>

                </div>
            </div>


            <!-- ACTIVE PONDS -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Active Ponds
                            </small>

                            <h4 class="fw-bold mb-1 text-success">
                                <?= number_format($active_ponds) ?>
                            </h4>

                            <small class="text-muted">
                                Operational ponds
                            </small>
                        </div>

                        <i class="bi bi-check-circle fs-3 text-success"></i>

                    </div>

                </div>
            </div>


            <!-- OCCUPIED PONDS -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Occupied Ponds
                            </small>

                            <h4 class="fw-bold mb-1 text-info">
                                <?= number_format($occupied_ponds) ?>
                            </h4>

                            <small class="text-muted">
                                Currently stocked
                            </small>
                        </div>

                        <i class="bi bi-water fs-3 text-info"></i>

                    </div>

                </div>
            </div>


            <!-- EMPTY PONDS -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Empty Active Ponds
                            </small>

                            <h4 class="fw-bold mb-1 text-warning">
                                <?= number_format($empty_active_ponds) ?>
                            </h4>

                            <small class="text-muted">
                                Available for stocking
                            </small>
                        </div>

                        <i class="bi bi-house fs-3 text-warning"></i>

                    </div>

                </div>
            </div>

        </div>

    </div>

</div>

<!-- =========================================================
     HARVEST INVENTORY SNAPSHOT
     ========================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <div>
            <strong>Harvest Inventory</strong>
            <div class="text-muted small">
                Harvested fish currently tracked in inventory
            </div>
        </div>

        <span class="badge bg-light text-dark border">
            <?= number_format($harvested_batches) ?> Harvested Batches
        </span>

    </div>

    <div class="card-body">

        <div class="row g-3">

            <!-- HARVESTED FISH -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Harvested Fish
                            </small>

                            <h4 class="fw-bold mb-1">
                                <?= number_format($total_harvested_fish) ?>
                            </h4>

                            <small class="text-muted">
                                Total harvested quantity
                            </small>
                        </div>

                        <i class="bi bi-fish fs-3 text-primary"></i>

                    </div>

                </div>
            </div>


            <!-- HARVESTED WEIGHT -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Harvested Weight
                            </small>

                            <h4 class="fw-bold mb-1">
                                <?= number_format($total_harvested_weight, 2) ?>
                                <small class="fs-6">kg</small>
                            </h4>

                            <small class="text-muted">
                                Total harvested biomass
                            </small>
                        </div>

                        <i class="bi bi-box-seam fs-3 text-success"></i>

                    </div>

                </div>
            </div>


            <!-- AVAILABLE FISH -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Available Fish
                            </small>

                            <h4 class="fw-bold mb-1 text-info">
                                <?= number_format($available_harvest_fish) ?>
                            </h4>

                            <small class="text-muted">
                                Available for sale
                            </small>
                        </div>

                        <i class="bi bi-basket fs-3 text-info"></i>

                    </div>

                </div>
            </div>


            <!-- AVAILABLE WEIGHT -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Available Weight
                            </small>

                            <h4 class="fw-bold mb-1 text-warning">
                                <?= number_format($available_harvest_weight, 2) ?>
                                <small class="fs-6">kg</small>
                            </h4>

                            <small class="text-muted">
                                Harvest stock available for sale
                            </small>
                        </div>

                        <i class="bi bi-boxes fs-3 text-warning"></i>

                    </div>

                </div>
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
