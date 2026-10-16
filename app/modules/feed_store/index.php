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

/**
 * FARM CONTEXT
 */
$farm_id = farm_id();

/**
 * PAGE TITLE
 */
$page_title = "Dashboard";

/**
 * UNIVERSAL STORE
 * feed_store.farm_id = purchasing / owning farm
 */

$current_farm_id = farm_id();

/**
 * KPI SUMMARY (ALL STOCK)
 */
$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(quantity_kg),0) total_stock,
        COALESCE(SUM(quantity_kg * cost_per_kg),0) stock_value,
        COUNT(*) total_batches
    FROM feed_store
    WHERE status IN ('active','finished')
");
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

/**
 * LOW STOCK
 */
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM feed_store
    WHERE quantity_kg > 0
    AND quantity_kg <= low_stock_level
");
$low_stock = (int)$stmt->fetchColumn();

/**
 * EXPIRING
 */
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM feed_store
    WHERE expiry_date IS NOT NULL
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND quantity_kg > 0
    AND status='active'
");
$expiring = (int)$stmt->fetchColumn();

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
 * STOCK LIST
 */
$stmt = $pdo->query("
    SELECT fs.*, f.name farm_name
    FROM feed_store fs
    LEFT JOIN farms f ON f.id = fs.farm_id
    ORDER BY
        CASE WHEN fs.status='active' THEN 1 ELSE 2 END,
        fs.received_date ASC,
        fs.id ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function statusColor($status){
    return match($status){
        'active'   => 'success',
        'finished' => 'secondary',
        'expired'  => 'danger',
        default    => 'dark'
    };
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<style>
/* ============================================================
   YOTRIBE IFMS — FEED STORE UI
   UI ONLY — NO DATABASE / BUSINESS LOGIC CHANGES
   Responsive: desktop, laptop, tablet and mobile
   ============================================================ */

.feed-store-page{
    --fs-bg:#f4f7fb;
    --fs-card:#ffffff;
    --fs-border:#e6ebf2;
    --fs-text:#172033;
    --fs-muted:#718096;
    --fs-primary:#2563eb;
    --fs-primary-dark:#1d4ed8;
    --fs-success:#16a34a;
    --fs-warning:#d97706;
    --fs-danger:#dc2626;

    background:var(--fs-bg);
    min-height:100vh;
    padding:28px;
    color:var(--fs-text);
    font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;
}

/* PAGE HEADER */

.feed-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    margin-bottom:25px;
}

.feed-title-wrap{
    display:flex;
    align-items:center;
    gap:15px;
}

.feed-title-icon{
    width:52px;
    height:52px;
    min-width:52px;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:#fff;
    font-size:23px;
    box-shadow:0 8px 20px rgba(37,99,235,.22);
}

.feed-title{
    margin:0;
    font-size:25px;
    font-weight:800;
    letter-spacing:-.3px;
}

.feed-subtitle{
    margin:4px 0 0;
    color:var(--fs-muted);
    font-size:13px;
}

.feed-header-actions{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
}

/* BUTTONS */

.feed-btn{
    border:none;
    border-radius:11px;
    padding:10px 15px;
    font-size:13px;
    font-weight:700;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    transition:.2s ease;
    white-space:nowrap;
}

.feed-btn:hover{
    transform:translateY(-1px);
    text-decoration:none;
}

.feed-btn-primary{
    background:var(--fs-primary);
    color:#fff;
}

.feed-btn-primary:hover{
    background:var(--fs-primary-dark);
    color:#fff;
}

.feed-btn-success{
    background:#16a34a;
    color:#fff;
}

.feed-btn-success:hover{
    background:#15803d;
    color:#fff;
}

.feed-btn-light{
    background:#fff;
    color:#334155;
    border:1px solid var(--fs-border);
}

.feed-btn-light:hover{
    background:#f8fafc;
    color:#172033;
}

/* HERO */

.feed-hero{
    position:relative;
    overflow:hidden;
    border-radius:22px;
    padding:28px 30px;
    margin-bottom:25px;
    color:#fff;
    background:
        radial-gradient(circle at 85% 20%,rgba(59,130,246,.35),transparent 28%),
        radial-gradient(circle at 15% 90%,rgba(16,185,129,.16),transparent 25%),
        linear-gradient(135deg,#0f172a,#1e3a8a);
    box-shadow:0 16px 35px rgba(15,23,42,.13);
}

.feed-hero-content{
    position:relative;
    z-index:2;
}

.feed-hero h2{
    margin:0 0 7px;
    font-size:24px;
    font-weight:800;
}

.feed-hero p{
    margin:0;
    color:rgba(255,255,255,.72);
    font-size:14px;
    line-height:1.6;
}

.feed-hero-meta{
    display:flex;
    gap:9px;
    flex-wrap:wrap;
    margin-top:17px;
}

.feed-meta-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 11px;
    border-radius:50px;
    background:rgba(255,255,255,.10);
    border:1px solid rgba(255,255,255,.12);
    color:rgba(255,255,255,.9);
    font-size:12px;
    font-weight:600;
}

/* KPI CARDS */

.feed-kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:18px;
    margin-bottom:25px;
}

.feed-kpi{
    background:var(--fs-card);
    border:1px solid var(--fs-border);
    border-radius:18px;
    padding:20px;
    box-shadow:0 7px 22px rgba(15,23,42,.045);
    position:relative;
    overflow:hidden;
    min-width:0;
}

.feed-kpi::after{
    content:"";
    position:absolute;
    right:-22px;
    bottom:-22px;
    width:75px;
    height:75px;
    border-radius:50%;
    background:rgba(37,99,235,.055);
}

.feed-kpi-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:14px;
}

.feed-kpi-label{
    font-size:12px;
    color:var(--fs-muted);
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.4px;
}

.feed-kpi-icon{
    width:38px;
    height:38px;
    min-width:38px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:17px;
}

.feed-kpi-icon.blue{background:#eff6ff;color:#2563eb}
.feed-kpi-icon.green{background:#ecfdf3;color:#16a34a}
.feed-kpi-icon.orange{background:#fff7ed;color:#d97706}
.feed-kpi-icon.red{background:#fef2f2;color:#dc2626}

.feed-kpi-value{
    font-size:28px;
    font-weight:850;
    line-height:1.1;
    letter-spacing:-.5px;
    overflow-wrap:anywhere;
}

.feed-kpi-unit{
    font-size:12px;
    color:var(--fs-muted);
    font-weight:600;
    margin-top:4px;
}

/* INVENTORY CARD */

.feed-card{
    background:var(--fs-card);
    border:1px solid var(--fs-border);
    border-radius:20px;
    box-shadow:0 7px 25px rgba(15,23,42,.045);
    overflow:hidden;
}

.feed-card-header{
    padding:20px 22px;
    border-bottom:1px solid var(--fs-border);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    flex-wrap:wrap;
}

.feed-card-title{
    margin:0;
    font-size:17px;
    font-weight:800;
}

.feed-card-subtitle{
    margin:4px 0 0;
    color:var(--fs-muted);
    font-size:12px;
    line-height:1.5;
}

.feed-search-wrap{
    position:relative;
    width:340px;
    max-width:100%;
}

.feed-search-wrap i{
    position:absolute;
    left:13px;
    top:50%;
    transform:translateY(-50%);
    color:#94a3b8;
    pointer-events:none;
}

.feed-search{
    width:100%;
    box-sizing:border-box;
    border:1px solid var(--fs-border);
    border-radius:11px;
    padding:10px 13px 10px 37px;
    outline:none;
    font-size:13px;
    background:#f8fafc;
    transition:.2s ease;
}

.feed-search:focus{
    background:#fff;
    border-color:#93c5fd;
    box-shadow:0 0 0 3px rgba(37,99,235,.08);
}

/* TABLE — DESKTOP */

.feed-table-wrap{
    width:100%;
    overflow-x:hidden;
}

.feed-table{
    width:100%;
    max-width:100%;
    margin:0;
    table-layout:fixed;
    border-collapse:separate;
    border-spacing:0;
}

.feed-table thead th{
    background:#f8fafc;
    color:#64748b;
    border-bottom:1px solid var(--fs-border);
    border-top:none;
    padding:12px 9px;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.35px;
    white-space:normal;
    line-height:1.25;
    overflow-wrap:anywhere;
}

.feed-table tbody td{
    padding:13px 9px;
    border-bottom:1px solid #eef2f7;
    color:#334155;
    font-size:12px;
    vertical-align:middle;
    overflow-wrap:anywhere;
    word-break:normal;
}

.feed-table tbody tr{
    transition:.15s ease;
}

.feed-table tbody tr:hover{
    background:#f8fbff;
}

.feed-table tbody tr:last-child td{
    border-bottom:none;
}

/* Balanced column widths */

.feed-table th:nth-child(1),
.feed-table td:nth-child(1){width:11%}

.feed-table th:nth-child(2),
.feed-table td:nth-child(2){width:11%}

.feed-table th:nth-child(3),
.feed-table td:nth-child(3){width:9%}

.feed-table th:nth-child(4),
.feed-table td:nth-child(4){width:9%}

.feed-table th:nth-child(5),
.feed-table td:nth-child(5){width:6%}

.feed-table th:nth-child(6),
.feed-table td:nth-child(6){width:10%}

.feed-table th:nth-child(7),
.feed-table td:nth-child(7){width:11%}

.feed-table th:nth-child(8),
.feed-table td:nth-child(8){width:8%}

.feed-table th:nth-child(9),
.feed-table td:nth-child(9){width:8%}

.feed-table th:nth-child(10),
.feed-table td:nth-child(10){width:8%}

.feed-table th:nth-child(11),
.feed-table td:nth-child(11){width:9%}

.feed-name{
    font-weight:800;
    color:#172033;
}

.feed-secondary{
    color:#8a97a8;
    font-size:10px;
    margin-top:3px;
}

.feed-farm{
    display:inline-flex;
    align-items:center;
    gap:5px;
    max-width:100%;
    background:#eff6ff;
    color:#1d4ed8;
    padding:5px 7px;
    border-radius:8px;
    font-size:10px;
    font-weight:700;
    white-space:normal;
    overflow-wrap:anywhere;
}

.feed-quantity{
    font-weight:800;
    color:#172033;
}

.feed-cost{
    font-weight:750;
    color:#334155;
}

.feed-date{
    color:#64748b;
    white-space:normal;
}

/* STATUS / ALERT */

.feed-status,
.feed-alert{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    max-width:100%;
    padding:5px 7px;
    border-radius:50px;
    font-size:9px;
    font-weight:800;
    white-space:normal;
    text-align:center;
    overflow-wrap:anywhere;
}

.feed-status-active{background:#ecfdf3;color:#15803d}
.feed-status-finished{background:#f1f5f9;color:#64748b}
.feed-status-expired{background:#fef2f2;color:#dc2626}
.feed-status-default{background:#f1f5f9;color:#334155}

.feed-alert-success{background:#ecfdf3;color:#15803d}
.feed-alert-warning{background:#fff7ed;color:#c2410c}
.feed-alert-danger{background:#fef2f2;color:#dc2626}

/* EMPTY STATE */

.feed-empty{
    text-align:center;
    padding:55px 20px;
    color:#94a3b8;
}

.feed-empty-icon{
    width:58px;
    height:58px;
    margin:0 auto 14px;
    border-radius:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#f1f5f9;
    color:#94a3b8;
    font-size:24px;
}

.feed-empty h6{
    margin:0 0 5px;
    color:#475569;
    font-weight:800;
}

.feed-empty p{
    margin:0;
    font-size:13px;
}

/* ============================================================
   LARGE DESKTOP — 1440px+
   ============================================================ */

@media (min-width:1440px){

    .feed-store-page{padding:32px}

    .feed-title{font-size:28px}

    .feed-hero{padding:34px 36px}

    .feed-hero h2{font-size:27px}

    .feed-kpi-grid{gap:22px}

    .feed-kpi{padding:23px}

    .feed-kpi-value{font-size:30px}

    .feed-table thead th{
        padding:13px 11px;
        font-size:11px;
    }

    .feed-table tbody td{
        padding:15px 11px;
        font-size:13px;
    }

}

/* ============================================================
   DESKTOP / LAPTOP — 1200px to 1439px
   ============================================================ */

@media (min-width:1200px) and (max-width:1439px){

    .feed-store-page{padding:28px}

}

/* ============================================================
   SMALL LAPTOP — 992px to 1199px
   ============================================================ */

@media (min-width:992px) and (max-width:1199px){

    .feed-store-page{padding:24px}

    .feed-kpi-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:18px;
    }

    .feed-header{align-items:flex-start}

    .feed-header-actions{justify-content:flex-end}

    .feed-hero{padding:25px}

    .feed-table thead th{
        padding:10px 7px;
        font-size:9px;
    }

    .feed-table tbody td{
        padding:11px 7px;
        font-size:11px;
    }

    .feed-status,
    .feed-alert{
        font-size:8px;
        padding:4px 5px;
    }

}

/* ============================================================
   TABLET — 768px to 991px
   ============================================================ */

@media (min-width:768px) and (max-width:991px){

    .feed-store-page{padding:22px}

    .feed-header{
        flex-direction:column;
        align-items:flex-start;
    }

    .feed-header-actions{
        width:100%;
        justify-content:flex-start;
    }

    .feed-kpi-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:16px;
    }

    .feed-kpi{padding:18px}

    .feed-kpi-value{font-size:26px}

    .feed-hero{padding:24px}

    .feed-hero h2{font-size:23px}

    .feed-card-header{align-items:flex-start}

    .feed-search-wrap{
        width:100%;
        max-width:420px;
    }

    .feed-table thead th{
        padding:9px 6px;
        font-size:8px;
    }

    .feed-table tbody td{
        padding:10px 6px;
        font-size:10px;
    }

    .feed-secondary{font-size:9px}

    .feed-farm,
    .feed-status,
    .feed-alert{
        font-size:8px;
        padding:4px 5px;
    }

}

/* ============================================================
   LARGE MOBILE — 576px to 767px
   Inventory becomes compact cards
   ============================================================ */

@media (min-width:576px) and (max-width:767px){

    .feed-store-page{padding:18px}

    .feed-header{
        flex-direction:column;
        align-items:flex-start;
        gap:16px;
    }

    .feed-title-wrap{width:100%}

    .feed-header-actions{
        width:100%;
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:8px;
    }

    .feed-header-actions .feed-btn{
        justify-content:center;
        padding:10px 8px;
    }

    .feed-kpi-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }

    .feed-kpi{
        padding:17px;
        border-radius:16px;
    }

    .feed-kpi-value{font-size:24px}

    .feed-hero{
        padding:22px;
        border-radius:18px;
    }

    .feed-hero h2{font-size:21px}

    .feed-hero-meta{gap:7px}

    .feed-meta-pill{font-size:11px}

    .feed-card{border-radius:17px}

    .feed-card-header{
        flex-direction:column;
        align-items:stretch;
    }

    .feed-search-wrap{width:100%}

    /* Mobile table-card transformation */

    .feed-table-wrap{
        overflow:hidden;
        padding:12px;
        background:#f8fafc;
    }

    .feed-table{
        display:block;
        width:100%;
        min-width:0;
        table-layout:auto;
    }

    .feed-table thead{
        display:none;
    }

    .feed-table tbody{
        display:block;
        width:100%;
    }

    .feed-table tbody tr.feed-stock-row{
        display:block;
        background:#fff;
        border:1px solid var(--fs-border);
        border-radius:15px;
        margin-bottom:12px;
        padding:4px 0;
        box-shadow:0 4px 14px rgba(15,23,42,.04);
    }

    .feed-table tbody tr.feed-stock-row:hover{
        background:#fff;
    }

    .feed-table tbody tr.feed-stock-row:last-child{
        margin-bottom:0;
    }

    .feed-table tbody tr.feed-stock-row td{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:15px;
        width:100%;
        box-sizing:border-box;
        padding:9px 12px;
        border-bottom:1px solid #eef2f7;
        font-size:11px;
        text-align:right;
    }

    .feed-table tbody tr.feed-stock-row td:last-child{
        border-bottom:none;
    }

    .feed-table tbody tr.feed-stock-row td::before{
        flex:0 0 34%;
        content:"";
        color:#64748b;
        font-size:9px;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:.35px;
        text-align:left;
    }

    .feed-table tbody tr.feed-stock-row td:nth-child(1)::before{content:"Feed"}
    .feed-table tbody tr.feed-stock-row td:nth-child(2)::before{content:"Owner Farm"}
    .feed-table tbody tr.feed-stock-row td:nth-child(3)::before{content:"Batch"}
    .feed-table tbody tr.feed-stock-row td:nth-child(4)::before{content:"Available"}
    .feed-table tbody tr.feed-stock-row td:nth-child(5)::before{content:"Bags"}
    .feed-table tbody tr.feed-stock-row td:nth-child(6)::before{content:"Rate"}
    .feed-table tbody tr.feed-stock-row td:nth-child(7)::before{content:"Supplier"}
    .feed-table tbody tr.feed-stock-row td:nth-child(8)::before{content:"Received"}
    .feed-table tbody tr.feed-stock-row td:nth-child(9)::before{content:"Expiry"}
    .feed-table tbody tr.feed-stock-row td:nth-child(10)::before{content:"Status"}
    .feed-table tbody tr.feed-stock-row td:nth-child(11)::before{content:"Alert"}

    .feed-table tbody tr.feed-stock-row td > *{
        max-width:66%;
    }

    .feed-table tbody tr.feed-stock-row .feed-name,
    .feed-table tbody tr.feed-stock-row .feed-secondary{
        text-align:right;
    }

    .feed-table tbody tr.feed-stock-row .feed-farm,
    .feed-table tbody tr.feed-stock-row .feed-status,
    .feed-table tbody tr.feed-stock-row .feed-alert{
        justify-content:center;
    }

    .feed-table tbody tr:not(.feed-stock-row) td{
        display:block;
        width:100%;
    }

}

/* ============================================================
   MOBILE — 0px to 575px
   ============================================================ */

@media (max-width:575px){

    .feed-store-page{padding:14px}

    .feed-header{
        flex-direction:column;
        align-items:stretch;
        gap:15px;
        margin-bottom:18px;
    }

    .feed-title-wrap{
        align-items:center;
        gap:11px;
    }

    .feed-title-icon{
        width:43px;
        height:43px;
        min-width:43px;
        border-radius:13px;
        font-size:18px;
    }

    .feed-title{
        font-size:20px;
        line-height:1.2;
    }

    .feed-subtitle{font-size:11px}

    .feed-header-actions{
        width:100%;
        display:grid;
        grid-template-columns:1fr;
        gap:8px;
    }

    .feed-header-actions .feed-btn{
        width:100%;
        justify-content:center;
        padding:11px;
    }

    .feed-hero{
        padding:20px;
        border-radius:17px;
        margin-bottom:18px;
    }

    .feed-hero h2{
        font-size:20px;
        line-height:1.25;
    }

    .feed-hero p{
        font-size:12px;
        line-height:1.6;
    }

    .feed-hero-meta{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:7px;
        margin-top:14px;
    }

    .feed-meta-pill{
        justify-content:center;
        padding:7px 5px;
        font-size:10px;
        text-align:center;
    }

    .feed-kpi-grid{
        grid-template-columns:1fr 1fr;
        gap:10px;
        margin-bottom:18px;
    }

    .feed-kpi{
        padding:14px;
        border-radius:15px;
    }

    .feed-kpi-top{
        margin-bottom:10px;
        align-items:flex-start;
    }

    .feed-kpi-label{
        font-size:9px;
        line-height:1.3;
        max-width:70px;
    }

    .feed-kpi-icon{
        width:31px;
        height:31px;
        min-width:31px;
        border-radius:9px;
        font-size:13px;
    }

    .feed-kpi-value{
        font-size:20px;
        line-height:1.15;
    }

    .feed-kpi-unit{
        font-size:9px;
        line-height:1.4;
    }

    .feed-card{border-radius:16px}

    .feed-card-header{
        padding:16px;
        display:flex;
        flex-direction:column;
        align-items:stretch;
        gap:13px;
    }

    .feed-card-title{font-size:15px}

    .feed-card-subtitle{
        font-size:11px;
        line-height:1.5;
    }

    .feed-search-wrap{width:100%}

    .feed-search{
        padding:10px 12px 10px 36px;
        font-size:12px;
    }

    /* Mobile inventory cards */

    .feed-table-wrap{
        overflow:hidden;
        padding:10px;
        background:#f8fafc;
    }

    .feed-table{
        display:block;
        width:100%;
        min-width:0;
        table-layout:auto;
    }

    .feed-table thead{
        display:none;
    }

    .feed-table tbody{
        display:block;
        width:100%;
    }

    .feed-table tbody tr.feed-stock-row{
        display:block;
        width:100%;
        background:#fff;
        border:1px solid var(--fs-border);
        border-radius:14px;
        margin-bottom:10px;
        padding:3px 0;
        box-shadow:0 4px 14px rgba(15,23,42,.04);
    }

    .feed-table tbody tr.feed-stock-row:hover{
        background:#fff;
    }

    .feed-table tbody tr.feed-stock-row td{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:10px;
        width:100%;
        box-sizing:border-box;
        padding:8px 10px;
        border-bottom:1px solid #eef2f7;
        font-size:11px;
        text-align:right;
    }

    .feed-table tbody tr.feed-stock-row td:last-child{
        border-bottom:none;
    }

    .feed-table tbody tr.feed-stock-row td::before{
        flex:0 0 36%;
        color:#64748b;
        font-size:8px;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:.3px;
        line-height:1.35;
        text-align:left;
    }

    .feed-table tbody tr.feed-stock-row td:nth-child(1)::before{content:"Feed"}
    .feed-table tbody tr.feed-stock-row td:nth-child(2)::before{content:"Owner Farm"}
    .feed-table tbody tr.feed-stock-row td:nth-child(3)::before{content:"Batch"}
    .feed-table tbody tr.feed-stock-row td:nth-child(4)::before{content:"Available"}
    .feed-table tbody tr.feed-stock-row td:nth-child(5)::before{content:"Bags"}
    .feed-table tbody tr.feed-stock-row td:nth-child(6)::before{content:"Rate"}
    .feed-table tbody tr.feed-stock-row td:nth-child(7)::before{content:"Supplier"}
    .feed-table tbody tr.feed-stock-row td:nth-child(8)::before{content:"Received"}
    .feed-table tbody tr.feed-stock-row td:nth-child(9)::before{content:"Expiry"}
    .feed-table tbody tr.feed-stock-row td:nth-child(10)::before{content:"Status"}
    .feed-table tbody tr.feed-stock-row td:nth-child(11)::before{content:"Alert"}

    .feed-table tbody tr.feed-stock-row td > *{
        max-width:62%;
    }

    .feed-table tbody tr.feed-stock-row .feed-name,
    .feed-table tbody tr.feed-stock-row .feed-secondary{
        text-align:right;
    }

    .feed-table tbody tr.feed-stock-row .feed-farm,
    .feed-table tbody tr.feed-stock-row .feed-status,
    .feed-table tbody tr.feed-stock-row .feed-alert{
        justify-content:center;
    }

    .feed-table tbody tr:not(.feed-stock-row) td{
        display:block;
        width:100%;
    }

    .feed-empty{
        padding:45px 15px;
    }

}

/* ============================================================
   VERY SMALL PHONES — 0px to 375px
   ============================================================ */

@media (max-width:375px){

    .feed-store-page{padding:10px}

    .feed-title{font-size:18px}

    .feed-subtitle{font-size:10px}

    .feed-title-icon{
        width:39px;
        height:39px;
        min-width:39px;
        font-size:16px;
    }

    .feed-hero{padding:17px}

    .feed-hero h2{font-size:18px}

    .feed-hero p{font-size:11px}

    .feed-hero-meta{
        grid-template-columns:1fr;
    }

    .feed-kpi-grid{gap:8px}

    .feed-kpi{padding:12px}

    .feed-kpi-value{font-size:18px}

    .feed-kpi-label{font-size:8px}

    .feed-kpi-unit{font-size:8px}

    .feed-card-header{padding:14px}

    .feed-table tbody tr.feed-stock-row td{
        padding:7px 9px;
        font-size:10px;
    }

    .feed-table tbody tr.feed-stock-row td::before{
        font-size:7px;
    }

}
</style>


<!-- ============================================================
     FEED STORE PAGE
     ============================================================ -->

<div class="feed-store-page">

    <!-- ========================================================
         PAGE HEADER
         ======================================================== -->

    <div class="feed-header">

        <div class="feed-title-wrap">

            <div class="feed-title-icon">
                <i class="bi bi-box-seam"></i>
            </div>

            <div>
                <h1 class="feed-title">
                    Feed Store
                </h1>

                <p class="feed-subtitle">
                    Inventory, stock levels and feed ownership management
                </p>
            </div>

        </div>


        <div class="feed-header-actions">

            <a href="receive.php"
               class="feed-btn feed-btn-success">

                <i class="bi bi-plus-circle"></i>
                Receive Feed

            </a>

            <a href="issue.php"
               class="feed-btn feed-btn-primary">

                <i class="bi bi-box-arrow-up"></i>
                Issue Feed

            </a>

            <a href="logs.php"
               class="feed-btn feed-btn-light">

                <i class="bi bi-clock-history"></i>
                Logs

            </a>

        </div>

    </div>


    <!-- ========================================================
         HERO
         ======================================================== -->

    <div class="feed-hero">

        <div class="feed-hero-content">

            <h2>
                Universal Feed Store
            </h2>

            <p>
                Centralized feed inventory with multi-farm ownership tracking
                and FIFO-ready stock management.
            </p>

            <div class="feed-hero-meta">

                <span class="feed-meta-pill">
                    <i class="bi bi-building"></i>
                    Multi-Farm
                </span>

                <span class="feed-meta-pill">
                    <i class="bi bi-arrow-repeat"></i>
                    FIFO Ready
                </span>

                <span class="feed-meta-pill">
                    <i class="bi bi-box-seam"></i>
                    Batch Tracking
                </span>

                <span class="feed-meta-pill">
                    <i class="bi bi-shield-check"></i>
                    Inventory Control
                </span>

            </div>

        </div>

    </div>


    <!-- ========================================================
         KPI CARDS
         ======================================================== -->

    <div class="feed-kpi-grid">

        <!-- TOTAL STOCK -->

        <div class="feed-kpi">

            <div class="feed-kpi-top">

                <span class="feed-kpi-label">
                    Total Stock
                </span>

                <div class="feed-kpi-icon blue">
                    <i class="bi bi-box-seam"></i>
                </div>

            </div>

            <div class="feed-kpi-value text-primary">
                <?= number_format($summary['total_stock'],2) ?>
            </div>

            <div class="feed-kpi-unit">
                kilograms available
            </div>

        </div>


        <!-- INVENTORY VALUE -->

        <div class="feed-kpi">

            <div class="feed-kpi-top">

                <span class="feed-kpi-label">
                    Inventory Value
                </span>

                <div class="feed-kpi-icon green">
                    <i class="bi bi-cash-stack"></i>
                </div>

            </div>

            <div class="feed-kpi-value text-success">
                ₦<?= number_format($summary['stock_value'],2) ?>
            </div>

            <div class="feed-kpi-unit">
                current stock valuation
            </div>

        </div>


        <!-- LOW STOCK -->

        <div class="feed-kpi">

            <div class="feed-kpi-top">

                <span class="feed-kpi-label">
                    Low Stock Alerts
                </span>

                <div class="feed-kpi-icon orange">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>

            </div>

            <div class="feed-kpi-value text-warning">
                <?= $low_stock ?>
            </div>

            <div class="feed-kpi-unit">
                batches requiring attention
            </div>

        </div>


        <!-- EXPIRING -->

        <div class="feed-kpi">

            <div class="feed-kpi-top">

                <span class="feed-kpi-label">
                    Expiring Soon
                </span>

                <div class="feed-kpi-icon red">
                    <i class="bi bi-calendar-x"></i>
                </div>

            </div>

            <div class="feed-kpi-value text-danger">
                <?= $expiring ?>
            </div>

            <div class="feed-kpi-unit">
                within the next 30 days
            </div>

        </div>

    </div>


    <!-- ========================================================
         CURRENT INVENTORY
         ======================================================== -->

    <div class="feed-card">

        <div class="feed-card-header">

            <div>

                <h5 class="feed-card-title">
                    Current Batch Inventory
                </h5>

                <p class="feed-card-subtitle">
                    Monitor available feed, ownership, cost, expiry and
                    stock alerts.
                </p>

            </div>


            <div class="feed-search-wrap">

                <i class="bi bi-search"></i>

                <input
                    type="text"
                    id="searchInput"
                    class="feed-search"
                    placeholder="Search feed, batch, supplier or farm"
                    autocomplete="off"
                >

            </div>

        </div>


        <div class="feed-table-wrap">

            <table class="table feed-table" id="stockTable">

                <thead>

                <tr>

                    <th>Feed</th>

                    <th>Owner Farm</th>

                    <th>Batch</th>

                    <th>Available</th>

                    <th>Bags</th>

                    <th>Rate</th>

                    <th>Supplier</th>

                    <th>Received</th>

                    <th>Expiry</th>

                    <th>Status</th>

                    <th>Alert</th>

                </tr>

                </thead>


                <tbody>

                <?php if(empty($rows)): ?>

                    <tr>

                        <td colspan="11">

                            <div class="feed-empty">

                                <div class="feed-empty-icon">
                                    <i class="bi bi-box-seam"></i>
                                </div>

                                <h6>
                                    No Feed Inventory
                                </h6>

                                <p>
                                    No feed batches are currently available
                                    in the store.
                                </p>

                            </div>

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach($rows as $r): ?>

                        <?php

                        $alert = 'OK';
                        $alertClass = 'success';

                        if (
                            $r['quantity_kg'] <= $r['low_stock_level']
                            && $r['quantity_kg'] > 0
                        ){
                            $alert = 'Low Stock';
                            $alertClass = 'warning';
                        }

                        if (
                            !empty($r['expiry_date'])
                            && $r['expiry_date']
                                <= date(
                                    'Y-m-d',
                                    strtotime('+30 days')
                                )
                        ){
                            $alert = 'Near Expiry';
                            $alertClass = 'danger';
                        }

                        if ($r['status'] === 'expired'){
                            $alert = 'Expired';
                            $alertClass = 'danger';
                        }

                        $statusClass = match($r['status']){
                            'active'   => 'feed-status-active',
                            'finished' => 'feed-status-finished',
                            'expired'  => 'feed-status-expired',
                            default    => 'feed-status-default'
                        };

                        ?>

                        <tr class="feed-stock-row">

                            <!-- FEED -->

                            <td>

                                <div class="feed-name">
                                    <?= htmlspecialchars($r['feed_type']) ?>
                                </div>

                                <div class="feed-secondary">
                                    <?= number_format(
                                        $r['bag_weight_kg'],
                                        2
                                    ) ?> kg bags
                                </div>

                            </td>


                            <!-- FARM -->

                            <td>

                                <span class="feed-farm">

                                    <i class="bi bi-building"></i>

                                    <?= htmlspecialchars(
                                        $r['farm_name'] ?? 'Unknown'
                                    ) ?>

                                </span>

                            </td>


                            <!-- BATCH -->

                            <td>

                                <span class="fw-semibold">
                                    <?= htmlspecialchars($r['batch_no']) ?>
                                </span>

                            </td>


                            <!-- AVAILABLE -->

                            <td>

                                <div class="feed-quantity">

                                    <?= number_format(
                                        $r['quantity_kg'],
                                        2
                                    ) ?> kg

                                </div>

                            </td>


                            <!-- BAGS -->

                            <td>

                                <span class="fw-semibold">
                                    <?= (int)$r['bag_count'] ?>
                                </span>

                            </td>


                            <!-- RATE -->

                            <td>

                                <div class="feed-cost">

                                    ₦<?= number_format(
                                        $r['cost_per_kg'],
                                        2
                                    ) ?>/kg

                                </div>

                                <div class="feed-secondary">

                                    ₦<?= number_format(
                                        $r['total_cost'],
                                        2
                                    ) ?> total

                                </div>

                            </td>


                            <!-- SUPPLIER -->

                            <td>

                                <?= htmlspecialchars(
                                    $r['supplier_name']
                                ) ?>

                            </td>


                            <!-- RECEIVED -->

                            <td>

                                <span class="feed-date">
                                    <?= htmlspecialchars(
                                        $r['received_date']
                                    ) ?>
                                </span>

                            </td>


                            <!-- EXPIRY -->

                            <td>

                                <span class="feed-date">

                                    <?= !empty($r['expiry_date'])
                                        ? htmlspecialchars(
                                            $r['expiry_date']
                                        )
                                        : '-'
                                    ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span class="feed-status <?= $statusClass ?>">

                                    <i class="bi bi-circle-fill"
                                       style="font-size:6px"></i>

                                    <?= ucfirst(
                                        htmlspecialchars($r['status'])
                                    ) ?>

                                </span>

                            </td>


                            <!-- ALERT -->

                            <td>

                                <span class="feed-alert feed-alert-<?= $alertClass ?>">

                                    <?php if($alertClass === 'success'): ?>

                                        <i class="bi bi-check-circle"></i>

                                    <?php elseif($alertClass === 'warning'): ?>

                                        <i class="bi bi-exclamation-circle"></i>

                                    <?php else: ?>

                                        <i class="bi bi-exclamation-triangle"></i>

                                    <?php endif; ?>

                                    <?= htmlspecialchars($alert) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<script>
/* ============================================================
   FEED STORE SEARCH
   UI ONLY
   ============================================================ */

const feedSearch = document.getElementById('searchInput');

if (feedSearch) {

    feedSearch.addEventListener('keyup', function () {

        const value = this.value.toLowerCase().trim();

        const rows = document.querySelectorAll(
            '#stockTable tbody tr'
        );

        rows.forEach(function(row) {

            row.style.display =
                row.innerText.toLowerCase().includes(value)
                    ? ''
                    : 'none';

        });

    });

}
</script>

</body>
</html>