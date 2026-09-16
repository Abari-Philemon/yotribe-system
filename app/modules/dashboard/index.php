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
/**
 * PROFIT MARGIN
 */

$profit_margin = 0;

if ($total_sales > 0) {
    $profit_margin = ($profit / $total_sales) * 100;
}
/**
 * SALES SNAPSHOT
 */

$sales_count = 0;
$sales_fish = 0;
$sales_weight_kg = 0;
$average_sale_value = 0;

if ($can_view_financials) {

    // Number of sales transactions
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sales
        WHERE farm_id = ?
    ");
    $stmt->execute([$farm_id]);
    $sales_count = (int)$stmt->fetchColumn();


    // Fish and weight sold
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(si.quantity_fish), 0) AS fish_sold,
            COALESCE(SUM(si.quantity_kg), 0) AS weight_sold
        FROM sale_items si
        INNER JOIN sales s
            ON s.id = si.sale_id
        WHERE s.farm_id = ?
    ");
    $stmt->execute([$farm_id]);

    $sales_snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

    $sales_fish = (int)($sales_snapshot['fish_sold'] ?? 0);
    $sales_weight_kg = (float)($sales_snapshot['weight_sold'] ?? 0);


    // Average sale transaction value
    if ($sales_count > 0) {
        $average_sale_value = $total_sales / $sales_count;
    }
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
 *
 * Uses growth_logs, which is the authoritative growth
 * recording table used by growth_helper.php.
 */
$stmt = $pdo->prepare("
    SELECT DISTINCT
        gl.pond_id,
        gl.batch_id,

        (
            SELECT gl_start.avg_weight_g
            FROM growth_logs gl_start
            WHERE gl_start.farm_id = gl.farm_id
              AND gl_start.pond_id = gl.pond_id
              AND gl_start.batch_id = gl.batch_id
            ORDER BY gl_start.recorded_at ASC, gl_start.id ASC
            LIMIT 1
        ) AS start_w,

        (
            SELECT gl_start.recorded_at
            FROM growth_logs gl_start
            WHERE gl_start.farm_id = gl.farm_id
              AND gl_start.pond_id = gl.pond_id
              AND gl_start.batch_id = gl.batch_id
            ORDER BY gl_start.recorded_at ASC, gl_start.id ASC
            LIMIT 1
        ) AS start_at,

        (
            SELECT gl_end.avg_weight_g
            FROM growth_logs gl_end
            WHERE gl_end.farm_id = gl.farm_id
              AND gl_end.pond_id = gl.pond_id
              AND gl_end.batch_id = gl.batch_id
            ORDER BY gl_end.recorded_at DESC, gl_end.id DESC
            LIMIT 1
        ) AS end_w,

        (
            SELECT gl_end.recorded_at
            FROM growth_logs gl_end
            WHERE gl_end.farm_id = gl.farm_id
              AND gl_end.pond_id = gl.pond_id
              AND gl_end.batch_id = gl.batch_id
            ORDER BY gl_end.recorded_at DESC, gl_end.id DESC
            LIMIT 1
        ) AS end_at

    FROM growth_logs gl
    WHERE gl.farm_id = ?
");
$stmt->execute([$farm_id]);

$growth_map = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
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
        $attention[$s['pond_code']] = [
            'title'    => 'Growth Alert',
            'message'  => "{$s['pond_code']}: {$alert}",
            'action'   => 'Review growth performance and recent growth measurements.',
            'severity' => 'high'
        ];
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
            $attention[$s['pond_code'].'_feed'] = [
                'title'    => 'Feeding Alert',
                'message'  => "{$s['pond_code']}: Overfeeding detected",
                'action'   => 'Review today’s feeding quantity and adjust the next feeding.',
                'severity' => 'attention'
            ];
        }
    }
    /**
     * TRUE FCR (SCIENTIFIC)
     */
    if (isset($growth_map[$key]) && isset($feed_total_map[$pond_id])) {

        $g = $growth_map[$key];

        if ($g['end_w'] > $g['start_w']) {

            $weight_gain = $g['end_w'] - $g['start_w'];

            $biomass_gain = (
                $weight_gain * $s['current_count']
            ) / 1000;

            if ($biomass_gain > 0) {

                $feed_used = $feed_total_map[$pond_id];

                $fcr = $feed_used / $biomass_gain;

                /**
                 * FCR CLASSIFICATION
                 */
                if ($fcr <= 1.8) {

                    $fcr_efficiency = 'EXCELLENT';
                    $fcr_alert = '';

                } elseif ($fcr <= 2.0) {

                    $fcr_efficiency = 'GOOD';
                    $fcr_alert = '';

                } else {

                    $fcr_efficiency = 'POOR';
                    $fcr_alert = 'Poor FCR';
                }

                $fcr_data[] = [

                    'pond' => $s['pond_code'],

                    'start_weight_g' =>
                        (float)$g['start_w'],

                    'end_weight_g' =>
                        (float)$g['end_w'],

                    'weight_gain_g' =>
                        (float)$weight_gain,

                    'feed_used_kg' =>
                        (float)$feed_used,

                    'biomass_gain_kg' =>
                        (float)$biomass_gain,

                    'fcr' =>
                        (float)$fcr,

                    'efficiency' =>
                        $fcr_efficiency,

                    'alert' =>
                        $fcr_alert
                ];

                /**
                 * MANAGEMENT ATTENTION
                 */
                if ($fcr_alert !== '') {

                    $attention[
                        $s['pond_code'].'_fcr'
                    ] = [
                        'title'    => 'FCR Alert',
                        'message'  => "{$s['pond_code']}: {$fcr_alert}",
                        'action'   => 'Review feed usage and investigate the cause of poor feed conversion.',
                        'severity' => 'high'
                    ];
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
        $attention[$m['pond_code'].'_mort'] = [
            'title'    => 'Mortality Spike',
            'message'  => "{$m['pond_code']}: Mortality spike ({$m['deaths']})",
            'action'   => 'Inspect the pond immediately and investigate the mortality cause.',
            'severity' => 'high'
        ];
    }
}

/**
 * FARM HEALTH SUMMARY
 *
 * Converts existing operational analysis into
 * simple dashboard-level health indicators.
 */

$growth_health = 'Healthy';
$feeding_health = 'Healthy';
$fcr_health = 'Healthy';
$mortality_health = 'Healthy';


// ---------------------------------------------------------
// GROWTH HEALTH
// ---------------------------------------------------------

$growth_attention_count = 0;

foreach ($growth_data as $growth) {

    if (
        isset($growth['alert']) &&
        !empty($growth['alert'])
    ) {
        $growth_attention_count++;
    }
}

if ($growth_attention_count > 0) {
    $growth_health = 'Attention';
}


// ---------------------------------------------------------
// FEEDING HEALTH
// ---------------------------------------------------------

$feeding_attention_count = 0;

foreach ($feeding_data as $feeding) {

    if (
        isset($feeding['alert']) &&
        !empty($feeding['alert'])
    ) {
        $feeding_attention_count++;
    }
}

if ($feeding_attention_count > 0) {
    $feeding_health = 'Attention';
}


// ---------------------------------------------------------
// FCR HEALTH
// ---------------------------------------------------------

$fcr_attention_count = 0;

foreach ($fcr_data as $fcr) {

    if (
        isset($fcr['alert']) &&
        !empty($fcr['alert'])
    ) {
        $fcr_attention_count++;
    }
}

if ($fcr_attention_count > 0) {

    $fcr_health = 'Attention';

} else {

    $fcr_health = 'Healthy';
}


// ---------------------------------------------------------
// MORTALITY HEALTH
// ---------------------------------------------------------

if ($high_mortality > 0) {
    $mortality_health = 'Attention';
}

/**
 * FINAL CLEAN ARRAY
 */
$attention = array_values($attention);

/**
 * YOTRIBE INTELLIGENCE SUMMARY
 */

$intelligence_attention_count = count($attention);

$intelligence_critical_count = 0;
$intelligence_normal_count = 0;

foreach ($attention as $item) {

    $severity = strtolower(
        trim($item['severity'] ?? '')
    );

    if (
        in_array(
            $severity,
            ['critical', 'high'],
            true
        )
    ) {
        $intelligence_critical_count++;
    } else {
        $intelligence_normal_count++;
    }
}


if ($intelligence_critical_count > 0) {

    $intelligence_status = 'Critical';

} elseif ($intelligence_attention_count > 0) {

    $intelligence_status = 'Attention';

} else {

    $intelligence_status = 'Healthy';
}

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
    
    'sales_count'          => $sales_count,
    'sales_fish'           => $sales_fish,
    'sales_weight_kg'      => $sales_weight_kg,
    'average_sale_value'   => $average_sale_value,
    'profit_margin'        => $profit_margin,

    'alerts'         => $alerts,
    'growth_data'    => $growth_data,
    'feeding_data'   => $feeding_data,
    'fcr_data'       => $fcr_data,
    'attention'      => $attention,

    'growth_health'  => $growth_health,
    'feeding_health' => $feeding_health,
    'fcr_health'     => $fcr_health,
    'mortality_health' => $mortality_health,

    'intelligence_attention_count' => $intelligence_attention_count,
    'intelligence_critical_count'  => $intelligence_critical_count,
    'intelligence_normal_count'    => $intelligence_normal_count,
    'intelligence_status'          => $intelligence_status,
];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
    /* =========================================================
    004-E-1 — DASHBOARD RESPONSIVE UI
    Presentation layer only
    ========================================================= */

    .card-header {
        min-width: 0;
    }

    .card-header > div {
        min-width: 0;
    }

    .card-header .badge {
        flex-shrink: 0;
    }

    .table-responsive {
        -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 767.98px) {

        .card-header.d-flex {
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .card-header.d-flex > div:first-child {
            flex: 1 1 100%;
        }

        .card-header.d-flex > .badge {
            margin-left: 0;
        }

        .card-body {
            overflow-wrap: break-word;
        }

        .table-responsive {
            font-size: 0.9rem;
        }

    }

    @media (max-width: 575.98px) {

        .card-header {
            padding: 0.85rem 1rem;
        }

        .card-body {
            padding: 1rem;
        }

    }
    /* =========================================================
   004-E-2 — KPI CARD VISUAL CONSISTENCY
   Presentation layer only
   ========================================================= */

    .row.g-3.mb-4 > [class*="col-"] > .card {
        min-height: 132px;
        transition:
            transform 0.18s ease,
            box-shadow 0.18s ease;
    }

    .row.g-3.mb-4 > [class*="col-"] > .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.08) !important;
    }

    .row.g-3.mb-4 > [class*="col-"] > .card .card-body {
        padding: 1.1rem 1.15rem;
    }

    .row.g-3.mb-4 > [class*="col-"] > .card h4 {
        font-size: 1.35rem;
        line-height: 1.25;
        margin-top: 0.2rem;
    }

    .row.g-3.mb-4 > [class*="col-"] > .card small {
        line-height: 1.35;
    }

    .row.g-3.mb-4 > [class*="col-"] > .card .bi {
        line-height: 1;
    }

    @media (max-width: 575.98px) {

        .row.g-3.mb-4 > [class*="col-"] > .card {
            min-height: 118px;
        }

        .row.g-3.mb-4 > [class*="col-"] > .card h4 {
            font-size: 1.2rem;
        }

    }
    /* =========================================================
   004-E-3 — SNAPSHOT METRIC CARD CONSISTENCY
   Presentation layer only
   ========================================================= */

    .card .row.g-3 > [class*="col-"] > .border.rounded {
        background: #fff;
        transition:
            border-color 0.18s ease,
            box-shadow 0.18s ease,
            transform 0.18s ease;
    }

    .card .row.g-3 > [class*="col-"] > .border.rounded:hover {
        border-color: rgba(13, 110, 253, 0.25) !important;
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.06);
        transform: translateY(-1px);
    }

    .card .row.g-3 > [class*="col-"] > .border.rounded h4 {
        line-height: 1.25;
    }

    .card .row.g-3 > [class*="col-"] > .border.rounded .text-muted {
        line-height: 1.35;
    }

    @media (max-width: 767.98px) {

        .card .row.g-3 > [class*="col-"] > .border.rounded {
            padding: 1rem !important;
        }

    }

    @media (max-width: 575.98px) {

        .card .row.g-3 > [class*="col-"] > .border.rounded {
            min-height: 105px;
        }

    }
    /* =========================================================
   004-E-4 — FARM HEALTH & INTELLIGENCE REFINEMENT
   Presentation layer only
   ========================================================= */

    /* Farm Health tiles */
    .card .border.rounded.p-3.h-100 {
        border-color: #e9ecef !important;
        background: #fff;
    }

    .card .border.rounded.p-3.h-100 h5 {
        line-height: 1.25;
    }

    .card .border.rounded.p-3.h-100 .bi {
        opacity: 0.85;
    }

    /* Intelligence summary tiles */
    .card .row.g-3.mb-4 > [class*="col-"] > .border.rounded.p-3.h-100 {
        background: #fdfdfd;
    }

    .card .row.g-3.mb-4 > [class*="col-"] > .border.rounded.p-3.h-100 h4 {
        font-size: 1.3rem;
    }

    /* Intelligence attention list */
    .list-group-item {
        border-left: 0;
        border-right: 0;
    }

    .list-group-item:first-child {
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }

    .list-group-item:last-child {
        border-bottom-left-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
    }

    .list-group-item .badge {
        white-space: nowrap;
    }

    @media (max-width: 767.98px) {

        .list-group-item .d-flex {
            flex-wrap: wrap;
        }

        .list-group-item .flex-grow-1 {
            min-width: 0;
            width: 100%;
        }

        .list-group-item .flex-grow-1 > .d-flex {
            gap: 0.5rem;
        }

    }

    @media (max-width: 575.98px) {

        .list-group-item {
            padding: 0.85rem;
        }

        .list-group-item .me-3 {
            margin-right: 0.65rem !important;
        }

        .list-group-item .badge {
            font-size: 0.7rem;
        }

    }
    /* =========================================================
   004-E-5 — ANALYTICS & CHARTS RESPONSIVE REFINEMENT
   Presentation layer only
   ========================================================= */

    /* Analytics tabs */
    #analyticsTabs {
        gap: 0.4rem;
        flex-wrap: wrap;
    }

    #analyticsTabs .nav-link {
        white-space: nowrap;
        transition:
            background-color 0.18s ease,
            color 0.18s ease,
            box-shadow 0.18s ease;
    }

    #analyticsTabs .nav-link:hover {
        box-shadow: 0 0.2rem 0.55rem rgba(0, 0, 0, 0.06);
    }

    /* Analytics tables */
    .tab-pane .table {
        margin-bottom: 0;
    }

    .tab-pane .table th,
    .tab-pane .table td {
        vertical-align: middle;
    }

    .tab-pane .table-responsive {
        border-radius: 0 0 0.5rem 0.5rem;
    }

    /* Chart cards */
    .row.g-3.mt-4 > [class*="col-"] > .card {
        min-width: 0;
    }

    .row.g-3.mt-4 > [class*="col-"] > .card .card-body {
        min-width: 0;
    }

    /* Existing chart containers */
    .row.g-3.mt-4 canvas {
        max-width: 100%;
    }

    /* Tablet */
    @media (max-width: 767.98px) {

        #analyticsTabs {
            width: 100%;
            margin-bottom: 1rem !important;
        }

        #analyticsTabs .nav-item {
            flex: 1 1 auto;
        }

        #analyticsTabs .nav-link {
            width: 100%;
            text-align: center;
        }

        .tab-pane .card-header {
            gap: 0.75rem;
        }

        .tab-pane .table-responsive {
            overflow-x: auto;
        }

        .row.g-3.mt-4 > [class*="col-"] {
            width: 100%;
        }

    }

    /* Mobile */
    @media (max-width: 575.98px) {

        #analyticsTabs {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
        }

        #analyticsTabs .nav-item,
        #analyticsTabs .nav-link {
            width: 100%;
        }

        .tab-pane .card-header {
            padding: 0.85rem 1rem;
        }

        .tab-pane .card-body {
            padding: 1rem;
        }

        .tab-pane .table {
            font-size: 0.85rem;
        }

        .row.g-3.mt-4 .card-header {
            padding: 0.85rem 1rem;
        }

        .row.g-3.mt-4 .card-body {
            padding: 1rem;
        }

        /* Give charts more usable space on small screens */
        .row.g-3.mt-4 [style*="height:320px"] {
            height: 280px !important;
        }

    }
</style>
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


<!-- =========================================================
     004-C — FARM NOTIFICATIONS
     ========================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <?php
    $notification_count = count($attention);
    $notification_critical_count = 0;
    $notification_attention_count = 0;

    foreach ($attention as $notification) {

        $severity = strtolower(
            trim($notification['severity'] ?? '')
        );

        if (
            in_array(
                $severity,
                ['critical', 'high'],
                true
            )
        ) {
            $notification_critical_count++;
        } else {
            $notification_attention_count++;
        }
    }
    ?>

    <div class="card-header bg-white
                d-flex justify-content-between
                align-items-center">

        <div>
            <strong>
                <i class="bi bi-bell me-1"></i>
                Notifications
            </strong>

            <div class="text-muted small">
                Important operational conditions for the selected farm
            </div>
        </div>

        <?php if ($notification_count > 0): ?>

            <span class="badge
                <?= $notification_critical_count > 0
                    ? 'bg-danger'
                    : 'bg-warning text-dark' ?>">

                <?= number_format($notification_count) ?>
                <?= $notification_count === 1
                    ? 'Notification'
                    : 'Notifications' ?>

            </span>

        <?php else: ?>

            <span class="badge bg-success">
                All Clear
            </span>

        <?php endif; ?>

    </div>


    <div class="card-body p-0">

        <?php if (empty($attention)): ?>

            <div class="text-center text-muted py-5">

                <div class="mb-2">
                    <i class="bi bi-check-circle text-success fs-1"></i>
                </div>

                <div class="fw-semibold text-dark">
                    No notifications
                </div>

                <small>
                    No operational conditions currently require management attention.
                </small>

            </div>

        <?php else: ?>

            <div class="list-group list-group-flush">

                <?php foreach ($attention as $notification): ?>

                    <?php
                    $severity = strtolower(
                        trim($notification['severity'] ?? '')
                    );

                    if (
                        in_array(
                            $severity,
                            ['critical', 'high'],
                            true
                        )
                    ) {
                        $icon = 'bi-exclamation-triangle-fill';
                        $icon_class = 'text-danger';
                        $badge_class = 'bg-danger';
                        $badge_text = 'Critical';
                    } else {
                        $icon = 'bi-exclamation-circle-fill';
                        $icon_class = 'text-warning';
                        $badge_class = 'bg-warning text-dark';
                        $badge_text = 'Attention';
                    }
                    ?>

                    <div class="list-group-item px-4 py-3">

                        <div class="d-flex
                                    align-items-start
                                    justify-content-between
                                    gap-3">

                            <div class="d-flex
                                        align-items-start
                                        gap-3">

                                <div class="<?= $icon_class; ?> pt-1">
                                    <i class="bi <?= $icon; ?> fs-5"></i>
                                </div>

                                <div>

                                    <div class="fw-semibold">
                                        <?= htmlspecialchars(
                                            $notification['title'] ?? 'Notification',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </div>

                                    <div class="text-muted small mt-1">
                                        <?= htmlspecialchars(
                                            $notification['message'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                    </div>

                                    <?php if (!empty($notification['action'])): ?>

                                        <div class="small mt-2">
                                            <strong>Recommended action:</strong>
                                            <?= htmlspecialchars(
                                                $notification['action'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                            <span class="badge <?= $badge_class; ?> flex-shrink-0">
                                <?= $badge_text; ?>
                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

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

<!-- =========================================================
     SALES SNAPSHOT
     ========================================================= -->

<?php if ($can_view_financials): ?>

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <div>
            <strong>Sales Snapshot</strong>
            <div class="text-muted small">
                Current sales activity for this farm
            </div>
        </div>

        <span class="badge bg-light text-dark border">
            <?= number_format($sales_count) ?> Transactions
        </span>

    </div>

    <div class="card-body">

        <div class="row g-3">

            <!-- SALES VALUE -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Sales Revenue
                            </small>

                            <h4 class="fw-bold mb-1">
                                ₦<?= number_format($total_sales, 2) ?>
                            </h4>

                            <small class="text-muted">
                                Total recorded sales
                            </small>
                        </div>

                        <i class="bi bi-cash-stack fs-3 text-success"></i>

                    </div>

                </div>
            </div>


            <!-- FISH SOLD -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Fish Sold
                            </small>

                            <h4 class="fw-bold mb-1">
                                <?= number_format($sales_fish) ?>
                            </h4>

                            <small class="text-muted">
                                Total fish sold
                            </small>
                        </div>

                        <i class="bi bi-fish fs-3 text-primary"></i>

                    </div>

                </div>
            </div>


            <!-- WEIGHT SOLD -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Weight Sold
                            </small>

                            <h4 class="fw-bold mb-1">
                                <?= number_format($sales_weight_kg, 2) ?>
                                <small class="fs-6">kg</small>
                            </h4>

                            <small class="text-muted">
                                Total fish weight sold
                            </small>
                        </div>

                        <i class="bi bi-box-arrow-up fs-3 text-info"></i>

                    </div>

                </div>
            </div>


            <!-- AVERAGE SALE -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Average Sale
                            </small>

                            <h4 class="fw-bold mb-1 text-warning">
                                ₦<?= number_format($average_sale_value, 2) ?>
                            </h4>

                            <small class="text-muted">
                                Average transaction value
                            </small>
                        </div>

                        <i class="bi bi-receipt fs-3 text-warning"></i>

                    </div>

                </div>
            </div>

        </div>

    </div>

</div>

<?php endif; ?>

<!-- =========================================================
     FINANCIAL SNAPSHOT
     ========================================================= -->

<?php if ($can_view_financials): ?>

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <div>
            <strong>Financial Snapshot</strong>
            <div class="text-muted small">
                Financial performance for this farm
            </div>
        </div>

        <span class="badge bg-light text-dark border">
            <?= number_format($profit_margin, 1) ?>% Margin
        </span>

    </div>

    <div class="card-body">

        <div class="row g-3">

            <!-- TOTAL REVENUE -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Total Revenue
                            </small>

                            <h4 class="fw-bold mb-1 text-success">
                                ₦<?= number_format($total_sales, 2) ?>
                            </h4>

                            <small class="text-muted">
                                Recorded sales revenue
                            </small>
                        </div>

                        <i class="bi bi-graph-up-arrow fs-3 text-success"></i>

                    </div>

                </div>
            </div>


            <!-- TOTAL EXPENSES -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Total Expenses
                            </small>

                            <h4 class="fw-bold mb-1 text-danger">
                                ₦<?= number_format($total_expenses, 2) ?>
                            </h4>

                            <small class="text-muted">
                                Recorded farm expenses
                            </small>
                        </div>

                        <i class="bi bi-arrow-down-circle fs-3 text-danger"></i>

                    </div>

                </div>
            </div>


            <!-- NET PROFIT -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Net Profit
                            </small>

                            <h4 class="fw-bold mb-1 <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                ₦<?= number_format($profit, 2) ?>
                            </h4>

                            <small class="text-muted">
                                Revenue minus expenses
                            </small>
                        </div>

                        <i class="bi bi-wallet2 fs-3 <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>"></i>

                    </div>

                </div>
            </div>


            <!-- PROFIT MARGIN -->
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>
                            <small class="text-muted">
                                Profit Margin
                            </small>

                            <h4 class="fw-bold mb-1 <?= $profit_margin >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($profit_margin, 1) ?>%
                            </h4>

                            <small class="text-muted">
                                Net profit / revenue
                            </small>
                        </div>

                        <i class="bi bi-percent fs-3 <?= $profit_margin >= 0 ? 'text-success' : 'text-danger' ?>"></i>

                    </div>

                </div>
            </div>

        </div>

    </div>

</div>

<?php endif; ?>

<!-- =========================================================
     FARM HEALTH SUMMARY
     ========================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <div>
            <strong>Farm Health</strong>
            <div class="text-muted small">
                Current operational health indicators for this farm
            </div>
        </div>

        <?php
        $health_statuses = [
            $growth_health,
            $feeding_health,
            $fcr_health,
            $mortality_health
        ];

        $health_attention_count = count(
            array_filter(
                $health_statuses,
                fn($status) => $status === 'Attention'
            )
        );
        ?>

        <?php if ($health_attention_count > 0): ?>

            <span class="badge bg-warning text-dark">
                <?= number_format($health_attention_count) ?> Attention
            </span>

        <?php else: ?>

            <span class="badge bg-success">
                Healthy
            </span>

        <?php endif; ?>

    </div>


    <div class="card-body">

        <div class="row g-3">


            <!-- =================================================
                 GROWTH HEALTH
                 ================================================= -->

            <div class="col-md-3">

                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>

                            <small class="text-muted">
                                Growth Health
                            </small>

                            <h5 class="fw-bold mb-1
                                <?= $growth_health === 'Healthy'
                                    ? 'text-success'
                                    : 'text-warning' ?>">

                                <?= htmlspecialchars($growth_health) ?>

                            </h5>

                            <small class="text-muted">
                                Fish growth performance
                            </small>

                        </div>

                        <i class="bi bi-graph-up-arrow fs-3
                            <?= $growth_health === 'Healthy'
                                ? 'text-success'
                                : 'text-warning' ?>">
                        </i>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 FEEDING HEALTH
                 ================================================= -->

            <div class="col-md-3">

                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>

                            <small class="text-muted">
                                Feeding Health
                            </small>

                            <h5 class="fw-bold mb-1
                                <?= $feeding_health === 'Healthy'
                                    ? 'text-success'
                                    : 'text-warning' ?>">

                                <?= htmlspecialchars($feeding_health) ?>

                            </h5>

                            <small class="text-muted">
                                Feeding performance
                            </small>

                        </div>

                        <i class="bi bi-egg-fried fs-3
                            <?= $feeding_health === 'Healthy'
                                ? 'text-success'
                                : 'text-warning' ?>">
                        </i>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 FCR HEALTH
                 ================================================= -->

            <div class="col-md-3">

                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>

                            <small class="text-muted">
                                FCR Health
                            </small>

                            <h5 class="fw-bold mb-1
                                <?= $fcr_health === 'Healthy'
                                    ? 'text-success'
                                    : 'text-warning' ?>">

                                <?= htmlspecialchars($fcr_health) ?>

                            </h5>

                            <small class="text-muted">
                                Feed conversion performance
                            </small>

                        </div>

                        <i class="bi bi-speedometer2 fs-3
                            <?= $fcr_health === 'Healthy'
                                ? 'text-success'
                                : 'text-warning' ?>">
                        </i>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 MORTALITY HEALTH
                 ================================================= -->

            <div class="col-md-3">

                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-start">

                        <div>

                            <small class="text-muted">
                                Mortality Health
                            </small>

                            <h5 class="fw-bold mb-1
                                <?= $mortality_health === 'Healthy'
                                    ? 'text-success'
                                    : 'text-warning' ?>">

                                <?= htmlspecialchars($mortality_health) ?>

                            </h5>

                            <small class="text-muted">
                                Mortality activity
                            </small>

                        </div>

                        <i class="bi bi-heart-pulse fs-3
                            <?= $mortality_health === 'Healthy'
                                ? 'text-success'
                                : 'text-warning' ?>">
                        </i>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- =========================================================
     YOTRIBE INTELLIGENCE
     ========================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white d-flex justify-content-between align-items-center">

        <div>
            <strong>YOTRIBE Intelligence</strong>

            <div class="text-muted small">
                Operational conditions requiring management attention
            </div>
        </div>


        <?php if ($intelligence_status === 'Critical'): ?>

            <span class="badge bg-danger">
                Critical
            </span>

        <?php elseif ($intelligence_status === 'Attention'): ?>

            <span class="badge bg-warning text-dark">
                Attention Required
            </span>

        <?php else: ?>

            <span class="badge bg-success">
                Healthy
            </span>

        <?php endif; ?>

    </div>


    <div class="card-body">


        <!-- =====================================================
             INTELLIGENCE SUMMARY
             ===================================================== -->

        <div class="row g-3 mb-4">

            <!-- TOTAL ATTENTION -->
            <div class="col-md-4">

                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <small class="text-muted">
                                Attention Items
                            </small>

                            <h4 class="fw-bold mb-0">
                                <?= number_format($intelligence_attention_count) ?>
                            </h4>

                        </div>

                        <i class="bi bi-exclamation-circle fs-3 text-warning"></i>

                    </div>

                </div>

            </div>


            <!-- CRITICAL -->
            <div class="col-md-4">

                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <small class="text-muted">
                                Critical / High
                            </small>

                            <h4 class="fw-bold mb-0 text-danger">
                                <?= number_format($intelligence_critical_count) ?>
                            </h4>

                        </div>

                        <i class="bi bi-exclamation-triangle fs-3 text-danger"></i>

                    </div>

                </div>

            </div>


            <!-- NORMAL -->
            <div class="col-md-4">

                <div class="border rounded p-3 h-100">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <small class="text-muted">
                                Normal Attention
                            </small>

                            <h4 class="fw-bold mb-0 text-info">
                                <?= number_format($intelligence_normal_count) ?>
                            </h4>

                        </div>

                        <i class="bi bi-info-circle fs-3 text-info"></i>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             ATTENTION ITEMS
             ===================================================== -->

        <?php if (!empty($attention)): ?>

            <div class="mb-3">

                <h6 class="fw-bold mb-3">
                    Attention Required
                </h6>


                <div class="list-group">


                    <?php foreach ($attention as $item): ?>

                        <?php
                        $severity = strtolower(
                            trim($item['severity'] ?? '')
                        );

                        if (
                            in_array(
                                $severity,
                                ['critical', 'high'],
                                true
                            )
                        ) {

                            $severity_class = 'danger';
                            $severity_icon  = 'bi-exclamation-triangle';

                        } else {

                            $severity_class = 'warning';
                            $severity_icon  = 'bi-exclamation-circle';
                        }
                        ?>


                        <div class="list-group-item">

                            <div class="d-flex align-items-start">

                                <div class="me-3">

                                    <i class="bi <?= $severity_icon ?>
                                        text-<?= $severity_class ?>
                                        fs-4">
                                    </i>

                                </div>


                                <div class="flex-grow-1">

                                    <div class="d-flex justify-content-between align-items-start">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $item['title']
                                                ?? $item['message']
                                                ?? 'Operational Attention'
                                            ) ?>
                                        </strong>


                                        <span class="badge bg-<?= $severity_class ?>
                                            <?= $severity_class === 'warning'
                                                ? ' text-dark'
                                                : '' ?>">

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $severity !== ''
                                                        ? $severity
                                                        : 'attention'
                                                )
                                            ) ?>

                                        </span>

                                    </div>


                                    <?php if (!empty($item['message'])): ?>

                                        <div class="text-muted small mt-1">

                                            <?= htmlspecialchars(
                                                $item['message']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>


                                    <?php if (!empty($item['action'])): ?>

                                        <div class="small mt-2">

                                            <strong>
                                                Recommended Action:
                                            </strong>

                                            <?= htmlspecialchars(
                                                $item['action']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                    <?php endforeach; ?>

                </div>

            </div>


        <?php else: ?>


            <!-- =================================================
                 NO ATTENTION ITEMS
                 ================================================= -->

            <div class="text-center py-4">

                <i class="bi bi-check-circle text-success fs-1"></i>

                <h6 class="fw-bold mt-2">
                    No Immediate Attention Required
                </h6>

                <p class="text-muted small mb-0">
                    YOTRIBE has not detected any current operational
                    conditions requiring management attention.
                </p>

            </div>


        <?php endif; ?>

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

            <div class="card-header bg-white d-flex justify-content-between align-items-center">

                <div>
                    <strong>Feed Conversion Ratio Analytics</strong>

                    <div class="text-muted small">
                        Scientific feed-to-biomass conversion analysis
                    </div>
                </div>

                <span class="badge bg-light text-dark border">
                    <?= number_format(count($fcr_data)) ?> Ponds Analysed
                </span>

            </div>

            <div class="card-body table-responsive">

                <table class="table table-sm table-hover align-middle">

                    <thead>

                        <tr>
                            <th>Pond</th>
                            <th>Start Weight</th>
                            <th>End Weight</th>
                            <th>Weight Gain</th>
                            <th>Feed Used</th>
                            <th>Biomass Gain</th>
                            <th>FCR</th>
                            <th>Efficiency</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($fcr_data)): ?>

                            <tr>
                                <td colspan="8"
                                    class="text-center text-muted py-4">

                                    No sufficient growth and feeding data
                                    available for FCR calculation.

                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($fcr_data as $f): ?>

                                <tr>

                                    <td class="fw-bold">
                                        <?= htmlspecialchars($f['pond']) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $f['start_weight_g'],
                                            1
                                        ) ?> g
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $f['end_weight_g'],
                                            1
                                        ) ?> g
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $f['weight_gain_g'],
                                            1
                                        ) ?> g
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $f['feed_used_kg'],
                                            2
                                        ) ?> kg
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $f['biomass_gain_kg'],
                                            2
                                        ) ?> kg
                                    </td>

                                    <td class="fw-bold">
                                        <?= number_format(
                                            $f['fcr'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>

                                        <?php if ($f['efficiency'] === 'EXCELLENT'): ?>

                                            <span class="badge bg-success">
                                                EXCELLENT
                                            </span>

                                        <?php elseif ($f['efficiency'] === 'GOOD'): ?>

                                            <span class="badge bg-warning text-dark">
                                                GOOD
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-danger">
                                                POOR
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
    <!-- =========================================================
    ANALYTICS & CHARTS
    ========================================================= -->
<div class="row g-3 mt-4">

    <!-- BIOMASS ANALYSIS -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">

            <div class="card-header bg-white
                        d-flex justify-content-between align-items-center">

                <div>
                    <strong>Biomass Analysis</strong>

                    <div class="text-muted small">
                        Current biomass distribution by active pond
                    </div>
                </div>

                <span class="badge bg-light text-dark border">
                    <?= number_format($total_biomass, 2) ?> kg
                </span>

            </div>

            <div class="card-body">

                <?php if (!empty($feeding_data)): ?>

                    <div style="height:320px;">
                        <canvas id="biomassChart"></canvas>
                    </div>

                <?php else: ?>

                    <div class="text-center text-muted py-5">

                        <i class="bi bi-bar-chart fs-1 d-block mb-2"></i>

                        <div class="fw-semibold">
                            No biomass analysis available
                        </div>

                        <small>
                            Active pond biomass data will appear here.
                        </small>

                    </div>

                <?php endif; ?>

            </div>

        </div>
    </div>


    <!-- SALES PERFORMANCE -->
    <?php if ($can_view_financials): ?>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">

            <div class="card-header bg-white
                        d-flex justify-content-between align-items-center">

                <div>
                    <strong>Sales Performance</strong>

                    <div class="text-muted small">
                        Recorded sales performance for this farm
                    </div>
                </div>

                <span class="badge bg-light text-dark border">
                    <?= number_format($sales_count) ?> Transactions
                </span>

            </div>

            <div class="card-body">

                <?php if ($sales_count > 0): ?>

                    <div style="height:320px;">
                        <canvas id="salesChart"></canvas>
                    </div>

                <?php else: ?>

                    <div class="text-center text-muted py-5">

                        <i class="bi bi-graph-up fs-1 d-block mb-2"></i>

                        <div class="fw-semibold">
                            No sales data available
                        </div>

                        <small>
                            Sales performance will appear here after transactions are recorded.
                        </small>

                    </div>

                <?php endif; ?>

            </div>

        </div>
    </div>

    <?php endif; ?>

</div>

<!-- =========================================================
     RECENT FARM ACTIVITIES
     ========================================================= -->

<?php

/**
 * RECENT ACTIVITIES
 *
 * All activity queries are restricted to the
 * currently selected farm.
 */

$recent_activities = [];


/**
 * RECENT SALES
 */
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.created_at,
        s.total_amount
    FROM sales s
    WHERE s.farm_id = ?
    ORDER BY s.created_at DESC, s.id DESC
    LIMIT 5
");
$stmt->execute([$farm_id]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $recent_activities[] = [
        'type'  => 'sale',
        'icon'  => 'bi-cart-check',
        'title' => 'Sale Recorded',
        'description' =>
            'Sale transaction recorded',
        'value' =>
            '₦' . number_format(
                (float)$row['total_amount'],
                2
            ),
        'date' => $row['created_at']
    ];
}


/**
 * RECENT HARVESTS
 */
$stmt = $pdo->prepare("
    SELECT
        h.id,
        h.harvest_no,
        h.harvest_date,
        h.created_at
    FROM harvests h
    WHERE h.farm_id = ?
    ORDER BY h.created_at DESC, h.id DESC
    LIMIT 5
");
$stmt->execute([$farm_id]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $recent_activities[] = [
        'type'  => 'harvest',
        'icon'  => 'bi-basket',
        'title' => 'Harvest Recorded',
        'description' =>
            'Harvest ' . htmlspecialchars(
                $row['harvest_no']
            ),
        'value' => $row['harvest_date'],
        'date'  => $row['created_at']
    ];
}


/**
 * RECENT FEEDING
 */
$stmt = $pdo->prepare("
    SELECT
        fl.id,
        fl.pond_id,
        fl.quantity_kg,
        fl.date,
        p.pond_code
    FROM feeding_logs fl
    INNER JOIN ponds_tanks p
        ON p.id = fl.pond_id
    WHERE fl.farm_id = ?
    ORDER BY fl.date DESC, fl.id DESC
    LIMIT 5
");
$stmt->execute([$farm_id]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $recent_activities[] = [
        'type'  => 'feeding',
        'icon'  => 'bi-droplet',
        'title' => 'Feeding Recorded',
        'description' =>
            htmlspecialchars(
                $row['pond_code']
            ) . ' feeding',
        'value' =>
            number_format(
                (float)$row['quantity_kg'],
                2
            ) . ' kg',
        'date'  => $row['date']
    ];
}


/**
 * RECENT GROWTH
 */
$stmt = $pdo->prepare("
    SELECT
        gl.id,
        gl.pond_id,
        gl.batch_id,
        gl.avg_weight_g,
        gl.recorded_at,
        p.pond_code
    FROM growth_logs gl
    INNER JOIN ponds_tanks p
        ON p.id = gl.pond_id
    WHERE gl.farm_id = ?
    ORDER BY gl.recorded_at DESC, gl.id DESC
    LIMIT 5
");
$stmt->execute([$farm_id]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $recent_activities[] = [
        'type'  => 'growth',
        'icon'  => 'bi-graph-up',
        'title' => 'Growth Recorded',
        'description' =>
            htmlspecialchars(
                $row['pond_code']
            ) . ' growth measurement',
        'value' =>
            number_format(
                (float)$row['avg_weight_g'],
                2
            ) . ' g',
        'date'  => $row['recorded_at']
    ];
}


/**
 * RECENT MORTALITY
 */
$stmt = $pdo->prepare("
    SELECT
        m.id,
        m.pond_id,
        m.dead_count,
        m.date,
        p.pond_code
    FROM mortality_logs m
    INNER JOIN ponds_tanks p
        ON p.id = m.pond_id
    WHERE m.farm_id = ?
    ORDER BY m.date DESC, m.id DESC
    LIMIT 5
");
$stmt->execute([$farm_id]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $recent_activities[] = [
        'type'  => 'mortality',
        'icon'  => 'bi-exclamation-triangle',
        'title' => 'Mortality Recorded',
        'description' =>
            htmlspecialchars(
                $row['pond_code']
            ) . ' mortality record',
        'value' =>
            number_format(
                (int)$row['dead_count']
            ) . ' fish',
        'date'  => $row['date']
    ];
}


/**
 * SORT ALL ACTIVITIES TOGETHER
 */
usort(
    $recent_activities,
    function ($a, $b) {
        return strtotime($b['date'])
             <=> strtotime($a['date']);
    }
);


/**
 * SHOW ONLY THE 10 MOST RECENT EVENTS
 */
$recent_activities = array_slice(
    $recent_activities,
    0,
    10
);

?>


<!-- =========================================================
     RECENT FARM ACTIVITIES
     ========================================================= -->

<div class="row g-3 mt-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">

            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-semibold">
                        Recent Activities
                    </h6>
                    <small class="text-muted">
                        Latest operational activities for the selected farm
                    </small>
                </div>

                <span class="badge bg-light text-dark border">
                    <?= count($recent_activities); ?> activities
                </span>
            </div>

            <div class="card-body p-0">

                <?php if (empty($recent_activities)): ?>

                    <div class="text-center text-muted py-5">
                        <div class="mb-2">
                            <i class="bi bi-clock-history fs-2"></i>
                        </div>

                        <div class="fw-semibold">
                            No recent activities
                        </div>

                        <small>
                            Farm activities will appear here as records are created.
                        </small>
                    </div>

                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Activity</th>
                                    <th>Details</th>
                                    <th>Date</th>
                                    <th class="text-end pe-4">Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($recent_activities as $activity): ?>

                                    <?php
                                    $activity_type = strtolower(
                                        $activity['type'] ?? ''
                                    );

                                    $icon = $activity['icon'] ?? 'bi-clock-history';

                                    if ($activity_type === 'mortality') {
                                        $badge_class = 'bg-warning text-dark';
                                        $badge_text  = 'Review';
                                    } else {
                                        $badge_class = 'bg-success';
                                        $badge_text  = 'Normal';
                                    }
                                    ?>

                                    <tr>

                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-2">

                                                <div class="text-muted">
                                                    <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                </div>

                                                <div>
                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars(
                                                            $activity['title'] ?? 'Activity',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>
                                                    </div>

                                                    <small class="text-muted">
                                                        <?= ucfirst(
                                                            htmlspecialchars(
                                                                $activity_type,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            )
                                                        ); ?>
                                                    </small>
                                                </div>

                                            </div>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $activity['description'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                            <?php if (!empty($activity['value'])): ?>
                                                <span class="text-muted">
                                                    — <?= htmlspecialchars(
                                                        (string)$activity['value'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string)($activity['date'] ?? ''),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </td>

                                        <td class="text-end pe-4">
                                            <span class="badge <?= $badge_class; ?>">
                                                <?= $badge_text; ?>
                                            </span>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     004-D — QUICK ACTIONS
     ========================================================= -->

<div class="row g-3 mt-4">

    <div class="col-12">
        <div class="card shadow-sm border-0">

            <div class="card-header bg-white
                        d-flex justify-content-between
                        align-items-center">

                <div>
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-lightning-charge me-1"></i>
                        Quick Actions
                    </h6>

                    <small class="text-muted">
                        Common operational actions for the selected farm
                    </small>
                </div>

            </div>

            <div class="card-body">

                <div class="row g-3">

                    <!-- RECORD FEEDING -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a
                            href="/yotribe-system/app/modules/feedng/index.php"
                            class="btn btn-outline-primary w-100
                                   d-flex align-items-center
                                   justify-content-start gap-3
                                   py-3"
                        >
                            <i class="bi bi-droplet fs-4"></i>

                            <span class="text-start">
                                <span class="d-block fw-semibold">
                                    Record Feeding
                                </span>

                                <small class="text-muted">
                                    Record today's feed
                                </small>
                            </span>
                        </a>
                    </div>


                    <!-- RECORD MORTALITY -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a
                            href="/yotribe-system/app/modules/mortality/index.php"
                            class="btn btn-outline-warning w-100
                                   d-flex align-items-center
                                   justify-content-start gap-3
                                   py-3"
                        >
                            <i class="bi bi-exclamation-triangle fs-4"></i>

                            <span class="text-start">
                                <span class="d-block fw-semibold">
                                    Record Mortality
                                </span>

                                <small class="text-muted">
                                    Record fish mortality
                                </small>
                            </span>
                        </a>
                    </div>


                    <!-- RECORD GROWTH -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a
                            href="/yotribe-system/app/modules/growth/index.php"
                            class="btn btn-outline-success w-100
                                   d-flex align-items-center
                                   justify-content-start gap-3
                                   py-3"
                        >
                            <i class="bi bi-graph-up-arrow fs-4"></i>

                            <span class="text-start">
                                <span class="d-block fw-semibold">
                                    Record Growth
                                </span>

                                <small class="text-muted">
                                    Record growth measurements
                                </small>
                            </span>
                        </a>
                    </div>


                    <!-- CREATE HARVEST -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a
                            href="/yotribe-system/app/modules/harvest/create.php"
                            class="btn btn-outline-primary w-100
                                   d-flex align-items-center
                                   justify-content-start gap-3
                                   py-3"
                        >
                            <i class="bi bi-basket2 fs-4"></i>

                            <span class="text-start">
                                <span class="d-block fw-semibold">
                                    Create Harvest
                                </span>

                                <small class="text-muted">
                                    Start a new harvest
                                </small>
                            </span>
                        </a>
                    </div>


                    <!-- RECORD SALE -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a
                            href="/yotribe-system/app/modules/sales/dashboard.php"
                            class="btn btn-outline-success w-100
                                   d-flex align-items-center
                                   justify-content-start gap-3
                                   py-3"
                        >
                            <i class="bi bi-cart-check fs-4"></i>

                            <span class="text-start">
                                <span class="d-block fw-semibold">
                                    Record Sale
                                </span>

                                <small class="text-muted">
                                    Create a sales transaction
                                </small>
                            </span>
                        </a>
                    </div>


                    <!-- VIEW HARVEST -->
                    <div class="col-12 col-sm-6 col-lg-4">
                        <a
                            href="/yotribe-system/app/modules/harvest/view.php"
                            class="btn btn-outline-secondary w-100
                                   d-flex align-items-center
                                   justify-content-start gap-3
                                   py-3"
                        >
                            <i class="bi bi-box-seam fs-4"></i>

                            <span class="text-start">
                                <span class="d-block fw-semibold">
                                    Harvest Inventory
                                </span>

                                <small class="text-muted">
                                    View harvested stock
                                </small>
                            </span>
                        </a>
                    </div>

                </div>

            </div>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        /*
        * =========================================================
        * BIOMASS ANALYSIS
        * =========================================================
        *
        * Biomass is calculated from:
        * current fish population × average fish weight.
        *
        * Data comes from the existing active stocking records.
        */

        const biomassLabels = [];
        const biomassValues = [];

        <?php foreach ($stocks as $stock): ?>

            <?php
                $pondBiomass = (
                    (float)$stock['current_count'] *
                    (float)$stock['avg_weight_g']
                ) / 1000;
            ?>

            biomassLabels.push(
                <?= json_encode($stock['pond_code']) ?>
            );

            biomassValues.push(
                <?= json_encode(round($pondBiomass, 2)) ?>
            );

        <?php endforeach; ?>


        const biomassCanvas =
            document.getElementById('biomassChart');

        if (biomassCanvas && biomassLabels.length > 0) {

            new Chart(biomassCanvas, {

                type: 'bar',

                data: {
                    labels: biomassLabels,

                    datasets: [{
                        label: 'Biomass (kg)',
                        data: biomassValues,
                        borderWidth: 1
                    }]
                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {
                            display: false
                        },

                        tooltip: {
                            callbacks: {
                                label: function (context) {

                                    return Number(
                                        context.raw
                                    ).toLocaleString(
                                        undefined,
                                        {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    ) + ' kg';

                                }
                            }
                        }

                    },

                    scales: {

                        y: {
                            beginAtZero: true,

                            title: {
                                display: true,
                                text: 'Biomass (kg)'
                            }
                        },

                        x: {
                            title: {
                                display: true,
                                text: 'Pond'
                            }
                        }

                    }

                }

            });

        }


        /*
        * =========================================================
        * SALES PERFORMANCE
        * =========================================================
        */

        const salesCanvas =
            document.getElementById('salesChart');

        if (salesCanvas) {

            new Chart(salesCanvas, {

                type: 'doughnut',

                data: {

                    labels: [
                        'Revenue',
                        'Expenses',
                        'Profit'
                    ],

                    datasets: [{
                        data: [
                            <?= json_encode(round($total_sales, 2)) ?>,
                            <?= json_encode(round($total_expenses, 2)) ?>,
                            <?= json_encode(round(max($profit, 0), 2)) ?>
                        ],

                        borderWidth: 1
                    }]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {

                        legend: {
                            position: 'bottom'
                        },

                        tooltip: {

                            callbacks: {

                                label: function (context) {

                                    return context.label +
                                        ': ₦' +
                                        Number(
                                            context.raw
                                        ).toLocaleString(
                                            undefined,
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            }
                                        );

                                }

                            }

                        }

                    }

                }

            });

        }

    });
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
