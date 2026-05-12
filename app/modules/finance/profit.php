<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('finance');

$sales = $pdo->query("SELECT SUM(total_amount) FROM sales")->fetchColumn() ?: 0;
$expenses = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn() ?: 0;

$profit = $sales - $expenses;
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
                    <span class="navbar-brand mb-0 h1">Profit Summary</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Profit Summary</h1>
            </div>

            <div class="card shadow-sm mb-4 p-4">
                <p><strong>Total Sales:</strong> <?= number_format($sales,2) ?> </p>
                <p><strong>Total Expenses:</strong> <?= number_format($expenses,2) ?> </p>
                <h3 class="text-success">Net Profit: <?= number_format($profit,2) ?></h3>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
