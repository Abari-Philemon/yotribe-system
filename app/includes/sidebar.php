<?php
require_once __DIR__ . '/../helpers/permission.php';

/**
 * =========================================================
 * FARM CONTEXT
 * =========================================================
 */

$farm_id = farm_id();

/**
 * FETCH FARM DETAILS
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
 * CURRENT PAGE
 * =========================================================
 */

$current = basename($_SERVER['PHP_SELF']);

/**
 * ACTIVE LINK HELPER
 */

function nav_active(array $pages)
{
    global $current;

    return in_array($current, $pages)
        ? 'active'
        : '';
}
?>

<div class="sidebar" id="sidebar">

    <!-- ================================================= -->
    <!-- FARM BRAND -->
    <!-- ================================================= -->

    <div class="text-center mb-4">

        <img
            src="/yotribe-system/public/uploads/logo8.png"
            class="img-fluid mb-2"
            style="max-height:70px"
        >

        <div class="fw-bold">
            <?= htmlspecialchars($farm_name) ?>
        </div>

        <small class="text-muted">
            <?= htmlspecialchars($farm_size) ?> Farm
        </small>

    </div>

    <!-- ================================================= -->
    <!-- QUICK KPI -->
    <!-- ================================================= -->

    <div class="quick-box mb-4">

        Feed:
        <strong>
            <?= number_format($total_feed ?? 0, 0) ?>kg
        </strong>

        <br>

        Biomass:
        <strong>
            <?= number_format($total_biomass ?? 0, 0) ?>kg
        </strong>

    </div>

    <!-- ================================================= -->
    <!-- OVERVIEW -->
    <!-- ================================================= -->

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

    <!-- ================================================= -->
    <!-- OPERATIONS -->
    <!-- ================================================= -->

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
            class="nav-link <?= nav_active([
                'index.php',
                'create.php'
            ]) ?>"
        >
            🍽 Feeding
        </a>

    <?php endif; ?>

    <?php if(canAccess('stocking')): ?>

        <a
            href="/yotribe-system/app/modules/stocking/index.php"
            class="nav-link"
        >
            🐟 Stocking
        </a>

    <?php endif; ?>

    <?php if(canAccess('ponds')): ?>

        <a
            href="/yotribe-system/app/modules/ponds/index.php"
            class="nav-link"
        >
            🏞 Ponds
        </a>

    <?php endif; ?>

    <?php if(canAccess('mortality')): ?>

        <a
            href="/yotribe-system/app/modules/mortality/index.php"
            class="nav-link"
        >
            ☠ Mortality
        </a>

    <?php endif; ?>

    <?php if(canAccess('growth')): ?>

        <a
            href="/yotribe-system/app/modules/growth/index.php"
            class="nav-link"
        >
            📈 Growth
        </a>

    <?php endif; ?>

    <!-- ================================================= -->
    <!-- FEED SYSTEM -->
    <!-- ================================================= -->

    <?php if(canAccess('feed_store')): ?>

        <div class="nav-title">
            Feed System
        </div>

        <a
            href="/yotribe-system/app/modules/feed_store/index.php"
            class="nav-link"
        >
            📦 Feed Store
        </a>

    <?php endif; ?>

    <!-- ================================================= -->
    <!-- HATCHERY -->
    <!-- ================================================= -->

    <?php if(canAccess('hatchery')): ?>

        <div class="nav-title">
            Production
        </div>

        <a
            href="/yotribe-system/app/modules/hatchery/index.php"
            class="nav-link"
        >
            🥚 Hatchery
        </a>

    <?php endif; ?>

    <!-- ================================================= -->
    <!-- MAGGOT -->
    <!-- ================================================= -->

    <?php if(
        in_array(
            $_SESSION['role'],
            ['super_admin','owner','manager']
        )
    ): ?>

        <a
            href="/yotribe-system/app/modules/maggot/index.php"
            class="nav-link"
        >
            🪱 Maggot
        </a>

    <?php endif; ?>

    <!-- ================================================= -->
    <!-- FINANCE -->
    <!-- ================================================= -->

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

    <!-- ================================================= -->
    <!-- REPORTS -->
    <!-- ================================================= -->

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

    <!-- ================================================= -->
    <!-- WATER -->
    <!-- ================================================= -->

    <?php if(canAccess('water')): ?>

        <a
            href="/yotribe-system/app/modules/water/index.php"
            class="nav-link"
        >
            💧 Water Quality
        </a>

    <?php endif; ?>

    <!-- ================================================= -->
    <!-- STAFF -->
    <!-- ================================================= -->

    <?php if(canAccess('staff')): ?>

        <div class="nav-title">
            Administration
        </div>

        <a
            href="/yotribe-system/app/modules/staff/manage.php"
            class="nav-link"
        >
            👥 Staff Management
        </a>

        <a
            href="/yotribe-system/app/modules/staff/register.php"
            class="nav-link"
        >
            ➕ Register Staff
        </a>

    <?php endif; ?>

    <!-- ================================================= -->
    <!-- ACCOUNT -->
    <!-- ================================================= -->

    <div class="nav-title mt-3">
        Account
    </div>

    <a
        href="/yotribe-system/app/modules/profile/index.php"
        class="nav-link"
    >
        👤 My Profile
    </a>

    <a
        href="/yotribe-system/app/auth/logout.php"
        class="nav-link text-danger"
    >
        🚪 Logout
    </a>

</div>

<div class="main">