<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../middleware/farm_context.php';
require_once __DIR__ . '/../../helpers/permission.php';

/**
 * MODULE ACCESS
 */
require_permission('reports');

/**
 * FARM CONTEXT
 */
$farm_id = farm_id();

/**
 * PAGE TITLE
 */
$page_title = "Reports Dashboard";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>


        <!-- Main Content -->
        <main class="col-md-10 ms-sm-auto px-md-4">

            <!-- Hamburger for small screens -->
            <nav class="navbar navbar-light bg-light d-md-none mb-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        ☰ Menu
                    </button>
                    <span class="navbar-brand mb-0 h1">Reports Dashboard</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Reports Dashboard</h1>
            </div>

            <div class="row g-3">

                <div class="col-md-3 col-6">
                    <a href="daily.php" class="text-decoration-none">
                        <div class="card shadow-sm text-center bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Daily Operations</h5>
                                <p class="card-text">View daily farm activities and logs</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-6">
                    <a href="finance.php" class="text-decoration-none">
                        <div class="card shadow-sm text-center bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Financial Report</h5>
                                <p class="card-text">View income, expenses, and profit</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-6">
                    <a href="production.php" class="text-decoration-none">
                        <div class="card shadow-sm text-center bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Production Report</h5>
                                <p class="card-text">Track fish growth, feed, and output</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-6">
                    <a href="quarterly.php" class="text-decoration-none">
                        <div class="card shadow-sm text-center bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Quarterly Targets</h5>
                                <p class="card-text">Check progress against quarterly goals</p>
                            </div>
                        </div>
                    </a>
                </div>

            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
