<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/../../helpers/rbac.php';

/**
 * MODULE ACCESS
 */
require_permission('feed_store');

$farm_id = farm_id();

/**
 * FILTERS
 */
$from              = $_GET['from'] ?? date('Y-m-01');
$to                = $_GET['to'] ?? date('Y-m-d');
$feed_type         = trim($_GET['feed_type'] ?? '');
$movement_type     = trim($_GET['movement_type'] ?? '');
$consumer_farm_id  = (int)($_GET['consumer_farm_id'] ?? 0);

/**
 * FEED TYPES
 */
$stmt = $pdo->query("
    SELECT DISTINCT feed_type
    FROM feed_store
    ORDER BY feed_type
");
$feed_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * FARMS
 */
$stmt = $pdo->query("
    SELECT id,name
    FROM farms
    ORDER BY name
");
$farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * LOG QUERY
 * farm_id = consuming/requesting farm
 * stock_owner_farm_id = original buyer
 */
$sql = "
SELECT
    l.*,
    p.pond_code,
    sf.name AS consumer_farm,
    ofm.name AS owner_farm,
    st.full_name
FROM feed_store_logs l
LEFT JOIN ponds_tanks p ON p.id = l.pond_id
LEFT JOIN farms sf ON sf.id = l.farm_id
LEFT JOIN farms ofm ON ofm.id = l.stock_owner_farm_id
LEFT JOIN staff st ON st.id = l.storekeeper
WHERE l.date BETWEEN ? AND ?
";

$params = [$from, $to];

if ($feed_type !== '') {
    $sql .= " AND l.feed_type = ? ";
    $params[] = $feed_type;
}

if ($movement_type !== '') {
    $sql .= " AND l.movement_type = ? ";
    $params[] = $movement_type;
}

if ($consumer_farm_id > 0) {
    $sql .= " AND l.farm_id = ? ";
    $params[] = $consumer_farm_id;
}

$sql .= " ORDER BY l.id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * SUMMARY
 */
$stmt = $pdo->prepare("
SELECT
    COALESCE(SUM(received),0) AS total_received,
    COALESCE(SUM(issued),0)   AS total_issued,
    COALESCE(SUM(total_cost),0) AS total_value
FROM feed_store_logs
WHERE date BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$sum = $stmt->fetch(PDO::FETCH_ASSOC);

$total_received = (float)$sum['total_received'];
$total_issued   = (float)$sum['total_issued'];
$total_value    = (float)$sum['total_value'];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<title>Feed Store Logs</title>

<style>
/* ============================================================
   YOTRIBE IFMS — FEED STORE MOVEMENT LOGS
   UI ONLY — BACKEND / QUERY LOGIC PRESERVED
   ============================================================ */

.feed-logs-page{
    --fl-bg:#f4f7fb;
    --fl-card:#ffffff;
    --fl-border:#e5eaf1;
    --fl-text:#172033;
    --fl-muted:#718096;
    --fl-primary:#2563eb;
    --fl-primary-dark:#1d4ed8;
    --fl-success:#16a34a;
    --fl-warning:#d97706;
    --fl-danger:#dc2626;

    min-height:100vh;
    background:var(--fl-bg);
    padding:28px;
    color:var(--fl-text);
    font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;
}

/* PAGE HEADER */

.feed-logs-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    margin-bottom:22px;
}

.feed-logs-title-wrap{
    display:flex;
    align-items:center;
    gap:14px;
}

.feed-logs-title-icon{
    width:50px;
    height:50px;
    min-width:50px;
    border-radius:15px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:#fff;
    font-size:22px;
    box-shadow:0 8px 20px rgba(37,99,235,.20);
}

.feed-logs-title{
    margin:0;
    font-size:25px;
    font-weight:800;
    letter-spacing:-.3px;
}

.feed-logs-subtitle{
    margin:4px 0 0;
    color:var(--fl-muted);
    font-size:13px;
}

.feed-logs-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.feed-log-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    padding:10px 14px;
    border-radius:10px;
    text-decoration:none;
    font-size:12px;
    font-weight:700;
    transition:.2s ease;
}

.feed-log-btn:hover{
    transform:translateY(-1px);
    text-decoration:none;
}

.feed-log-btn-stock{
    background:#fff;
    border:1px solid var(--fl-border);
    color:#334155;
}

.feed-log-btn-stock:hover{
    background:#f8fafc;
    color:#172033;
}

.feed-log-btn-receive{
    background:#16a34a;
    color:#fff;
}

.feed-log-btn-receive:hover{
    background:#15803d;
    color:#fff;
}

.feed-log-btn-issue{
    background:#2563eb;
    color:#fff;
}

.feed-log-btn-issue:hover{
    background:#1d4ed8;
    color:#fff;
}

/* HERO */

.feed-logs-hero{
    position:relative;
    overflow:hidden;
    border-radius:21px;
    padding:27px 30px;
    margin-bottom:22px;
    color:#fff;
    background:
        radial-gradient(circle at 85% 15%,rgba(59,130,246,.35),transparent 28%),
        radial-gradient(circle at 12% 90%,rgba(16,185,129,.14),transparent 25%),
        linear-gradient(135deg,#0f172a,#1e3a8a);
    box-shadow:0 15px 34px rgba(15,23,42,.12);
}

.feed-logs-hero h2{
    margin:0 0 6px;
    font-size:23px;
    font-weight:800;
}

.feed-logs-hero p{
    margin:0;
    color:rgba(255,255,255,.72);
    font-size:13px;
    line-height:1.6;
}

.feed-log-meta{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:15px;
}

.feed-log-meta span{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 10px;
    border-radius:50px;
    background:rgba(255,255,255,.10);
    border:1px solid rgba(255,255,255,.12);
    color:rgba(255,255,255,.9);
    font-size:11px;
    font-weight:600;
}

/* FILTER CARD */

.feed-log-card{
    background:var(--fl-card);
    border:1px solid var(--fl-border);
    border-radius:19px;
    box-shadow:0 7px 24px rgba(15,23,42,.045);
    overflow:hidden;
}

.feed-filter-card{
    padding:20px;
    margin-bottom:22px;
}

.feed-section-heading{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:17px;
}

.feed-section-icon{
    width:34px;
    height:34px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eff6ff;
    color:#2563eb;
}

.feed-section-heading h3{
    margin:0;
    font-size:15px;
    font-weight:800;
}

.feed-section-heading p{
    margin:2px 0 0;
    color:var(--fl-muted);
    font-size:11px;
}

.feed-filter-grid{
    display:grid;
    grid-template-columns:repeat(5,1fr) 150px;
    gap:13px;
    align-items:end;
}

.feed-field label{
    display:block;
    margin-bottom:6px;
    color:#64748b;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.4px;
}

.feed-field .form-control,
.feed-field .form-select{
    min-height:42px;
    border:1px solid var(--fl-border);
    border-radius:10px;
    font-size:12px;
    color:#334155;
    background:#f8fafc;
    box-shadow:none;
}

.feed-field .form-control:focus,
.feed-field .form-select:focus{
    border-color:#93c5fd;
    box-shadow:0 0 0 3px rgba(37,99,235,.08);
    background:#fff;
}

.feed-filter-submit{
    min-height:42px;
    border:none;
    border-radius:10px;
    background:#172033;
    color:#fff;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
    transition:.2s ease;
}

.feed-filter-submit:hover{
    background:#0f172a;
    transform:translateY(-1px);
}

/* SUMMARY */

.feed-log-summary{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:17px;
    margin-bottom:22px;
}

.feed-summary-card{
    position:relative;
    overflow:hidden;
    border-radius:17px;
    padding:19px;
    color:#fff;
    box-shadow:0 9px 24px rgba(15,23,42,.08);
}

.feed-summary-card::after{
    content:"";
    position:absolute;
    width:90px;
    height:90px;
    border-radius:50%;
    right:-28px;
    bottom:-32px;
    background:rgba(255,255,255,.08);
}

.feed-summary-received{
    background:linear-gradient(135deg,#15803d,#16a34a);
}

.feed-summary-issued{
    background:linear-gradient(135deg,#1d4ed8,#2563eb);
}

.feed-summary-value{
    background:linear-gradient(135deg,#111827,#334155);
}

.feed-summary-label{
    position:relative;
    z-index:2;
    display:block;
    margin-bottom:9px;
    color:rgba(255,255,255,.78);
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.45px;
}

.feed-summary-number{
    position:relative;
    z-index:2;
    font-size:27px;
    font-weight:850;
    line-height:1.15;
    letter-spacing:-.5px;
}

.feed-summary-unit{
    position:relative;
    z-index:2;
    margin-top:5px;
    color:rgba(255,255,255,.68);
    font-size:10px;
    font-weight:600;
}

/* TABLE CARD */

.feed-log-table-card{
    overflow:hidden;
}

.feed-log-table-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    padding:18px 20px;
    border-bottom:1px solid var(--fl-border);
}

.feed-log-table-title{
    margin:0;
    font-size:16px;
    font-weight:800;
}

.feed-log-table-subtitle{
    margin:3px 0 0;
    color:var(--fl-muted);
    font-size:11px;
}

.feed-log-count{
    padding:7px 10px;
    border-radius:50px;
    background:#f1f5f9;
    color:#64748b;
    font-size:10px;
    font-weight:800;
    white-space:nowrap;
}

.feed-log-table-wrap{
    width:100%;
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
}

.feed-log-table{
    width:100%;
    min-width:1120px;
    margin:0;
    border-collapse:separate;
    border-spacing:0;
}

.feed-log-table thead th{
    padding:12px 13px;
    background:#f8fafc;
    border-bottom:1px solid var(--fl-border);
    color:#64748b;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.4px;
    white-space:nowrap;
}

.feed-log-table tbody td{
    padding:13px;
    border-bottom:1px solid #eef2f7;
    color:#334155;
    font-size:12px;
    vertical-align:middle;
}

.feed-log-table tbody tr{
    transition:.15s ease;
}

.feed-log-table tbody tr:hover{
    background:#f8fbff;
}

.feed-log-table tbody tr:last-child td{
    border-bottom:none;
}

.feed-log-date{
    color:#64748b;
    white-space:nowrap;
    font-weight:600;
}

.feed-log-feed{
    color:#172033;
    font-weight:800;
}

.feed-log-batch{
    display:inline-flex;
    align-items:center;
    padding:5px 8px;
    border-radius:7px;
    background:#172033;
    color:#fff;
    font-size:10px;
    font-weight:700;
    white-space:nowrap;
}

.feed-log-qty{
    color:#172033;
    font-weight:800;
    white-space:nowrap;
}

.feed-log-farm{
    color:#334155;
    font-weight:600;
}

.feed-log-cost{
    color:#334155;
    font-weight:700;
    white-space:nowrap;
}

.feed-log-staff{
    color:#475569;
    white-space:nowrap;
}

.feed-log-remarks{
    max-width:220px;
    color:#64748b;
    line-height:1.45;
}

.feed-movement{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:6px 10px;
    border-radius:50px;
    font-size:10px;
    font-weight:800;
    white-space:nowrap;
}

.feed-movement-receive{
    background:#ecfdf3;
    color:#15803d;
}

.feed-movement-issue{
    background:#eff6ff;
    color:#1d4ed8;
}

.feed-movement-return{
    background:#fff7ed;
    color:#c2410c;
}

.feed-movement-adjustment{
    background:#f1f5f9;
    color:#475569;
}

/* EMPTY */

.feed-log-empty{
    padding:55px 20px;
    text-align:center;
    color:#94a3b8;
}

.feed-log-empty-icon{
    width:58px;
    height:58px;
    margin:0 auto 13px;
    border-radius:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#f1f5f9;
    color:#94a3b8;
    font-size:24px;
}

.feed-log-empty h4{
    margin:0 0 5px;
    color:#475569;
    font-size:14px;
    font-weight:800;
}

.feed-log-empty p{
    margin:0;
    font-size:12px;
}

/* RESPONSIVE */

@media (min-width:1200px) and (max-width:1439px){
    .feed-logs-page{
        padding:26px;
    }

    .feed-filter-grid{
        grid-template-columns:repeat(3,1fr);
    }

    .feed-filter-submit{
        width:100%;
    }
}

@media (min-width:992px) and (max-width:1199px){
    .feed-logs-page{
        padding:24px;
    }

    .feed-logs-header{
        align-items:flex-start;
    }

    .feed-filter-grid{
        grid-template-columns:repeat(3,1fr);
    }

    .feed-filter-submit{
        width:100%;
    }
}

@media (min-width:768px) and (max-width:991px){
    .feed-logs-page{
        padding:21px;
    }

    .feed-logs-header{
        flex-direction:column;
        align-items:flex-start;
    }

    .feed-logs-actions{
        width:100%;
    }

    .feed-log-btn{
        flex:1;
    }

    .feed-filter-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .feed-filter-submit{
        width:100%;
    }

    .feed-log-table{
        min-width:1120px;
    }
}

@media (min-width:576px) and (max-width:767px){
    .feed-logs-page{
        padding:18px;
    }

    .feed-logs-header{
        flex-direction:column;
        align-items:flex-start;
        gap:15px;
    }

    .feed-logs-actions{
        width:100%;
        display:grid;
        grid-template-columns:repeat(3,1fr);
    }

    .feed-log-btn{
        width:100%;
        padding:10px 7px;
    }

    .feed-log-btn i{
        display:none;
    }

    .feed-filter-grid{
        grid-template-columns:1fr 1fr;
    }

    .feed-filter-submit{
        width:100%;
    }

    .feed-log-summary{
        gap:11px;
    }

    .feed-summary-card{
        padding:16px;
    }

    .feed-summary-number{
        font-size:22px;
    }

    .feed-log-table{
        min-width:1120px;
    }
}

@media (max-width:575px){
    .feed-logs-page{
        padding:13px;
    }

    .feed-logs-header{
        flex-direction:column;
        align-items:stretch;
        gap:13px;
        margin-bottom:17px;
    }

    .feed-logs-title-wrap{
        gap:10px;
    }

    .feed-logs-title-icon{
        width:42px;
        height:42px;
        min-width:42px;
        border-radius:12px;
        font-size:18px;
    }

    .feed-logs-title{
        font-size:20px;
        line-height:1.2;
    }

    .feed-logs-subtitle{
        font-size:10px;
    }

    .feed-logs-actions{
        width:100%;
        display:grid;
        grid-template-columns:1fr 1fr 1fr;
        gap:7px;
    }

    .feed-log-btn{
        width:100%;
        padding:10px 5px;
        font-size:10px;
    }

    .feed-log-btn i{
        display:none;
    }

    .feed-logs-hero{
        padding:19px;
        border-radius:17px;
        margin-bottom:17px;
    }

    .feed-logs-hero h2{
        font-size:19px;
    }

    .feed-logs-hero p{
        font-size:11px;
    }

    .feed-log-meta{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:6px;
    }

    .feed-log-meta span{
        justify-content:center;
        padding:7px 5px;
        font-size:9px;
        text-align:center;
    }

    .feed-filter-card{
        padding:15px;
        border-radius:16px;
        margin-bottom:17px;
    }

    .feed-section-heading{
        margin-bottom:13px;
    }

    .feed-section-heading h3{
        font-size:14px;
    }

    .feed-filter-grid{
        grid-template-columns:1fr;
        gap:10px;
    }

    .feed-filter-submit{
        width:100%;
    }

    .feed-log-summary{
        grid-template-columns:1fr;
        gap:9px;
        margin-bottom:17px;
    }

    .feed-summary-card{
        padding:15px;
        border-radius:15px;
    }

    .feed-summary-number{
        font-size:22px;
    }

    .feed-log-table-card{
        border-radius:16px;
    }

    .feed-log-table-header{
        padding:15px;
        align-items:flex-start;
    }

    .feed-log-table-title{
        font-size:14px;
    }

    .feed-log-count{
        font-size:9px;
    }

    .feed-log-table{
        min-width:1120px;
    }
}

@media (max-width:375px){
    .feed-logs-page{
        padding:10px;
    }

    .feed-logs-title{
        font-size:18px;
    }

    .feed-logs-actions{
        gap:5px;
    }

    .feed-log-btn{
        font-size:9px;
        padding:9px 4px;
    }

    .feed-logs-hero{
        padding:17px;
    }

    .feed-logs-hero h2{
        font-size:18px;
    }

    .feed-filter-card{
        padding:13px;
    }

    .feed-summary-number{
        font-size:20px;
    }
}
</style>

<div class="feed-logs-page">

    <!-- PAGE HEADER -->
    <div class="feed-logs-header">

        <div class="feed-logs-title-wrap">
            <div class="feed-logs-title-icon">
                <i class="bi bi-clock-history"></i>
            </div>

            <div>
                <h1 class="feed-logs-title">Feed Store Logs</h1>
                <p class="feed-logs-subtitle">
                    Receive, issue, return and adjustment movement history
                </p>
            </div>
        </div>

        <div class="feed-logs-actions">
            <a href="index.php" class="feed-log-btn feed-log-btn-stock">
                <i class="bi bi-box-seam"></i>
                Stock
            </a>

            <a href="receive.php" class="feed-log-btn feed-log-btn-receive">
                <i class="bi bi-plus-circle"></i>
                Receive
            </a>

            <a href="issue.php" class="feed-log-btn feed-log-btn-issue">
                <i class="bi bi-box-arrow-up"></i>
                Issue
            </a>
        </div>

    </div>

    <!-- HERO -->
    <div class="feed-logs-hero">

        <h2>Universal Feed Store Movement Logs</h2>

        <p>
            Centralized movement history with feed quantities, ownership,
            consuming farms, pond destinations and cost tracking.
        </p>

        <div class="feed-log-meta">
            <span>
                <i class="bi bi-building"></i>
                Multi-Farm
            </span>

            <span>
                <i class="bi bi-arrow-left-right"></i>
                Movement Tracking
            </span>

            <span>
                <i class="bi bi-cash-stack"></i>
                Cost Tracking
            </span>

            <span>
                <i class="bi bi-clock-history"></i>
                Last 500 Records
            </span>
        </div>

    </div>

    <!-- FILTER -->
    <div class="feed-log-card feed-filter-card">

        <div class="feed-section-heading">
            <div class="feed-section-icon">
                <i class="bi bi-funnel"></i>
            </div>

            <div>
                <h3>Filter Movement Logs</h3>
                <p>Refine the movement history by date, feed and farm.</p>
            </div>
        </div>

        <form method="GET">

            <div class="feed-filter-grid">

                <div class="feed-field">
                    <label>From</label>
                    <input
                        type="date"
                        name="from"
                        value="<?= htmlspecialchars($from) ?>"
                        class="form-control"
                    >
                </div>

                <div class="feed-field">
                    <label>To</label>
                    <input
                        type="date"
                        name="to"
                        value="<?= htmlspecialchars($to) ?>"
                        class="form-control"
                    >
                </div>

                <div class="feed-field">
                    <label>Feed Type</label>
                    <select name="feed_type" class="form-select">
                        <option value="">All Feed Types</option>

                        <?php foreach($feed_types as $ft): ?>
                            <option
                                value="<?= htmlspecialchars($ft) ?>"
                                <?= $feed_type == $ft ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($ft) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="feed-field">
                    <label>Movement</label>
                    <select name="movement_type" class="form-select">
                        <option value="">All Movements</option>

                        <option value="receive"
                            <?= $movement_type == 'receive' ? 'selected' : '' ?>>
                            Receive
                        </option>

                        <option value="issue"
                            <?= $movement_type == 'issue' ? 'selected' : '' ?>>
                            Issue
                        </option>

                        <option value="adjustment"
                            <?= $movement_type == 'adjustment' ? 'selected' : '' ?>>
                            Adjustment
                        </option>

                        <option value="return"
                            <?= $movement_type == 'return' ? 'selected' : '' ?>>
                            Return
                        </option>
                    </select>
                </div>

                <div class="feed-field">
                    <label>Consumer Farm</label>
                    <select name="consumer_farm_id" class="form-select">
                        <option value="0">All Farms</option>

                        <?php foreach($farms as $f): ?>
                            <option
                                value="<?= (int)$f['id'] ?>"
                                <?= $consumer_farm_id == $f['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($f['name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <button type="submit" class="feed-filter-submit">
                    <i class="bi bi-search"></i>
                    Apply Filter
                </button>

            </div>

        </form>

    </div>

    <!-- SUMMARY -->
    <div class="feed-log-summary">

        <div class="feed-summary-card feed-summary-received">
            <span class="feed-summary-label">
                <i class="bi bi-box-arrow-in-down"></i>
                Total Received
            </span>

            <div class="feed-summary-number">
                <?= number_format($total_received,2) ?> kg
            </div>

            <div class="feed-summary-unit">
                Feed received during selected period
            </div>
        </div>

        <div class="feed-summary-card feed-summary-issued">
            <span class="feed-summary-label">
                <i class="bi bi-box-arrow-up"></i>
                Total Issued
            </span>

            <div class="feed-summary-number">
                <?= number_format($total_issued,2) ?> kg
            </div>

            <div class="feed-summary-unit">
                Feed issued during selected period
            </div>
        </div>

        <div class="feed-summary-card feed-summary-value">
            <span class="feed-summary-label">
                <i class="bi bi-cash-stack"></i>
                Total Movement Value
            </span>

            <div class="feed-summary-number">
                ₦<?= number_format($total_value,2) ?>
            </div>

            <div class="feed-summary-unit">
                Recorded movement value
            </div>
        </div>

    </div>

    <!-- TABLE -->
    <div class="feed-log-card feed-log-table-card">

        <div class="feed-log-table-header">

            <div>
                <h3 class="feed-log-table-title">
                    Movement History
                </h3>

                <p class="feed-log-table-subtitle">
                    Detailed feed store transactions for the selected period
                </p>
            </div>

            <span class="feed-log-count">
                <?= number_format(count($logs)) ?> RECORDS
            </span>

        </div>

        <div class="feed-log-table-wrap">

            <table class="feed-log-table">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Feed</th>
                        <th>Batch</th>
                        <th>Qty</th>
                        <th>Consumer Farm</th>
                        <th>Owner Farm</th>
                        <th>Pond</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                        <th>Staff</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>

                <?php if($logs): ?>

                    <?php foreach($logs as $row): ?>

                        <tr>

                            <td class="feed-log-date">
                                <?= htmlspecialchars($row['date']) ?>
                            </td>

                            <td>
                                <?php if($row['movement_type'] == 'receive'): ?>

                                    <span class="feed-movement feed-movement-receive">
                                        <i class="bi bi-box-arrow-in-down"></i>
                                        Receive
                                    </span>

                                <?php elseif($row['movement_type'] == 'issue'): ?>

                                    <span class="feed-movement feed-movement-issue">
                                        <i class="bi bi-box-arrow-up"></i>
                                        Issue
                                    </span>

                                <?php elseif($row['movement_type'] == 'return'): ?>

                                    <span class="feed-movement feed-movement-return">
                                        <i class="bi bi-arrow-return-left"></i>
                                        Return
                                    </span>

                                <?php else: ?>

                                    <span class="feed-movement feed-movement-adjustment">
                                        <i class="bi bi-sliders"></i>
                                        Adjust
                                    </span>

                                <?php endif; ?>
                            </td>

                            <td class="feed-log-feed">
                                <?= htmlspecialchars($row['feed_type']) ?>
                            </td>

                            <td>
                                <span class="feed-log-batch">
                                    <?= htmlspecialchars($row['batch_no']) ?>
                                </span>
                            </td>

                            <td class="feed-log-qty">
                                <?php
                                $qty = $row['received'] > 0
                                    ? $row['received']
                                    : $row['issued'];

                                echo number_format((float)$qty,2) . ' kg';
                                ?>
                            </td>

                            <td class="feed-log-farm">
                                <?= htmlspecialchars($row['consumer_farm'] ?? '-') ?>
                            </td>

                            <td class="feed-log-farm">
                                <?= htmlspecialchars($row['owner_farm'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['pond_code'] ?? '-') ?>
                            </td>

                            <td class="feed-log-cost">
                                ₦<?= number_format((float)$row['unit_cost'],2) ?>
                            </td>

                            <td class="feed-log-cost">
                                ₦<?= number_format((float)$row['total_cost'],2) ?>
                            </td>

                            <td class="feed-log-staff">
                                <?= htmlspecialchars($row['full_name'] ?? '-') ?>
                            </td>

                            <td class="feed-log-remarks">
                                <?= htmlspecialchars($row['remarks'] ?? '-') ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="12">

                            <div class="feed-log-empty">

                                <div class="feed-log-empty-icon">
                                    <i class="bi bi-inbox"></i>
                                </div>

                                <h4>No Movement Records Found</h4>

                                <p>
                                    No feed store movement records match the selected filters.
                                </p>

                            </div>

                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
