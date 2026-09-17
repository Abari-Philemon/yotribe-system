<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('finance');

$rows = $pdo->query("SELECT * FROM cash_ledger ORDER BY id ASC")->fetchAll();
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
                    <span class="navbar-brand mb-0 h1">Cash Ledger</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Cash Ledger</h1>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-body table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Source</th>
                                <th>Amount</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($rows): ?>
                                <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= $r['date'] ?></td>
                                    <td><?= ucfirst($r['type']) ?></td>
                                    <td><?= ucfirst($r['source']) ?></td>
                                    <td><?= number_format($r['amount'],2) ?></td>
                                    <td><?= number_format($r['balance_after'],2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No ledger records found.</td>
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
