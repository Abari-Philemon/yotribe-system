<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('finance');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Insert expense
        $stmt = $pdo->prepare("
            INSERT INTO expenses
            (date, farm_id, category, description, amount, payment_method, approved_by)
            VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['farm_id'],
            $_POST['category'],
            $_POST['description'],
            $_POST['amount'],
            $_POST['payment_method'],
            $_SESSION['staff_id']
        ]);

        $expense_id = $pdo->lastInsertId();

        // 2. Calculate current balance
        $current_balance = $pdo->query("
            SELECT IFNULL(SUM(CASE WHEN type='inflow' THEN amount ELSE -amount END),0)
            FROM cash_ledger
        ")->fetchColumn();

        $new_balance = $current_balance - $_POST['amount'];

        // 3. Insert into cash_ledger
        $stmt = $pdo->prepare("
            INSERT INTO cash_ledger
            (date, type, source, reference_id, amount, balance_after)
            VALUES (CURDATE(), 'outflow', 'expense', ?, ?, ?)
        ");
        $stmt->execute([$expense_id, $_POST['amount'], $new_balance]);

        $pdo->commit();
        $message = "Expense recorded successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Record Expense | Yotribe Agro</title>
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

            <!-- Hamburger for mobile -->
            <nav class="navbar navbar-light bg-light d-md-none mb-3">
                <div class="container-fluid">
                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar">
                        ☰ Menu
                    </button>
                    <span class="navbar-brand mb-0 h1">Record Expense</span>
                </div>
            </nav>

            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Record Expense</h1>
            </div>

            <?php if($message): ?>
                <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="post" class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">Farm ID</label>
                            <input name="farm_id" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <input name="category" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-success">Save Expense</button>
                            <a href="ledger.php" class="btn btn-secondary">View Ledger</a>
                        </div>

                    </form>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
