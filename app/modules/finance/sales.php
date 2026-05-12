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

        $farm_id = $_POST['farm_id'];
        $pond_id = $_POST['pond_id'];
        $qty = (float)$_POST['quantity'];
        $price = (float)$_POST['unit_price'];
        $total = $qty * $price;

        // Reduce fish inventory
        $stmt = $pdo->prepare("
            UPDATE fish_inventory 
            SET estimated_weight_kg = estimated_weight_kg - ?
            WHERE pond_id = ?
        ");
        $stmt->execute([$qty, $pond_id]);

        // Insert sale
        $stmt = $pdo->prepare("
            INSERT INTO sales 
            (date, farm_id, pond_id, product_type, quantity_kg, unit_price, total_amount,
             payment_method, received_into, customer_name, recorded_by)
            VALUES (CURDATE(), ?, ?, 'table_fish', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $farm_id,
            $pond_id,
            $qty,
            $price,
            $total,
            $_POST['payment_method'],
            $_POST['received_into'],
            $_POST['customer'],
            $_SESSION['staff_id']
        ]);

        $sale_id = $pdo->lastInsertId();

        // Ledger inflow
        $stmt = $pdo->prepare("
            INSERT INTO cash_ledger
            (date, type, source, reference_id, amount, balance_after)
            VALUES (CURDATE(), 'inflow', 'sale', ?, ?, 
                (SELECT IFNULL(SUM(
                    CASE WHEN type='inflow' THEN amount ELSE -amount END
                ),0) + ?
                FROM cash_ledger)
        ");
        $stmt->execute([$sale_id, $total, $total]);

        $pdo->commit();
        $message = "Sale recorded successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}
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
                    <span class="navbar-brand mb-0 h1">Record Sale</span>
                </div>
            </nav>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2 d-none d-md-block">Record Sale</h1>
            </div>

            <?php if($message): ?>
                <div class="alert alert-<?= strpos($message,'Error')!==false ? 'danger' : 'success' ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="card shadow-sm p-4 mb-4">
                <form method="post" class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">Farm ID</label>
                        <input class="form-control" name="farm_id" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Pond ID</label>
                        <input class="form-control" name="pond_id" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Quantity (kg)</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Unit Price</label>
                        <input type="number" step="0.01" name="unit_price" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Customer</label>
                        <input name="customer" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option>cash</option>
                            <option>transfer</option>
                            <option>pos</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Received Into</label>
                        <select name="received_into" class="form-select">
                            <option>cash</option>
                            <option>bank</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Sale</button>
                    </div>

                </form>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
