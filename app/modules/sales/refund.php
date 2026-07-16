<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales Management
 * Refund Sale
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales.refund');

$farm_id = farm_id();

$page_title = 'Refund Sale';

$saleId = (int)($_GET['id'] ?? 0);

if ($saleId <= 0) {

    $_SESSION['error'] = 'Invalid sale selected.';

    header('Location: history.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Load Sale
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    s.*,

    h.harvest_no,

    fb.batch_code,

    fb.species,

    st.full_name

FROM sales s

INNER JOIN harvests h
    ON h.id = s.harvest_id

INNER JOIN fish_batches fb
    ON fb.id = h.fish_batch_id

LEFT JOIN staff st
    ON st.id = s.recorded_by

WHERE

    s.id = ?

AND s.farm_id = ?

LIMIT 1

");

$stmt->execute([

    $saleId,

    $farm_id

]);

$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {

    $_SESSION['error'] = 'Sale not found.';

    header('Location: history.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if ($sale['status'] === 'refunded') {

    $_SESSION['error'] =

        'This sale has already been refunded.';

    header("Location:view.php?id={$saleId}");

    exit;

}

if ($sale['status'] === 'cancelled') {

    $_SESSION['error'] =

        'Cancelled sales cannot be refunded.';

    header("Location:view.php?id={$saleId}");

    exit;

}

if ($sale['status'] !== 'completed') {

    $_SESSION['error'] =

        'Only completed sales can be refunded.';

    header("Location:view.php?id={$saleId}");

    exit;

}
/*
|--------------------------------------------------------------------------
| Sale Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    si.*,

    hp.pond_name

FROM sale_items si

LEFT JOIN harvest_ponds hp
ON hp.id = si.harvest_pond_id

WHERE sale_id = ?

ORDER BY id

");

$stmt->execute([$saleId]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container-fluid py-4">

<div class="card shadow-sm mb-4">

    <div class="card-header bg-danger text-white">

        <h5 class="mb-0">

            Refund Summary

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-3">

                <strong>Sale No</strong>

                <div>

                    <?= htmlspecialchars($sale['sale_no']) ?>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Customer</strong>

                <div>

                    <?= htmlspecialchars(
                        $sale['customer_name']
                        ?: 'Walk-in Customer'
                    ) ?>

                </div>

            </div>

            <div class="col-md-2">

                <strong>Total</strong>

                <div>

                    ₦<?= number_format(
                        $sale['total_amount'],
                        2
                    ) ?>

                </div>

            </div>

            <div class="col-md-2">

                <strong>Paid</strong>

                <div>

                    ₦<?= number_format(
                        $sale['amount_paid'],
                        2
                    ) ?>

                </div>

            </div>

            <div class="col-md-2">

                <strong>Status</strong>

                <div>

                    <span class="badge bg-success">

                        <?= ucfirst($sale['status']) ?>

                    </span>

                </div>

            </div>

        </div>

    </div>

</div>
<div class="card shadow-sm mb-4">

    <div class="card-header">

        Items to be Refunded

    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-sm mb-0">

            <thead>

            <tr>

                <th>Pond</th>

                <th>Product</th>

                <th class="text-end">Fish</th>

                <th class="text-end">Weight</th>

                <th class="text-end">Amount</th>

            </tr>

            </thead>

            <tbody>

            <?php foreach ($items as $item): ?>

                <tr>

                    <td>

                        <?= htmlspecialchars($item['pond_name']) ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($item['product_name']) ?>

                    </td>

                    <td class="text-end">

                        <?= number_format($item['quantity_fish']) ?>

                    </td>

                    <td class="text-end">

                        <?= number_format(
                            $item['quantity_kg'],
                            2
                        ) ?>

                    </td>

                    <td class="text-end">

                        ₦<?= number_format(
                            $item['line_total'],
                            2
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>
<!-- ==========================================================
REFUND AUTHORIZATION
========================================================== -->

<form action="refund_save.php" method="POST" id="refundForm">

    <input
        type="hidden"
        name="sale_id"
        value="<?= $saleId ?>">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrf_token() ?>">

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-warning">

            <h5 class="mb-0">

                Refund Authorization

            </h5>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Refund Date

                    </label>

                    <input
                        type="datetime-local"
                        name="refund_date"
                        class="form-control"
                        value="<?= date('Y-m-d\TH:i') ?>"
                        required>

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Refund Type

                    </label>

                    <select
                        name="refund_type"
                        class="form-select"
                        required>

                        <option value="full">

                            Full Refund

                        </option>

                        <!-- Reserved for future -->
                        <!--
                        <option value="partial">

                            Partial Refund

                        </option>
                        -->

                    </select>

                </div>

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Refund Reason

                </label>

                <select
                    name="refund_reason"
                    class="form-select"
                    required>

                    <option value="">

                        -- Select Reason --

                    </option>

                    <option value="customer_request">

                        Customer Request

                    </option>

                    <option value="wrong_pricing">

                        Wrong Pricing

                    </option>

                    <option value="duplicate_sale">

                        Duplicate Sale

                    </option>

                    <option value="system_error">

                        System Error

                    </option>

                    <option value="damaged_product">

                        Damaged Product

                    </option>

                    <option value="quality_issue">

                        Quality Issue

                    </option>

                    <option value="other">

                        Other

                    </option>

                </select>

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Refund Notes

                </label>

                <textarea
                    name="refund_notes"
                    rows="4"
                    class="form-control"
                    placeholder="Provide detailed explanation..."
                    required></textarea>

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Approved By

                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= htmlspecialchars($_SESSION['full_name'] ?? '') ?>"
                    readonly>

            </div>

            <div class="form-check mb-4">

                <input
                    class="form-check-input"
                    type="checkbox"
                    id="confirmRefund"
                    required>

                <label
                    class="form-check-label"
                    for="confirmRefund">

                    I confirm that this refund has been approved and I understand
                    that inventory, sales records, and financial records will be updated.

                </label>

            </div>

        </div>

    </div>
    <!-- ==========================================================
REFUND IMPACT
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-danger text-white">

        <h5 class="mb-0">

            Refund Impact

        </h5>

    </div>

    <div class="card-body">

        <div class="alert alert-warning">

            <strong>

                This operation will perform the following actions:

            </strong>

        </div>

        <ul class="mb-0">

            <li>

                Mark the sale as <strong>Refunded</strong>.

            </li>

            <li>

                Restore all sold fish to inventory.

            </li>

            <li>

                Update harvest stock balances.

            </li>

            <li>

                Reverse outstanding balances where applicable.

            </li>

            <li>

                Create audit trail records.

            </li>

            <li>

                Queue synchronization for remote systems.

            </li>

            <li>

                This action cannot be automatically reversed.

            </li>

        </ul>

    </div>

</div>
<div class="d-flex justify-content-end mb-4">

    <a
        href="view.php?id=<?= $saleId ?>"
        class="btn btn-secondary me-2">

        Cancel

    </a>

    <button
        type="submit"
        class="btn btn-danger">

        <i class="bi bi-arrow-counterclockwise"></i>

        Process Refund

    </button>

</div>

</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>