<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');
require_role(['super_admin','storekeeper','manager','owner']);

$stmt = $pdo->query("SELECT * FROM feed_store ORDER BY updated_at DESC");
$feeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feed Store – Current Stock | Yotribe Agro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/yotribe-system/public/css/custom.css">
<style>
.sidebar { min-height: 100vh; }
.table thead th { vertical-align: middle; }
</style>
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav id="sidebar" class="col-md-2 col-12 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/dashboard/index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/feeding/index.php">Feeding</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Feed Store</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/reports/index.php">Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/export/pdf.php">Export PDF</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 ms-sm-auto px-md-4">

            <!-- Hamburger Toggle for small screens -->
            <nav class="navbar navbar-light bg-light d-md-none mb-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        ☰ Menu
                    </button>
                    <span class="navbar-brand mb-0 h1">Feed Store</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Feed Store – Current Stock</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="receive.php" class="btn btn-success btn-sm me-2">Receive Feed</a>
                    <a href="issue.php" class="btn btn-primary btn-sm me-2">Issue Feed</a>
                    <a href="logs.php" class="btn btn-secondary btn-sm">View Logs</a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Feed Type</th>
                                <th>Batch No</th>
                                <th>Quantity (kg)</th>
                                <th>Cost/kg ($)</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($feeds): ?>
                                <?php foreach ($feeds as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['feed_type']) ?></td>
                                    <td><?= htmlspecialchars($f['batch_no']) ?></td>
                                    <td><?= number_format($f['quantity_kg'],2) ?></td>
                                    <td><?= number_format($f['cost_per_kg'],2) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($f['updated_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No feed records found.</td>
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
