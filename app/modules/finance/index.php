<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../middleware/farm_context.php';
require_once __DIR__ . '/../../helpers/permission.php';

requireModuleAccess('module_name');

$farm_id = farm_id();

authorize('finance');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finance Dashboard | Yotribe Agro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/yotribe-system/public/css/custom.css">
</head>
<body>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav id="sidebar" class="col-md-2 col-12 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/dashboard/index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Finance</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/feeding/index.php">Feeding</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/feed_store/index.php">Feed Store</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/hatchery/index.php">Hatchery</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/mortality/index.php">Mortality</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/maggot/index.php">Maggot Production</a></li>
                    <li class="nav-item"><a class="nav-link" href="/yotribe-system/app/modules/water/index.php">Water Quality</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 ms-sm-auto px-md-4">

            <!-- Hamburger for small screens -->
            <nav class="navbar navbar-light bg-light d-md-none mb-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        ☰ Menu
                    </button>
                    <span class="navbar-brand mb-0 h1">Finance Dashboard</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Finance Dashboard</h1>
            </div>

            <div class="row g-3">

                <div class="col-md-3 col-6">
                    <a href="expenses.php" class="text-decoration-none">
                        <div class="card shadow-sm text-center bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Expenses</h5>
                                <p class="card-text">Record and view all farm expenses</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-6">
                    <a href="ledger.php" class="text-decoration-none">
                        <div class="card shadow-sm text-center bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Ledger</h5>
                                <p class="card-text">Track cash inflow and outflow</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-6">
                    <a href="profit.php" class="text-decoration-none">
                        <div class="card shadow-sm text-center bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Profit</h5>
                                <p class="card-text">Monitor farm profitability</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-3 col-6">
                    <a href="sales.php" class="text-decoration-none">
                        <div class="card shadow-sm text-center bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Sales</h5>
                                <p class="card-text">View sales records and trends</p>
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
