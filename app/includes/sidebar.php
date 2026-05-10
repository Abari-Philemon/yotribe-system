<?php
require_once __DIR__ . '/../helpers/permission.php';
?>
<div class="sidebar" id="sidebar">

    <div class="text-center mb-3">
        <img src="/yotribe-system/public/uploads/logo8.png" class="img-fluid mb-2" style="max-height:70px">
        <div class="fw-bold"><?= htmlspecialchars($farm_name ?? '') ?></div>
        <small><?= $farm_size ?? '' ?> Farm</small>
    </div>

    <div class="quick-box">
        Feed: <strong><?= number_format($total_feed ?? 0,0) ?>kg</strong><br>
        Biomass: <strong><?= number_format($total_biomass ?? 0,0) ?>kg</strong>
    </div>

    <?php $current = basename($_SERVER['PHP_SELF']); ?>

    <div class="nav-title">Overview</div>
    <a href="/yotribe-system/app/modules/dashboard/index.php" class="nav-link <?= $current=='index.php'?'active':'' ?>">📊 Dashboard</a>

    <div class="nav-title">Operations</div>
    <a href="/yotribe-system/app/modules/feeding/index.php" class="nav-link">🍽 Feeding</a>
    <a href="/yotribe-system/app/modules/stocking/index.php" class="nav-link">🐟 Stocking</a>
    <a href="/yotribe-system/app/modules/ponds/index.php" class="nav-link">🏞 Ponds</a>
    <a href="/yotribe-system/app/modules/mortality/index.php" class="nav-link">☠ Mortality</a>
    <a href="/yotribe-system/app/modules/growth/index.php" class="nav-link">📈 Growth</a>

    <div class="nav-title">Feed System</div>
    <a href="/yotribe-system/app/modules/feed_store/index.php" class="nav-link">📦 Feed Store</a>

    <div class="nav-title">Production</div>
    <a href="/yotribe-system/app/modules/hatchery/index.php" class="nav-link">🥚 Hatchery</a>
    <a href="/yotribe-system/app/modules/maggot/index.php" class="nav-link">🪱 Maggot</a>

    <div class="nav-title">Finance</div>
    <a href="/yotribe-system/app/modules/finance/index.php" class="nav-link">💰 Finance</a>

    <div class="nav-title">System</div>
    <a href="/yotribe-system/app/modules/reports/index.php" class="nav-link">📑 Reports</a>
    <a href="/yotribe-system/app/modules/water/index.php" class="nav-link">💧 Water</a>

    <a href="/yotribe-system/app/auth/logout.php" class="nav-link text-danger mt-3">🚪 Logout</a>

</div>

<div class="main">
    