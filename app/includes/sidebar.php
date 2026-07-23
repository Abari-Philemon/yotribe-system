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
 * CURRENT PAGE
 */

$current = basename($_SERVER['PHP_SELF']);

function nav_active(array $pages)
{
    global $current;

    return in_array($current,$pages)
        ? 'active'
        : '';
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

    background: #e1e9ef;

    overflow-y:auto;

    padding:20px;

    border-right:1px solid #283e2f;

    transition:.3s;

    z-index:1050;
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


<!-- MOBILE TOP BAR -->

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


<div
class="sidebar-overlay"
id="sidebarOverlay"
></div>


<div
class="sidebar"
id="sidebar"
>

    <!-- LOGO -->

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


    <!-- KPI -->

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


    <?php if(canAccess('dashboard')): ?>

    <div class="nav-title">
        Overview
    </div>

    <a
    href="/yotribe-system/app/modules/dashboard/index.php"
    class="nav-link <?= nav_active(['index.php']) ?>"
    >

    📊 Dashboard

    </a>

    <?php endif; ?>


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


    <?php if(canAccess('feeding')): ?>

    <a
    href="/yotribe-system/app/modules/feeding/index.php"
    class="nav-link">

    🍽 Feeding

    </a>

    <?php endif; ?>


    <?php if(canAccess('stocking')): ?>

    <a
    href="/yotribe-system/app/modules/stocking/index.php"
    class="nav-link">

    🐟 Stocking Ponds

    </a>
     <?php endif; ?>


    <?php if(canAccess('stocking')): ?>

    <a
    href="/yotribe-system/app/modules/batches/index.php"
    class="nav-link">

    🐟 Stocking batches

    </a>

    <?php endif; ?>


    <?php if(canAccess('ponds')): ?>

    <a
    href="/yotribe-system/app/modules/ponds/index.php"
    class="nav-link">

    🏞 Ponds

    </a>

    <?php endif; ?>


    <?php if(canAccess('mortality')): ?>

    <a
    href="/yotribe-system/app/modules/mortality/index.php"
    class="nav-link">

    ☠ Mortality

    </a>

    <?php endif; ?>


    <?php if(canAccess('growth')): ?>

    <a
    href="/yotribe-system/app/modules/growth/index.php"
    class="nav-link">

    📈 Growth

    </a>

    <?php endif; ?>


    <?php if(canAccess('feed_store')): ?>

    <div class="nav-title">
        Feed System
    </div>

    <a
    href="/yotribe-system/app/modules/feed_store/index.php"
    class="nav-link">

    📦 Feed Store

    </a>

    <?php endif; ?>


    <?php if(canAccess('hatchery')): ?>

    <div class="nav-title">

        Production

    </div>

    <a
    href="/yotribe-system/app/modules/hatchery/index.php"
    class="nav-link">

    🥚 Hatchery

    </a>

    <?php endif; ?>
    <!-- ==========================================================
    HARVEST MANAGEMENT
    ========================================================== -->

    <?php if (canAccess('harvest')): ?>

        <li class="nav-item">

            <?php
            $harvestPages = [
                'create.php',
                'history.php',
                'view.php',
                'report.php',
                'print.php',
                'close.php',
                'save.php'
            ];

            $harvestActive = nav_active($harvestPages);
            ?>

            <a class="nav-link d-flex justify-content-between align-items-center <?= $harvestActive ? '' : 'collapsed' ?>"
            data-bs-toggle="collapse"
            href="#harvestMenu"
            role="button"
            aria-expanded="<?= $harvestActive ? 'true' : 'false' ?>"
            aria-controls="harvestMenu">

                <span>
                    <i class="bi bi-basket-fill"></i>
                    Harvest
                </span>

                <i class="bi bi-chevron-down"></i>

            </a>

            <div class="collapse <?= $harvestActive ? 'show' : '' ?>"
                id="harvestMenu">

                <ul class="btn-toggle-nav list-unstyled fw-normal small ms-3">

                    <li>
                        <a href="<?= BASE_URL ?>/modules/harvest/create.php"
                        class="nav-link <?= nav_active(['create.php']) ?>">
                            <i class="bi bi-plus-circle"></i>
                            New Harvest
                        </a>
                    </li>

                    <li>
                        <a href="<?= BASE_URL ?>/modules/harvest/history.php"
                        class="nav-link <?= nav_active(['history.php']) ?>">
                            <i class="bi bi-clock-history"></i>
                            Harvest History
                        </a>
                    </li>

                    <li>
                        <a href="<?= BASE_URL ?>/modules/harvest/report.php"
                        class="nav-link <?= nav_active(['report.php']) ?>">
                            <i class="bi bi-bar-chart"></i>
                            Harvest Reports
                        </a>
                    </li>

                </ul>

            </div>

        </li>

    <?php endif; ?>
    <?php if (canAccess('sales')): ?>

        <div class="nav-title">
            Sales
        </div>

        <div class="accordion" id="salesMenu">

            <div class="accordion-item border-0 bg-transparent">

                <h2 class="accordion-header">

                    <button
                        class="accordion-button collapsed shadow-none bg-transparent px-3 py-2"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#salesCollapse">

                        💵 Sales

                    </button>

                </h2>

                <div
                    id="salesCollapse"
                    class="accordion-collapse collapse">

                    <div class="accordion-body p-0">

                        <a href="/yotribe-system/app/modules/sales/dashboard.php"
                        class="nav-link <?= nav_active(['dashboard.php']) ?>">
                            📋 Dashboard
                        </a>

                        <a href="/yotribe-system/app/modules/sales/create.php"
                        class="nav-link <?= nav_active(['create.php']) ?>">
                            ➕ New Sale
                        </a>

                        <a href="/yotribe-system/app/modules/customers/index.php"
                        class="nav-link <?= nav_active(['customers.php']) ?>">
                            👥 Customers
                        </a>

                        <a href="/yotribe-system/app/modules/sales/returns.php"
                        class="nav-link <?= nav_active(['returns.php']) ?>">
                            ↩ Returns
                        </a>

                        <a href="/yotribe-system/app/modules/sales/reports.php"
                        class="nav-link <?= nav_active(['reports.php']) ?>">
                            📊 Sales Reports
                        </a>

                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>



    <?php if(canAccess('finance')): ?>

    <div class="nav-title">

        Finance

    </div>

    <a
    href="/yotribe-system/app/modules/finance/index.php"
    class="nav-link">

    💰 Finance

    </a>

    <?php endif; ?>


    <?php if(canAccess('reports')): ?>

    <div class="nav-title">

        Reports

    </div>

    <a
    href="/yotribe-system/app/modules/reports/index.php"
    class="nav-link">

    📑 Reports

    </a>

    <?php endif; ?>


    <?php if(canAccess('water')): ?>

    <a
    href="/yotribe-system/app/modules/water/index.php"
    class="nav-link">

    💧 Water Quality

    </a>

    <?php endif; ?>


    <?php if(canAccess('staff')): ?>

    <div class="nav-title">

        Administration

    </div>

    <a
    href="/yotribe-system/app/modules/staff/manage.php"
    class="nav-link">

    👥 Staff

    </a>

    <a
    href="/yotribe-system/app/modules/staff/register.php"
    class="nav-link">

    ➕ Register

    </a>

    <?php endif; ?>


    <div class="nav-title">

        Account

    </div>

    <a
    href="/yotribe-system/app/modules/profile/index.php"
    class="nav-link">

    👤 Profile

    </a>

    <a
    href="/yotribe-system/app/auth/logout.php"
    class="nav-link text-danger">

    🚪 Logout

    </a>

</div>


<div class="main">


<script>

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

overlay.addEventListener(
'click',
closeSidebar
);

document
.querySelectorAll('.sidebar .nav-link')
.forEach(link=>{

link.addEventListener(
'click',
()=>{

if(window.innerWidth<992){

closeSidebar();

}

});

});

</script>