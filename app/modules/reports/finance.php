<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('reports');

$sales = $pdo->query("SELECT date, total_amount FROM sales ORDER BY date DESC")->fetchAll();
$expenses = $pdo->query("SELECT date, category, amount FROM expenses ORDER BY date DESC")->fetchAll();
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
                    <span class="navbar-brand mb-0 h1">Financial Report</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Financial Report</h1>
            </div>

            <!-- Sales Card -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-success text-white">
                    Sales
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Total Amount ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($sales): ?>
                                <?php foreach($sales as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['date']) ?></td>
                                    <td><?= number_format($s['total_amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">No sales records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Expenses Card -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-danger text-white">
                    Expenses
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Amount ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($expenses): ?>
                                <?php foreach($expenses as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars($e['date']) ?></td>
                                    <td><?= htmlspecialchars($e['category']) ?></td>
                                    <td><?= number_format($e['amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No expense records found.</td>
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
