<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('reports');

$targets = $pdo->query("SELECT * FROM quarterly_targets")->fetchAll();
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
                    <span class="navbar-brand mb-0 h1">Quarterly Targets</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Quarterly Targets</h1>
            </div>

            <!-- Targets Table -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-warning text-white">
                    Quarterly Targets Overview
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Year</th>
                                <th>Quarter</th>
                                <th>Category</th>
                                <th>Target Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($targets): ?>
                                <?php foreach($targets as $t): ?>
                                <tr>
                                    <td><?= htmlspecialchars($t['year']) ?></td>
                                    <td><?= htmlspecialchars($t['quarter']) ?></td>
                                    <td><?= htmlspecialchars($t['category']) ?></td>
                                    <td><?= number_format($t['target_value'],2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No quarterly targets found.</td>
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
