<?php

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';

/**
 * =========================================================
 * MODULE ACCESS
 * =========================================================
 */

require_permission('finance');

/**
 * =========================================================
 * FARM CONTEXT
 * =========================================================
 */

$farm_id = farm_id();

/**
 * =========================================================
 * PAGE TITLE
 * =========================================================
 */

$page_title = "Record Sale";

/**
 * =========================================================
 * CSRF TOKEN
 * =========================================================
 */

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$alert = 'success';

/**
 * =========================================================
 * LOAD PONDS
 * =========================================================
 */

$stmt = $pdo->prepare("
    SELECT
        id,
        pond_code
    FROM ponds_tanks
    WHERE farm_id = ?
    ORDER BY pond_code ASC
");

$stmt->execute([$farm_id]);

$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * =========================================================
 * HANDLE SUBMIT
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * CSRF
     */

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token');
    }

    try {

        $pdo->beginTransaction();

        /**
         * CLEAN INPUTS
         */

        $pond_id = (int) ($_POST['pond_id'] ?? 0);

        $qty = (float) ($_POST['quantity'] ?? 0);

        $price = (float) ($_POST['unit_price'] ?? 0);

        $customer = trim($_POST['customer'] ?? '');

        $payment_method = trim($_POST['payment_method'] ?? '');

        $received_into = trim($_POST['received_into'] ?? '');

        $total = $qty * $price;

        /**
         * VALIDATION
         */

        if ($pond_id <= 0) {
            throw new Exception("Invalid pond selected.");
        }

        if ($qty <= 0) {
            throw new Exception("Quantity must be greater than zero.");
        }

        if ($price <= 0) {
            throw new Exception("Unit price must be greater than zero.");
        }

        /**
         * VERIFY INVENTORY
         */

        $stmt = $pdo->prepare("
            SELECT estimated_weight_kg
            FROM fish_inventory
            WHERE farm_id = ?
            AND pond_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $farm_id,
            $pond_id
        ]);

        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inventory) {
            throw new Exception("Fish inventory not found.");
        }

        $available_stock = (float) $inventory['estimated_weight_kg'];

        if ($qty > $available_stock) {
            throw new Exception(
                "Insufficient fish stock. Available: "
                . number_format($available_stock, 2)
                . " kg"
            );
        }

        /**
         * REDUCE INVENTORY
         */

        $stmt = $pdo->prepare("
            UPDATE fish_inventory
            SET estimated_weight_kg =
                estimated_weight_kg - ?
            WHERE farm_id = ?
            AND pond_id = ?
        ");

        $stmt->execute([
            $qty,
            $farm_id,
            $pond_id
        ]);

        /**
         * INSERT SALE
         */

        $stmt = $pdo->prepare("
            INSERT INTO sales
            (
                date,
                farm_id,
                pond_id,
                product_type,
                quantity_kg,
                unit_price,
                total_amount,
                payment_method,
                received_into,
                customer_name,
                recorded_by
            )
            VALUES
            (
                CURDATE(),
                ?,
                ?,
                'table_fish',
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $farm_id,
            $pond_id,
            $qty,
            $price,
            $total,
            $payment_method,
            $received_into,
            $customer,
            $_SESSION['staff_id']
        ]);

        $sale_id = $pdo->lastInsertId();

        /**
         * =========================================================
         * LEDGER ENTRY
         * =========================================================
         */

        $stmt = $pdo->prepare("
            INSERT INTO cash_ledger
            (
                date,
                type,
                source,
                reference_id,
                amount,
                balance_after
            )
            VALUES
            (
                CURDATE(),
                'inflow',
                'sale',
                ?,
                ?,
                (
                    SELECT
                        IFNULL(
                            SUM(
                                CASE
                                    WHEN type = 'inflow'
                                    THEN amount
                                    ELSE -amount
                                END
                            ),
                            0
                        ) + ?
                    FROM cash_ledger
                )
            )
        ");

        $stmt->execute([
            $sale_id,
            $total,
            $total
        ]);

        /**
         * =========================================================
         * SUCCESS
         * =========================================================
         */

        $pdo->commit();

        $message = "Sale recorded successfully.";

        $alert = 'success';

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = "Error: " . $e->getMessage();

        $alert = 'danger';
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h3 class="mb-1">
            💰 Record Fish Sale
        </h3>

        <small class="text-muted">
            Record harvested fish sales and cash inflow
        </small>

    </div>

</div>

<?php if($message): ?>

<div class="alert alert-<?= $alert ?>">
    <?= htmlspecialchars($message) ?>
</div>

<?php endif; ?>

<div class="card shadow-sm border-0">

    <div class="card-body p-4">

        <form method="POST" class="row g-3">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= $_SESSION['csrf_token'] ?>"
            >

            <!-- POND -->
            <div class="col-md-6">

                <label class="form-label fw-semibold">
                    Pond
                </label>

                <select
                    name="pond_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Pond
                    </option>

                    <?php foreach($ponds as $pond): ?>

                        <option value="<?= $pond['id'] ?>">

                            <?= htmlspecialchars($pond['pond_code']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- QUANTITY -->
            <div class="col-md-6">

                <label class="form-label fw-semibold">
                    Quantity (kg)
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="quantity"
                    class="form-control"
                    required
                >

            </div>

            <!-- UNIT PRICE -->
            <div class="col-md-6">

                <label class="form-label fw-semibold">
                    Unit Price (₦)
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="unit_price"
                    class="form-control"
                    required
                >

            </div>

            <!-- CUSTOMER -->
            <div class="col-md-6">

                <label class="form-label fw-semibold">
                    Customer Name
                </label>

                <input
                    type="text"
                    name="customer"
                    class="form-control"
                >

            </div>

            <!-- PAYMENT METHOD -->
            <div class="col-md-6">

                <label class="form-label fw-semibold">
                    Payment Method
                </label>

                <select
                    name="payment_method"
                    class="form-select"
                    required
                >

                    <option value="cash">Cash</option>
                    <option value="transfer">Transfer</option>
                    <option value="pos">POS</option>

                </select>

            </div>

            <!-- RECEIVED INTO -->
            <div class="col-md-6">

                <label class="form-label fw-semibold">
                    Received Into
                </label>

                <select
                    name="received_into"
                    class="form-select"
                    required
                >

                    <option value="cash">Cash Wallet</option>
                    <option value="bank">Bank Account</option>

                </select>

            </div>

            <div class="col-12 mt-3">

                <button
                    type="submit"
                    class="btn btn-primary px-4"
                >
                    Save Sale
                </button>

            </div>

        </form>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>