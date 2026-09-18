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


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>

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

    /* Light agricultural-tech background */
    background-color:#dce8e3;

    background-image:
        radial-gradient(
            circle at 15% 10%,
            rgba(25, 135, 84, .12),
            transparent 38%
        ),
        radial-gradient(
            circle at 90% 85%,
            rgba(52, 152, 219, .08),
            transparent 35%
        ),
        linear-gradient(
            rgba(25, 135, 84, .035) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(25, 135, 84, .035) 1px,
            transparent 1px
        );

    background-size:
        auto,
        auto,
        24px 24px,
        24px 24px;

    overflow-y:auto;
    padding:20px;

    border-right:1px solid rgba(25, 135, 84, .28);

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


/* =========================================================
   011-B — SIDEBAR SPACING & ALIGNMENT
   ========================================================= */

.sidebar-nav .nav-link {
    display: flex;
    align-items: center;
    min-height: 42px;
    padding: 9px 14px;
    gap: 0;
}

.sidebar-nav .nav-link .bi {
    width: 20px;
    min-width: 20px;
    margin-right: 10px !important;
    text-align: center;
    font-size: 1rem;
    line-height: 1;
}

.sidebar-nav .nav-link span {
    line-height: 1.3;
}

.sidebar-nav .nav-item {
    margin-bottom: 2px;
}

.sidebar-nav .collapse .nav-link {
    min-height: 38px;
    padding-top: 7px;
    padding-bottom: 7px;
}

.sidebar-nav .collapse .nav-link .bi {
    font-size: 0.9rem;
}

.sidebar-nav .nav-section {
    margin-top: 18px;
    margin-bottom: 7px;
}

.sidebar-nav .nav-section:first-child {
    margin-top: 4px;
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
/* =========================================================
   011-C — ACTIVE & HOVER STATES
   ========================================================= */

/* Normal sidebar links */
.sidebar .nav-link {
    position: relative;
    transition:
        background-color .2s ease,
        color .2s ease,
        transform .2s ease;
}

/* Hover state */
.sidebar .nav-link:hover {
    background: rgba(255, 255, 255, .55);
    color: #198754;
    transform: translateX(2px);
}

/* Active page */
.sidebar .nav-link.active {
    background: #198754;
    color: #fff;
    font-weight: 600;
    box-shadow: 0 3px 8px rgba(25, 135, 84, .18);
}

/* Active page icon */
.sidebar .nav-link.active .bi {
    color: #fff;
}

/* Keep active link stable when hovered */
.sidebar .nav-link.active:hover {
    background: #198754;
    color: #fff;
    transform: translateX(2px);
}

/* Accordion parent */
.sidebar .accordion-button {
    transition:
        background-color .2s ease,
        color .2s ease;
}

/* Accordion parent hover */
.sidebar .accordion-button:hover {
    background: rgba(255, 255, 255, .55) !important;
    color: #198754;
}

/* Expanded accordion parent */
.sidebar .accordion-button:not(.collapsed) {
    background: #198754 !important;
    color: #fff !important;
    border-radius: 10px;
    box-shadow: 0 3px 8px rgba(25, 135, 84, .18);
}

/* Expanded accordion parent icon */
.sidebar .accordion-button:not(.collapsed) .bi {
    color: #fff;
}

/* Accordion arrow when expanded */
.sidebar .accordion-button:not(.collapsed)::after {
    filter: brightness(0) invert(1);
}

/* Accordion submenu links */
.sidebar .accordion-body .nav-link {
    margin-left: 8px;
}

/* Active submenu item */
.sidebar .accordion-body .nav-link.active {
    background: #198754;
    color: #fff;
}

/* Submenu hover */
.sidebar .accordion-body .nav-link:hover {
    background: rgba(255, 255, 255, .55);
    color: #198754;
}

/* =========================================================
   011-E — SECTION HEADINGS
   ========================================================= */

/* Section heading base */
.sidebar .nav-title {
    margin: 18px 4px 7px;
    padding: 0 10px;
    font-size: .70rem;
    line-height: 1.2;
    font-weight: 700;
    letter-spacing: .08em;
    color: #5f6f68;
    text-transform: uppercase;
}

/* Keep section headings visually quiet and non-interactive */
.sidebar .nav-title {
    pointer-events: none;
}

/* =========================================================
   011-D — SUBMENU / ACCORDION STYLING
   ========================================================= */

/* Operations / Harvest / Sales dropdown parents */
.sidebar .operations-dropdown,
.sidebar .accordion-button {
    min-height: 42px;
    border-radius: 10px;
}

/* Bootstrap accordion containers remain transparent */
.sidebar .accordion-item,
.sidebar .accordion-header {
    background: transparent;
    border: 0;
}

/* Dropdown submenu containers */
.sidebar #operationsMenu,
.sidebar #harvestMenu,
.sidebar #salesCollapse {
    position: relative;
    padding: 5px 0 6px 0;
}

/* Subtle vertical submenu guide */
.sidebar #operationsMenu::before,
.sidebar #harvestMenu::before,
.sidebar #salesCollapse::before {
    content: "";
    position: absolute;
    top: 6px;
    bottom: 8px;
    left: 18px;
    width: 1px;
    background: rgba(25, 135, 84, .18);
}

/* Operations / Harvest / Sales child links */
.sidebar #operationsMenu .nav-link,
.sidebar #harvestMenu .nav-link,
.sidebar #salesCollapse .nav-link {
    position: relative;
    min-height: 38px;
    margin: 2px 0 2px 28px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: .94rem;
}

/* Horizontal connector from guide to child */
.sidebar #operationsMenu .nav-link::before,
.sidebar #harvestMenu .nav-link::before,
.sidebar #salesCollapse .nav-link::before {
    content: "";
    position: absolute;
    left: -11px;
    top: 50%;
    width: 9px;
    height: 1px;
    background: rgba(25, 135, 84, .18);
}

/* Child icons */
.sidebar #operationsMenu .nav-link .bi,
.sidebar #harvestMenu .nav-link .bi,
.sidebar #salesCollapse .nav-link .bi {
    width: 18px;
    min-width: 18px;
    margin-right: 8px !important;
    font-size: .88rem;
}

/* Active child */
.sidebar #operationsMenu .nav-link.active,
.sidebar #harvestMenu .nav-link.active,
.sidebar #salesCollapse .nav-link.active {
    margin-left: 24px;
}

/* Active child connector */
.sidebar #operationsMenu .nav-link.active::before,
.sidebar #harvestMenu .nav-link.active::before,
.sidebar #salesCollapse .nav-link.active::before {
    background: #198754;
}

/* Operations dropdown parent */
.sidebar .operations-dropdown {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    border: 0;
    text-align: left;
    color: #333;
    text-decoration: none;
    background: transparent;
    padding: 9px 14px;
    font-weight: 600;
}

/* Operations dropdown hover */
.sidebar .operations-dropdown:hover {
    background: rgba(255, 255, 255, .55);
    color: #198754;
}

/* Expanded Operations parent */
.sidebar .operations-dropdown:not(.collapsed) {
    background: #198754;
    color: #fff;
    box-shadow: 0 3px 8px rgba(25, 135, 84, .18);
}

/* Expanded Operations icon and arrow */
.sidebar .operations-dropdown:not(.collapsed) .bi {
    color: #fff;
}

.sidebar .operations-dropdown:not(.collapsed) .dropdown-arrow {
    transform: rotate(180deg);
}

/* Small-screen submenu refinement */
@media(max-width:991px){
    .sidebar #operationsMenu .nav-link,
    .sidebar #harvestMenu .nav-link,
    .sidebar #salesCollapse .nav-link {
        margin-left: 24px;
    }
}

/* =========================================================
   011-F — FINAL SIDEBAR VISUAL PASS
   ========================================================= */

/* Final box-sizing and scrollbar refinement */
.sidebar,
.sidebar * {
    box-sizing: border-box;
}

.sidebar {
    scrollbar-width: thin;
    scrollbar-color: rgba(25, 135, 84, .35) transparent;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(25, 135, 84, .35);
    border-radius: 10px;
}

/* Final navigation rhythm */
.sidebar .nav-link {
    min-height: 42px;
    margin-bottom: 4px;
    border-radius: 10px;
}

/* Keep dropdown parents aligned with normal navigation */
.sidebar .operations-dropdown,
.sidebar .accordion-button {
    min-height: 42px;
    border-radius: 10px;
}

/* Prevent hover movement from disturbing dropdown layout */
.sidebar .operations-dropdown:hover,
.sidebar .accordion-button:hover {
    transform: none;
}

/* Keep expanded dropdown parents visually stable */
.sidebar .operations-dropdown:not(.collapsed),
.sidebar .accordion-button:not(.collapsed) {
    transform: none;
}

/* Final submenu spacing */
.sidebar #operationsMenu,
.sidebar #harvestMenu,
.sidebar #salesCollapse {
    margin-bottom: 4px;
}

.sidebar #operationsMenu .nav-link,
.sidebar #harvestMenu .nav-link,
.sidebar #salesCollapse .nav-link {
    margin-top: 2px;
    margin-bottom: 2px;
}

/* Final mobile topbar consistency */
.mobile-topbar {
    background: #dce8e3;
    border-bottom: 1px solid rgba(25, 135, 84, .28);
}

.mobile-brand {
    color: #198754;
}

.menu-toggle {
    color: #198754;
    cursor: pointer;
}

/* Final responsive refinement */
@media(max-width:991px){
    .sidebar {
        width: 280px;
        padding: 16px;
    }

    .main {
        padding-left: 16px;
        padding-right: 16px;
    }
}

@media(min-width:992px){
    .sidebar {
        width: 280px;
    }

    .main {
        margin-left: 280px;
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
         OPERATIONS DROPDOWN
    ===================================================== -->

    <?php
    $operationsPages = [
        '/yotribe-system/app/modules/feeding/index.php',
        '/yotribe-system/app/modules/stocking/index.php',
        '/yotribe-system/app/modules/batches/index.php',
        '/yotribe-system/app/modules/ponds/index.php',
        '/yotribe-system/app/modules/mortality/index.php',
        '/yotribe-system/app/modules/growth/index.php'
    ];

    $operationsActive = nav_active($operationsPages);

    $operationsVisible =
        canAccess('feeding') ||
        canAccess('stocking') ||
        canAccess('batches') ||
        canAccess('ponds') ||
        canAccess('mortality') ||
        canAccess('growth');
    ?>

    <?php if($operationsVisible): ?>

        <div class="nav-title">
            Operations
        </div>

        <a
            class="operations-dropdown nav-link <?= $operationsActive ? '' : 'collapsed' ?>"
            data-bs-toggle="collapse"
            href="#operationsMenu"
            role="button"
            aria-expanded="<?= $operationsActive ? 'true' : 'false' ?>"
            aria-controls="operationsMenu"
        >
            <span>
                <i class="bi bi-gear-wide-connected me-2"></i>
                Operations
            </span>

            <i class="bi bi-chevron-down dropdown-arrow"></i>
        </a>

        <div
            id="operationsMenu"
            class="collapse <?= $operationsActive ? 'show' : '' ?>"
        >

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

        </div>

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

                '/yotribe-system/app/modules/sales/payment.php',

                '/yotribe-system/app/modules/sales/refund.php',

                '/yotribe-system/app/modules/sales/report.php'

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

                        <!-- PAYMENTS -->

                        <a
                            href="/yotribe-system/app/modules/sales/payment.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/sales/payment.php'
                            ]) ?>"
                        >

                            <i class="bi bi-credit-card-fill me-2"></i>

                            Payments

                        </a>


                        <!-- RETURNS -->

                        <a
                            href="/yotribe-system/app/modules/sales/refund.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/sales/refund.php'
                            ]) ?>"
                        >

                            <i class="bi bi-arrow-return-left me-2"></i>

                            Returns

                        </a>


                        <!-- SALES REPORTS -->

                        <a
                            href="/yotribe-system/app/modules/sales/report.php"
                            class="nav-link <?= nav_active([
                                '/yotribe-system/app/modules/sales/report.php'
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
        <i class="bi bi-cash-stack me-2"></i>
        Finance

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

            <i class="bi bi-bar-chart-fill me-2"></i>
            Reports

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

            <i class="bi bi-droplet-fill me-2"></i>
            Water Quality

        </a>

    <?php endif; ?>


    <!-- =====================================================
         ADMINISTRATION
    ====================================================== -->

        <?php if(canAccess('staff')): ?>
        <div class="nav-title">Administration</div>

        <a
            href="/yotribe-system/app/modules/staff/manage.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/staff/manage.php'
            ]) ?>"
        >
            <i class="bi bi-people-fill me-2"></i>
            Staff
        </a>


        <a
            href="/yotribe-system/app/modules/staff/register.php"
            class="nav-link <?= nav_active([
                '/yotribe-system/app/modules/staff/register.php'
            ]) ?>"
        >
            <i class="bi bi-person-plus-fill me-2"></i>
            Register Staff
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
        class="nav-link <?= nav_active([
            '/yotribe-system/app/modules/profile/index.php'
        ]) ?>"
    >
        <i class="bi bi-person-circle me-2"></i>
        Profile
    </a>

    <a
        href="/yotribe-system/app/auth/logout.php"
        class="nav-link"
    >
        <i class="bi bi-box-arrow-right me-2"></i>
        Logout
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