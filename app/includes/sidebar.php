<?php
require_once __DIR__ . '/../helpers/permission.php';
require_once __DIR__ . 'footer.php';
    /**
     * FARM CONTEXT (SECURE)
     */
    $farm_id = farm_id();
    /* FETCH FARM DETAILS
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

           <!-- HERO HEADER -->
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">

        <div>
            <h2 class="mb-0 fw-bold"><?= htmlspecialchars($farm_name) ?></h2>
            <small class="text-muted">
                <?= htmlspecialchars($farm_location) ?> • <?= $farm_size ?> Farm
            </small>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <select id="farmSwitcher" class="form-select form-select-sm shadow-sm"></select>
        </div>

    </div>