<?php

require_once __DIR__ . '/../helpers/permission.php';

/**
 * =========================================================
 * FARM CONTEXT
 * =========================================================
 */

$farm_id = farm_id();

/**
 * FARM DETAILS
 */

$stmt = $pdo->prepare("
    SELECT
        name,
        location,
        size
    FROM farms
    WHERE id = ?
");

$stmt->execute([$farm_id]);

$farm = $stmt->fetch(PDO::FETCH_ASSOC);

$farm_name     = $farm['name'] ?? 'Unknown Farm';
$farm_location = $farm['location'] ?? '';
$farm_size     = ucfirst($farm['size'] ?? '');

/**
 * =========================================================
 * CURRENT PAGE / ROUTE
 * =========================================================
 */

$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

$current_path = '/' . ltrim($current_path, '/');

function nav_active(array $pages)
{
    global $current_path;

    foreach ($pages as $page) {

        $page = '/' . ltrim($page, '/');

        if ($current_path === $page) {
            return 'active';
        }
    }

    return '';
}

?>

<style>

/* ===========================
   SIDEBAR
=========================== */

.sidebar{

    width:280px;

    position:fixed;

    top:0;
    left:0;
    bottom:0;

    background:#e1e9ef;

    overflow-y:auto;

    padding:20px;

    border-right:1px solid #283e2f;

    transition:.3s;

    z-index:1050;
}

.sidebar .dropdown-arrow{

    transition:transform .25s ease;
}

.sidebar .nav-link:not(.collapsed) .dropdown-arrow{

    transform:rotate(180deg);
}

.main{

    margin-left:280px;

    padding:20px;

    transition:.3s;
}


/* ===========================
   MOBILE BAR
=========================== */

.mobile-topbar{

    position:fixed;

    top:0;
    left:0;
    right:0;

    height:60px;

    display:flex;

    align-items:center;

    background:#b0b0b0;

    padding:0 20px;

    z-index:1100;

    box-shadow:0 2px 10px rgba(0,0,0,.08);
}

.mobile-brand{

    font-weight:700;

    margin-left:20px;
}

.menu-toggle{

    border:none;

    background:none;

    font-size:28px;
}


/* ===========================
   OVERLAY
=========================== */

.sidebar-overlay{

    position:fixed;

    inset:0;

    background:rgba(0,0,0,.45);

    display:none;

    z-index:1040;
}

.sidebar-overlay.show{

    display:block;
}


/* ===========================
   NAVIGATION
=========================== */

.nav-title{

    margin-top:25px;

    margin-bottom:10px;

    font-size:12px;

    font-weight:700;

    color:#888;

    text-transform:uppercase;
}

.nav-link{

    display:block;

    padding:12px 14px;

    margin-bottom:6px;

    border-radius:10px;

    color:#333;

    text-decoration:none;

    transition:.2s;
}

.nav-link:hover{

    background:#f4f4f4;
}

.nav-link.active{

    background:#198754;

    color:#fff;
}

.quick-box{

    background:#f7f7f7;

    padding:15px;

    border-radius:14px;
}


/* ===========================
   MOBILE
=========================== */

@media(max-width:991px){

    .sidebar{

        transform:translateX(-100%);

    }

    .sidebar.show{

        transform:translateX(0);

    }

    .main{

        margin-left:0;

        padding-top:80px;

    }

}

</style>


<!-- =========================================================
     MOBILE TOP BAR
========================================================= -->

<div class="mobile-topbar d-lg-none">

    <button
        id="menuToggle"
        class="menu-toggle"
    >
        ☰
    </button>

    <div class="mobile-brand">

        <?= htmlspecialchars($farm_name) ?>

    </div>

</div>


<!-- =========================================================
     SIDEBAR OVERLAY
========================================================= -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<div
    class="sidebar"
    id="sidebar"
>


    <!-- =====================================================
         LOGO / FARM INFORMATION
    ====================================================== -->

    <div class="text-center mb-4">

        <img
            src="/yotribe-system/public/uploads/logo8.png"
            class="img-fluid mb-2"
            style="max-height:100px"
        >

        <div class="fw-bold">

            <?= htmlspecialchars($farm_name) ?>

        </div>

        <small class="text-muted">

            <?= htmlspecialchars($farm_size) ?> Farm

        </small>

    </div>


    <!-- =====================================================
         KPI
    ====================================================== -->

    <div class="quick-box">

        Feed:

        <strong>

            <?= number_format($total_feed ?? 0,0) ?>kg

        </strong>

        <br>

        Biomass:

        <strong>

            <?= number_format($total_biomass ?? 0,0) ?>kg

        </strong>

    </div>


    <!-- =====================================================
         OVERVIEW
    ====================================================== -->

    <?php if(canAccess('dashboard')): ?>

        <div class="nav-title">

            Overview

        </div>

        <a
            href="/yotribe-system/app/modules/dashboard/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/dashboard/index.php'
            ]) ?>"
        >

            📊 Dashboard

        </a>

    <?php endif; ?>


    <!-- =====================================================
         OPERATIONS TITLE
    ====================================================== -->

    <?php if(
        canAccess('feeding') ||
        canAccess('stocking') ||
        canAccess('ponds') ||
        canAccess('mortality') ||
        canAccess('growth')
    ): ?>

        <div class="nav-title">

            Operations

        </div>

    <?php endif; ?>


    <!-- =====================================================
        FEEDING
    ====================================================== -->

    <?php if(canAccess('feeding')): ?>

        <a
            href="/yotribe-system/app/modules/feeding/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/feeding/index.php'
            ]) ?>"
        >

            <i class="bi bi-egg-fried me-2"></i>

            Feeding

        </a>

    <?php endif; ?>

    <!-- =====================================================
        STOCKING PONDS
    ====================================================== -->

    <?php if(canAccess('stocking')): ?>

        <a
            href="/yotribe-system/app/modules/stocking/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/stocking/index.php'
            ]) ?>"
        >

            <i class="bi bi-water me-2"></i>

            Stocking Ponds

        </a>

    <?php endif; ?>

    <!-- =====================================================
         STOCKING BATCHES
    ====================================================== -->

    <?php if(canAccess('batches')): ?>

        <a
            href="/yotribe-system/app/modules/batches/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/batches/index.php'
            ]) ?>"
        >

            <i class="bi bi-diagram-3-fill me-2"></i>

            Stocking Batches

        </a>

    <?php endif; ?>


    <!-- =====================================================
        PONDS
    ====================================================== -->

    <?php if(canAccess('ponds')): ?>

        <a
            href="/yotribe-system/app/modules/ponds/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/ponds/index.php'
            ]) ?>"
        >

            <i class="bi bi-droplet me-2"></i>

            Ponds

        </a>

    <?php endif; ?>



    <!-- =====================================================
        MORTALITY
    ====================================================== -->

    <?php if(canAccess('mortality')): ?>

        <a
            href="/yotribe-system/app/modules/mortality/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/mortality/index.php'
            ]) ?>"
        >

            <i class="bi bi-heart-pulse me-2"></i>

            Mortality

        </a>

    <?php endif; ?>


    <!-- =====================================================
        GROWTH
    ====================================================== -->

    <?php if(canAccess('growth')): ?>

        <a
            href="/yotribe-system/app/modules/growth/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/growth/index.php'
            ]) ?>"
        >

            <i class="bi bi-graph-up-arrow me-2"></i>

            Growth

        </a>

    <?php endif; ?>


    <!-- =====================================================
        FEED STORE
    ====================================================== -->

    <?php if(canAccess('feed_store')): ?>

        <a
            href="/yotribe-system/app/modules/feed_store/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/feed_store/index.php'
            ]) ?>"
        >

            <i class="bi bi-shop me-2"></i>

            Feed Store

        </a>

    <?php endif; ?>


    <!-- =====================================================
        HATCHERY
    ====================================================== -->

    <?php if(canAccess('hatchery')): ?>

        <a
            href="/yotribe-system/app/modules/hatchery/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/hatchery/index.php'
            ]) ?>"
        >

            <i class="bi bi-egg me-2"></i>

            Hatchery

        </a>

    <?php endif; ?>


    <!-- =====================================================
         MAGGOT PRODUCTION
    ====================================================== -->

    <?php if(canAccess('maggot')): ?>

        <a
            href="/yotribe-system/app/modules/maggot/index.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/maggot/index.php'
            ]) ?>"
        >

            <i class="bi bi-bug-fill me-2"></i>

            Maggot Production

        </a>

    <?php endif; ?>


    <!-- ===========================================================
         HARVEST MANAGEMENT
    ============================================================ -->

    <?php if(canAccess('harvest')): ?>

        <?php

        $harvestPages = [

            '/yotribe-system/app/modules/harvest/create.php',

            '/yotribe-system/app/modules/harvest/save.php',

            '/yotribe-system/app/modules/harvest/history.php',

            '/yotribe-system/app/modules/harvest/view.php',

            '/yotribe-system/app/modules/harvest/report.php',

            '/yotribe-system/app/modules/harvest/print.php',

            '/yotribe-system/app/modules/harvest/close.php'

        ];

        ?>

        <li class="nav-item">

            <a
                class="nav-link d-flex justify-content-between align-items-center <?= nav_active($harvestPages) ? '' : 'collapsed' ?>"
                data-bs-toggle="collapse"
                href="#harvestMenu"
                role="button"
                aria-expanded="<?= nav_active($harvestPages) ? 'true' : 'false' ?>"
                aria-controls="harvestMenu"
            >

                <span>

                    <i class="bi bi-basket-fill me-2"></i>

                    Harvest

                </span>

                <i class="bi bi-chevron-down dropdown-arrow"></i>

            </a>


            <div
                id="harvestMenu"
                class="collapse <?= nav_active($harvestPages) ? 'show' : '' ?>"
            >

                <ul class="btn-toggle-nav list-unstyled fw-normal small">


                    <!-- NEW HARVEST -->

                    <li>

                        <a
                            href="/yotribe-system/app/modules/harvest/create.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/harvest/create.php',
                                '/yotribe-system/app/modules/harvest/save.php'
                            ]) ? 'active' : '' ?>"
                        >

                            <i class="bi bi-plus-circle-fill me-2"></i>

                            New Harvest

                        </a>

                    </li>


                    <!-- HARVEST HISTORY -->

                    <li>

                        <a
                            href="/yotribe-system/app/modules/harvest/history.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/harvest/history.php'
                            ]) ? 'active' : '' ?>"
                        >

                            <i class="bi bi-clock-history me-2"></i>

                            Harvest History

                        </a>

                    </li>


                    <!-- VIEW HARVEST -->

                    <li>

                        <a
                            href="/yotribe-system/app/modules/harvest/view.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/harvest/view.php'
                            ]) ? 'active' : '' ?>"
                        >

                            <i class="bi bi-eye-fill me-2"></i>

                            View Harvest

                        </a>

                    </li>


                    <!-- HARVEST REPORTS -->

                    <li>

                        <a
                            href="/yotribe-system/app/modules/harvest/report.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/harvest/report.php'
                            ]) ? 'active' : '' ?>"
                        >

                            <i class="bi bi-file-earmark-bar-graph-fill me-2"></i>

                            Harvest Reports

                        </a>

                    </li>


                    <!-- PRINT HARVEST -->

                    <li>

                        <a
                            href="/yotribe-system/app/modules/harvest/print.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/harvest/print.php'
                            ]) ? 'active' : '' ?>"
                        >

                            <i class="bi bi-printer-fill me-2"></i>

                            Print Harvest

                        </a>

                    </li>


                    <!-- CLOSE HARVEST -->

                    <li>

                        <a
                            href="/yotribe-system/app/modules/harvest/close.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/harvest/close.php'
                            ]) ? 'active' : '' ?>"
                        >

                            <i class="bi bi-lock-fill me-2"></i>

                            Close Harvest

                        </a>

                    </li>


                </ul>

            </div>

        </li>

    <?php endif; ?>


    <!-- =====================================================
         SALES MANAGEMENT
    ====================================================== -->

    <?php if(canAccess('sales')): ?>

        <div class="nav-title">

            Sales

        </div>


        <div class="accordion" id="salesMenu">

            <?php

            $salesPages = [

                '/yotribe-system/app/modules/sales/dashboard.php',

                '/yotribe-system/app/modules/sales/create.php',

                '/yotribe-system/app/modules/customers/index.php',

                '/yotribe-system/app/modules/sales/returns.php',

                '/yotribe-system/app/modules/sales/reports.php'

            ];

            $salesActive = nav_active($salesPages);

            ?>


            <div class="accordion-item border-0 bg-transparent">


                <h2 class="accordion-header">


                    <button
                        class="accordion-button <?= $salesActive ? '' : 'collapsed' ?> shadow-none bg-transparent px-3 py-2"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#salesCollapse"
                        aria-expanded="<?= $salesActive ? 'true' : 'false' ?>"
                        aria-controls="salesCollapse"
                    >

                        <i class="bi bi-cash-stack me-2"></i>

                        Sales

                    </button>


                </h2>


                <div
                    id="salesCollapse"
                    class="accordion-collapse collapse <?= $salesActive ? 'show' : '' ?>"
                >


                    <div class="accordion-body p-0">


                        <!-- SALES DASHBOARD -->

                        <a
                            href="/yotribe-system/app/modules/sales/dashboard.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/sales/dashboard.php'
                            ]) ?>"
                        >

                            <i class="bi bi-clipboard-data me-2"></i>

                            Dashboard

                        </a>


                        <!-- NEW SALE -->

                        <a
                            href="/yotribe-system/app/modules/sales/create.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/sales/create.php'
                            ]) ?>"
                        >

                            <i class="bi bi-plus-circle me-2"></i>

                            New Sale

                        </a>


                        <!-- CUSTOMERS -->

                        <a
                            href="/yotribe-system/app/modules/customers/index.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/customers/index.php'
                            ]) ?>"
                        >

                            <i class="bi bi-people-fill me-2"></i>

                            Customers

                        </a>


                        <!-- RETURNS -->

                        <a
                            href="/yotribe-system/app/modules/sales/returns.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/sales/returns.php'
                            ]) ?>"
                        >

                            <i class="bi bi-arrow-return-left me-2"></i>

                            Returns

                        </a>


                        <!-- SALES REPORTS -->

                        <a
                            href="/yotribe-system/app/modules/sales/reports.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/sales/reports.php'
                            ]) ?>"
                        >

                            <i class="bi bi-bar-chart-fill me-2"></i>

                            Sales Reports

                        </a>


                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         FINANCE
    ====================================================== -->

    <?php if(canAccess('finance')): ?>

        <div class="nav-title">

            Finance

        </div>


        <a
            href="/yotribe-system/app/modules/finance/index.php"
            class="nav-link"
        >

            💰 Finance

        </a>

    <?php endif; ?>


    <!-- =====================================================
         REPORTS
    ====================================================== -->

    <?php if(canAccess('reports')): ?>

        <div class="nav-title">

            Reports

        </div>


        <a
            href="/yotribe-system/app/modules/reports/index.php"
            class="nav-link"
        >

            📑 Reports

        </a>

    <?php endif; ?>


    <!-- =====================================================
         WATER QUALITY
    ====================================================== -->

    <?php if(canAccess('water')): ?>

        <a
            href="/yotribe-system/app/modules/water/index.php"
            class="nav-link"
        >

            💧 Water Quality

        </a>

    <?php endif; ?>


    <!-- =====================================================
         ADMINISTRATION
    ====================================================== -->

    <?php if(canAccess('staff')): ?>

        <div class="nav-title">

            Administration

        </div>


        <a
            href="/yotribe-system/app/modules/staff/manage.php"
            class="nav-link"
        >

            👥 Staff

        </a>


        <a
            href="/yotribe-system/app/modules/staff/register.php"
            class="nav-link"
        >

            ➕ Register

        </a>

    <?php endif; ?>


    <!-- =====================================================
         ACCOUNT
    ====================================================== -->

    <div class="nav-title">

        Account

    </div>


    <a
        href="/yotribe-system/app/modules/profile/index.php"
        class="nav-link"
    >

        👤 Profile

    </a>


    <a
        href="/yotribe-system/app/auth/logout.php"
        class="nav-link text-danger"
    >

        🚪 Logout

    </a>


</div>


<!-- =========================================================
     MAIN CONTENT WRAPPER
========================================================= -->

<div class="main">


<script>

/* =========================================================
   SIDEBAR MOBILE CONTROLS
========================================================= */

const sidebar =
    document.getElementById('sidebar');

const overlay =
    document.getElementById('sidebarOverlay');

const toggle =
    document.getElementById('menuToggle');


function closeSidebar(){

    sidebar.classList.remove('show');

    overlay.classList.remove('show');

}


if(toggle){

    toggle.addEventListener('click',()=>{

        sidebar.classList.toggle('show');

        overlay.classList.toggle('show');

    });

}


if(overlay){

    overlay.addEventListener(
        'click',
        closeSidebar
    );

}


document
.querySelectorAll('.sidebar .nav-link')
.forEach(link=>{

    link.addEventListener(
        'click',
        ()=>{

            if(window.innerWidth < 992){

                closeSidebar();

            }

        }
    );

});

</script>