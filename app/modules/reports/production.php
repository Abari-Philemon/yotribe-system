<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('reports');

$data = $pdo->query("
    SELECT p.pond_code,
           fi.estimated_weight_kg,
           IFNULL(SUM(fl.quantity_kg),0) AS total_feed
    FROM ponds_tanks p
    LEFT JOIN fish_inventory fi ON fi.pond_id=p.id
    LEFT JOIN feeding_logs fl ON fl.pond_id=p.id
    GROUP BY p.id
")->fetchAll();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>


        <!-- Main Content -->
        <main class="col-md-10 ms-sm-auto px-md-4">

            <!-- Hamburger for mobile -->
            <nav class="navbar navbar-light bg-light d-md-none mb-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        ☰ Menu
                    </button>
                    <span class="navbar-brand mb-0 h1">Production Report</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Production Report</h1>
            </div>

            <!-- Production Table -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-info text-white">
                    Production Overview
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Pond</th>
                                <th>Biomass (kg)</th>
                                <th>Total Feed Used (kg)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($data): ?>
                                <?php foreach($data as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['pond_code']) ?></td>
                                    <td><?= number_format($d['estimated_weight_kg'],2) ?></td>
                                    <td><?= number_format($d['total_feed'],2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No production data found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
