<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * View Sale
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales');

$farm_id = farm_id();

$page_title = 'View Sale';

/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/

$saleId = (int)($_GET['id'] ?? 0);

if ($saleId <= 0) {

    $_SESSION['error'] = 'Invalid sale selected.';

    header('Location: dashboard.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Load Sale Header
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    s.*,

    h.harvest_no,

    fb.batch_code,

    fb.species,

    st.full_name AS staff_name

FROM sales s

INNER JOIN harvests h
    ON h.id=s.harvest_id

INNER JOIN fish_batches fb
    ON fb.id=h.fish_batch_id

LEFT JOIN staff st
    ON st.id=s.recorded_by

WHERE

    s.id=?

AND s.farm_id=?

LIMIT 1

");

$stmt->execute([

    $saleId,

    $farm_id

]);

$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {

    $_SESSION['error'] = 'Sale record not found.';

    header('Location: dashboard.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Load Sale Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    si.*,

    hp.pond_name

FROM sale_items si

LEFT JOIN harvest_ponds hp
    ON hp.id=si.harvest_pond_id

WHERE

    si.sale_id=?

ORDER BY si.id

");

$stmt->execute([$saleId]);

$saleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load Payments
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT *

FROM sale_payments

WHERE sale_id=?

ORDER BY payment_date

");

$stmt->execute([$saleId]);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load Receipt
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT *

FROM sale_receipts

WHERE sale_id=?

LIMIT 1

");

$stmt->execute([$saleId]);

$receipt = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load Audit Log
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

action,

description,

recorded_by,

created_at

FROM sale_logs

WHERE sale_id=?

ORDER BY created_at DESC

");

$stmt->execute([$saleId]);

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Sync Status
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

status,

retry_count,

last_attempt,

synced_at

FROM sales_sync_queue

WHERE sale_uuid=?

LIMIT 1

");

$stmt->execute([

    $sale['uuid']

]);

$sync = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h3>

Sale Details

</h3>

<small class="text-muted">

<?= htmlspecialchars($sale['sale_no']) ?>

</small>

</div>

<div>

<a
href="dashboard.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Back

</a>

</div>

</div>
<!-- ==========================================================
SALE INFORMATION
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-success text-white">

        <h5 class="mb-0">

            Sale Information

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-3 mb-3">

                <strong>Sale Number</strong>

                <div>

                    <?= htmlspecialchars($sale['sale_no']) ?>

                </div>

            </div>

            <div class="col-md-3 mb-3">

                <strong>Sale Date</strong>

                <div>

                    <?= date('d M Y H:i', strtotime($sale['sale_date'])) ?>

                </div>

            </div>

            <div class="col-md-3 mb-3">

                <strong>Sale Type</strong>

                <div>

                    <?= ucwords(str_replace('_', ' ', $sale['sale_type'])) ?>

                </div>

            </div>

            <div class="col-md-3 mb-3">

                <strong>Status</strong>

                <div>

                    <?php

                    $badge = match ($sale['status']) {

                        'completed' => 'success',

                        'draft' => 'warning',

                        'cancelled' => 'danger',

                        'refunded' => 'secondary',

                        default => 'primary'

                    };

                    ?>

                    <span class="badge bg-<?= $badge ?>">

                        <?= ucfirst($sale['status']) ?>

                    </span>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
CUSTOMER INFORMATION
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <h5 class="mb-0">

            Customer Information

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-4">

                <strong>Name</strong>

                <div>

                    <?= htmlspecialchars(
                        $sale['customer_name'] ?: 'Walk-in Customer'
                    ) ?>

                </div>

            </div>

            <div class="col-md-4">

                <strong>Phone</strong>

                <div>

                    <?= htmlspecialchars(
                        $sale['customer_phone'] ?: '-'
                    ) ?>

                </div>

            </div>

            <div class="col-md-4">

                <strong>Address</strong>

                <div>

                    <?= htmlspecialchars(
                        $sale['customer_address'] ?: '-'
                    ) ?>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
HARVEST INFORMATION
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-info text-white">

        <h5 class="mb-0">

            Harvest Information

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-3">

                <strong>Harvest No</strong>

                <div>

                    <?= htmlspecialchars($sale['harvest_no']) ?>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Batch</strong>

                <div>

                    <?= htmlspecialchars($sale['batch_code']) ?>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Species</strong>

                <div>

                    <?= htmlspecialchars($sale['species']) ?>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Recorded By</strong>

                <div>

                    <?= htmlspecialchars($sale['staff_name']) ?>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
FINANCIAL SUMMARY
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-warning">

        <h5 class="mb-0">

            Financial Summary

        </h5>

    </div>

    <div class="card-body">

        <div class="row text-center">

            <div class="col-md-3">

                <h6>

                    Subtotal

                </h6>

                <h4>

                    ₦<?= number_format($sale['subtotal'],2) ?>

                </h4>

            </div>

            <div class="col-md-3">

                <h6>

                    Discount

                </h6>

                <h4>

                    ₦<?= number_format($sale['discount'],2) ?>

                </h4>

            </div>

            <div class="col-md-3">

                <h6>

                    Amount Paid

                </h6>

                <h4 class="text-success">

                    ₦<?= number_format($sale['amount_paid'],2) ?>

                </h4>

            </div>

            <div class="col-md-3">

                <h6>

                    Balance

                </h6>

                <h4 class="text-danger">

                    ₦<?= number_format($sale['balance'],2) ?>

                </h4>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
QUICK ACTIONS
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <h5 class="mb-0">

            Quick Actions

        </h5>

    </div>

    <div class="card-body">

        <div class="d-flex flex-wrap gap-2">

            <a
                href="print.php?id=<?= $saleId ?>"
                class="btn btn-primary">

                <i class="bi bi-printer"></i>

                Print Invoice

            </a>

            <a
                href="receipt.php?id=<?= $saleId ?>"
                class="btn btn-success">

                <i class="bi bi-receipt"></i>

                Receipt

            </a>

            <a
                href="history.php"
                class="btn btn-secondary">

                <i class="bi bi-clock-history"></i>

                Sales History

            </a>

            <?php if ($sale['status'] === 'completed'): ?>

                <a
                    href="refund.php?id=<?= $saleId ?>"
                    class="btn btn-danger">

                    <i class="bi bi-arrow-counterclockwise"></i>

                    Refund

                </a>

            <?php endif; ?>

        </div>

    </div>

</div>
<!-- ==========================================================
SALE ITEMS
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <div class="d-flex justify-content-between align-items-center">

            <h5 class="mb-0">

                Sale Items

            </h5>

            <span class="badge bg-light text-dark">

                <?= count($saleItems) ?> Item(s)

            </span>

        </div>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle mb-0">

                <thead class="table-light">

                <tr>

                    <th>#</th>

                    <th>Pond</th>

                    <th>Product</th>

                    <th class="text-end">Fish</th>

                    <th class="text-end">Weight (Kg)</th>

                    <th class="text-end">Avg Weight</th>

                    <th class="text-end">Unit Price</th>

                    <th class="text-end">Line Total</th>

                </tr>

                </thead>

                <tbody>

                <?php

                $totalFish = 0;
                $totalWeight = 0;
                $grandLineTotal = 0;

                ?>

                <?php if (empty($saleItems)): ?>

                    <tr>

                        <td colspan="8"
                            class="text-center text-muted py-4">

                            No sale items found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($saleItems as $index => $item): ?>

                        <?php

                        $totalFish += (int)$item['quantity_fish'];

                        $totalWeight += (float)$item['quantity_kg'];

                        $grandLineTotal += (float)$item['line_total'];

                        ?>

                        <tr>

                            <td>

                                <?= $index + 1 ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $item['pond_name'] ?? 'Unknown Pond'
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $item['product_name']
                                ) ?>

                            </td>

                            <td class="text-end">

                                <?= number_format(
                                    $item['quantity_fish']
                                ) ?>

                            </td>

                            <td class="text-end">

                                <?= number_format(
                                    $item['quantity_kg'],
                                    2
                                ) ?>

                            </td>

                            <td class="text-end">

                                <?= number_format(
                                    $item['average_weight_kg'],
                                    3
                                ) ?>

                            </td>

                            <td class="text-end">

                                ₦<?= number_format(
                                    $item['unit_price'],
                                    2
                                ) ?>

                            </td>

                            <td class="text-end fw-bold">

                                ₦<?= number_format(
                                    $item['line_total'],
                                    2
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

                <?php if (!empty($saleItems)): ?>

                <tfoot class="table-light">

                <tr>

                    <th colspan="3"
                        class="text-end">

                        TOTAL

                    </th>

                    <th class="text-end">

                        <?= number_format($totalFish) ?>

                    </th>

                    <th class="text-end">

                        <?= number_format(
                            $totalWeight,
                            2
                        ) ?>

                    </th>

                    <th></th>

                    <th></th>

                    <th class="text-end">

                        ₦<?= number_format(
                            $grandLineTotal,
                            2
                        ) ?>

                    </th>

                </tr>

                </tfoot>

                <?php endif; ?>

            </table>

        </div>

    </div>

</div>
<!-- ==========================================================
PAYMENT HISTORY
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-success text-white">

        <div class="d-flex justify-content-between align-items-center">

            <h5 class="mb-0">

                Payment History

            </h5>

            <span class="badge bg-light text-dark">

                <?= count($payments) ?> Payment(s)

            </span>

        </div>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-bordered table-hover mb-0">

                <thead class="table-light">

                <tr>

                    <th>#</th>
                    <th>Payment No</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>

                </tr>

                </thead>

                <tbody>
                    <?php

                    $paymentTotal = 0;

                    ?>

                    <tbody>

                    <?php if (empty($payments)): ?>

                        <tr>

                            <td colspan="7"
                                class="text-center text-muted py-4">

                                No payment records found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($payments as $index => $payment): ?>

                            <?php

                            $paymentTotal += (float)$payment['amount'];

                            ?>

                            <tr>

                                ...
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                <?php if (empty($payments)): ?>

                    <tr>

                        <td colspan="7"
                            class="text-center text-muted py-4">

                            No payment records found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php

                    $paymentTotal = 0;

                    foreach ($payments as $index => $payment):

                        $paymentTotal += (float)$payment['amount'];

                    ?>

                    <tr>

                        <td><?= $index + 1 ?></td>

                        <td>

                            <?= htmlspecialchars($payment['payment_no']) ?>

                        </td>

                        <td>

                            <?= date(
                                'd M Y H:i',
                                strtotime($payment['payment_date'])
                            ) ?>

                        </td>

                        <td>

                            <?= ucfirst(
                                htmlspecialchars(
                                    $payment['payment_method']
                                )
                            ) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $payment['reference_no'] ?: '-'
                            ) ?>

                        </td>

                        <td class="text-end">

                            ₦<?= number_format(
                                $payment['amount'],
                                2
                            ) ?>

                        </td>

                        <td>

                            <span class="badge bg-success">

                                <?= ucfirst(
                                    $payment['payment_status']
                                ) ?>

                            </span>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

                <?php if (!empty($payments)): ?>

                <tfoot class="table-light">

                <tr>

                    <th colspan="5" class="text-end">

                        Total Paid

                    </th>

                    <th class="text-end">

                        ₦<?= number_format($paymentTotal, 2) ?>

                    </th>

                    <th></th>

                </tr>

                </tfoot>

                <?php endif; ?>

            </table>

        </div>

    </div>

</div>

<!-- ==========================================================
RECEIPT INFORMATION
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-info text-white">

        <h5 class="mb-0">

            Receipt Information

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-3">

                <strong>Receipt No</strong>

                <div>

                    <?= htmlspecialchars(
                        $receipt['receipt_no'] ?? '-'
                    ) ?>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Status</strong>

                <div>

                    <span class="badge bg-primary">

                        <?= ucfirst(
                            $receipt['receipt_status'] ?? 'Pending'
                        ) ?>

                    </span>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Print Count</strong>

                <div>

                    <?= (int)($receipt['print_count'] ?? 0) ?>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Receipt Date</strong>

                <div>

                    <?= !empty($receipt['receipt_date'])
                        ? date(
                            'd M Y H:i',
                            strtotime($receipt['receipt_date'])
                        )
                        : '-'
                    ?>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
OFFLINE SYNCHRONIZATION
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-dark text-white">

        <h5 class="mb-0">

            Synchronization Status

        </h5>

    </div>

    <div class="card-body">

        <?php

        $syncBadge = match ($sync['status'] ?? 'pending') {

            'synced'  => 'success',

            'failed'  => 'danger',

            'pending' => 'warning',

            default   => 'secondary'

        };

        ?>

        <div class="row">

            <div class="col-md-3">

                <strong>Status</strong>

                <div>

                    <span class="badge bg-<?= $syncBadge ?>">

                        <?= ucfirst(
                            $sync['status'] ?? 'Pending'
                        ) ?>

                    </span>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Retry Count</strong>

                <div>

                    <?= (int)($sync['retry_count'] ?? 0) ?>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Last Attempt</strong>

                <div>

                    <?= !empty($sync['last_attempt'])
                        ? date(
                            'd M Y H:i',
                            strtotime($sync['last_attempt'])
                        )
                        : '-'
                    ?>

                </div>

            </div>

            <div class="col-md-3">

                <strong>Synced At</strong>

                <div>

                    <?= !empty($sync['synced_at'])
                        ? date(
                            'd M Y H:i',
                            strtotime($sync['synced_at'])
                        )
                        : '-'
                    ?>

                </div>

            </div>

        </div>

    </div>

</div>
<!-- ==========================================================
AUDIT TIMELINE
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-secondary text-white">

        <h5 class="mb-0">

            Audit Timeline

        </h5>

    </div>

    <div class="card-body">

        <?php if (empty($logs)): ?>

            <div class="alert alert-light mb-0">

                No audit records found.

            </div>

        <?php else: ?>

            <div class="timeline">

                <?php foreach ($logs as $log): ?>

                    <?php

                    $badge = match ($log['action']) {

                        'create' => 'success',

                        'update' => 'primary',

                        'payment' => 'info',

                        'print' => 'secondary',

                        'refund' => 'warning',

                        'cancel' => 'danger',

                        default => 'dark'

                    };

                    ?>

                    <div class="border-start border-4 border-<?= $badge ?> ps-3 mb-4">

                        <div class="d-flex justify-content-between">

                            <strong>

                                <?= ucfirst($log['action']) ?>

                            </strong>

                            <small class="text-muted">

                                <?= date(
                                    'd M Y H:i:s',
                                    strtotime($log['created_at'])
                                ) ?>

                            </small>

                        </div>

                        <div class="mt-2">

                            <?= htmlspecialchars($log['description']) ?>

                        </div>

                        <small class="text-muted">

                            Staff ID:

                            <?= htmlspecialchars(
                                (string)$log['recorded_by']
                            ) ?>

                        </small>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<!-- ==========================================================
ACTION BUTTONS
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <h5 class="mb-0">

            Available Actions

        </h5>

    </div>

    <div class="card-body">

        <div class="d-flex flex-wrap gap-2">

            <a
                href="print.php?id=<?= $saleId ?>"
                class="btn btn-primary">

                <i class="bi bi-printer"></i>

                Print Invoice

            </a>

            <a
                href="receipt.php?id=<?= $saleId ?>"
                class="btn btn-success">

                <i class="bi bi-receipt"></i>

                Receipt

            </a>

            <?php if ($receipt): ?>

                <a
                    href="receipt_print.php?id=<?= $receipt['id'] ?>"
                    class="btn btn-info text-white">

                    <i class="bi bi-file-earmark-pdf"></i>

                    Print Receipt

                </a>

            <?php endif; ?>

            <?php if ($sale['balance'] > 0): ?>

                <a
                    href="payment.php?id=<?= $saleId ?>"
                    class="btn btn-warning">

                    <i class="bi bi-cash-coin"></i>

                    Add Payment

                </a>

            <?php endif; ?>

            <?php if ($sale['status'] === 'completed'): ?>

                <a
                    href="refund.php?id=<?= $saleId ?>"
                    class="btn btn-danger"
                    onclick="return confirm('Refund this sale?');">

                    <i class="bi bi-arrow-counterclockwise"></i>

                    Refund

                </a>

            <?php endif; ?>

            <a
                href="history.php"
                class="btn btn-secondary">

                <i class="bi bi-list-ul"></i>

                Back to History

            </a>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const printCount = <?= (int)($receipt['print_count'] ?? 0) ?>;

    if (printCount > 3) {

        console.warn(
            'Receipt has been printed multiple times.'
        );

    }

});

</script>