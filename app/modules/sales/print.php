<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales Management
 * Print Invoice
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales.print');

$farm_id = farm_id();

$saleId = (int)($_GET['id'] ?? 0);

if ($saleId <= 0) {

    $_SESSION['error'] = 'Invalid sale selected.';

    header('Location: history.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Company Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT *

FROM companies

WHERE farm_id=?

LIMIT 1

");

$stmt->execute([$farm_id]);

$company = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Sale Header
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

    $_SESSION['error']='Sale not found.';

    header('Location:history.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Sale Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT *

FROM sale_items

WHERE sale_id=?

ORDER BY id

");

$stmt->execute([$saleId]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Payments
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

$page_title = 'Print Invoice';

require_once __DIR__.'/../../includes/header.php';
?>
<style>

    .invoice{

        background:#fff;

        padding:30px;

        max-width:1100px;

        margin:auto;

    }

    @media print{

        body{

            background:#fff;

            font-size:12px;

        }

        .navbar,
        .sidebar,
        .no-print,
        footer{

            display:none !important;

        }

        .container-fluid{

            width:100%;

            margin:0;

            padding:0;

        }

        .invoice{

            box-shadow:none;

            border:none;

            width:100%;

            padding:0;

        }

        .card{

            border:1px solid #ddd !important;

            break-inside:avoid;

        }

    }

</style>
<div class="container-fluid py-4">

<div class="invoice">
    <div class="row mb-4">

<div class="col-md-6">

<h5>

Bill To

</h5>

<strong>

<?= htmlspecialchars(
    $sale['customer_name']
    ?: 'Walk-in Customer'
) ?>

</strong>

<br>

<?= htmlspecialchars(
    $sale['customer_address']
    ?: '-'
) ?>

<br>

<?= htmlspecialchars(
    $sale['customer_phone']
    ?: '-'
) ?>

</div>

<div class="col-md-6 text-end">

<table class="table table-sm table-borderless">

<tr>

<th>

Invoice Date

</th>

<td>

<?= date(
    'd M Y',
    strtotime($sale['sale_date'])
) ?>

</td>

</tr>

<tr>

<th>

Harvest

</th>

<td>

<?= htmlspecialchars(
    $sale['harvest_no']
) ?>

</td>

</tr>

<tr>

<th>

Batch

</th>

<td>

<?= htmlspecialchars(
    $sale['batch_code']
) ?>

</td>

</tr>

<tr>

<th>

Served By

</th>

<td>

<?= htmlspecialchars(
    $sale['full_name']
) ?>

</td>

</tr>

</table>

</div>

</div>
<!-- ==========================================================
INVOICE ITEMS
========================================================== -->

<div class="card border-0 mb-4">

    <div class="card-header bg-light">

        <strong>Invoice Details</strong>

    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-hover mb-0">

            <thead class="table-light">

            <tr>

                <th width="5%">#</th>

                <th>Description</th>

                <th class="text-end" width="10%">Fish</th>

                <th class="text-end" width="12%">Weight (Kg)</th>

                <th class="text-end" width="12%">Avg. Weight</th>

                <th class="text-end" width="15%">Unit Price</th>

                <th class="text-end" width="16%">Amount</th>

            </tr>

            </thead>

            <tbody>

            <?php

            $totalFish = 0;
            $totalWeight = 0;
            $invoiceTotal = 0;

            ?>

            <?php foreach ($items as $index => $item): ?>

                <?php

                $totalFish += (int)$item['quantity_fish'];

                $totalWeight += (float)$item['quantity_kg'];

                $invoiceTotal += (float)$item['line_total'];

                ?>

                <tr>

                    <td>

                        <?= $index + 1 ?>

                    </td>

                    <td>

                        <strong>

                            <?= htmlspecialchars($item['product_name']) ?>

                        </strong>

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

            </tbody>

            <tfoot class="table-light">

            <tr>

                <th colspan="2" class="text-end">

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
                        $invoiceTotal,
                        2
                    ) ?>

                </th>

            </tr>

            </tfoot>

        </table>

    </div>

</div>
<!-- ==========================================================
FINANCIAL SUMMARY
========================================================== -->

<div class="row mb-4">

    <div class="col-md-7">

        <div class="card">

            <div class="card-header">

                Sale Summary

            </div>

            <div class="card-body">

                <table class="table table-sm mb-0">

                    <tr>

                        <th>Total Fish Sold</th>

                        <td class="text-end">

                            <?= number_format($totalFish) ?>

                        </td>

                    </tr>

                    <tr>

                        <th>Total Weight</th>

                        <td class="text-end">

                            <?= number_format(
                                $totalWeight,
                                2
                            ) ?>

                            Kg

                        </td>

                    </tr>

                    <tr>

                        <th>Species</th>

                        <td class="text-end">

                            <?= htmlspecialchars(
                                $sale['species']
                            ) ?>

                        </td>

                    </tr>

                </table>

            </div>

        </div>

    </div>

    <div class="col-md-5">

        <table class="table table-bordered">

            <tr>

                <th>

                    Subtotal

                </th>

                <td class="text-end">

                    ₦<?= number_format(
                        $sale['subtotal'],
                        2
                    ) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Discount

                </th>

                <td class="text-end">

                    ₦<?= number_format(
                        $sale['discount'],
                        2
                    ) ?>

                </td>

            </tr>

            <tr class="table-warning">

                <th>

                    Grand Total

                </th>

                <th class="text-end">

                    ₦<?= number_format(
                        $sale['total_amount'],
                        2
                    ) ?>

                </th>

            </tr>

            <tr>

                <th>

                    Amount Paid

                </th>

                <td class="text-end text-success">

                    ₦<?= number_format(
                        $sale['amount_paid'],
                        2
                    ) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Outstanding Balance

                </th>

                <td class="text-end text-danger">

                    ₦<?= number_format(
                        $sale['balance'],
                        2
                    ) ?>

                </td>

            </tr>

        </table>

    </div>

</div>
<!-- ==========================================================
PAYMENT HISTORY
========================================================== -->

<div class="card mb-4">

    <div class="card-header">

        Payment History

    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-sm mb-0">

            <thead class="table-light">

            <tr>

                <th>Date</th>

                <th>Method</th>

                <th>Reference</th>

                <th class="text-end">

                    Amount

                </th>

            </tr>

            </thead>

            <tbody>

            <?php if (empty($payments)): ?>

                <tr>

                    <td colspan="4"
                        class="text-center text-muted">

                        No payments recorded.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($payments as $payment): ?>

                    <tr>

                        <td>

                            <?= date(
                                'd M Y H:i',
                                strtotime(
                                    $payment['payment_date']
                                )
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
                                $payment['reference_no']
                                ?: '-'
                            ) ?>

                        </td>

                        <td class="text-end">

                            ₦<?= number_format(
                                $payment['amount'],
                                2
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>
<!-- ==========================================================
REMARKS
========================================================== -->

<?php if (!empty($sale['remarks'])): ?>

<div class="card mb-4">

    <div class="card-header">

        Remarks

    </div>

    <div class="card-body">

        <?= nl2br(htmlspecialchars($sale['remarks'])) ?>

    </div>

</div>

<?php endif; ?>

<!-- ==========================================================
TERMS & CONDITIONS
========================================================== -->

<div class="card mb-4">

    <div class="card-header">

        Terms & Conditions

    </div>

    <div class="card-body">

        <ol class="mb-0">

            <li>

                Goods sold are not returnable unless otherwise agreed.

            </li>

            <li>

                Please verify all quantities before leaving the farm.

            </li>

            <li>

                Outstanding balances shall be settled according to the agreed payment terms.

            </li>

            <li>

                This invoice serves as proof of the sales transaction.

            </li>

        </ol>

    </div>

</div>

<!-- ==========================================================
PAYMENT STATUS
========================================================== -->

<?php

if ((float)$sale['balance'] <= 0) {

    $paymentStatus = 'PAID';
    $paymentClass  = 'success';

} elseif ((float)$sale['amount_paid'] > 0) {

    $paymentStatus = 'PARTIALLY PAID';
    $paymentClass  = 'warning';

} else {

    $paymentStatus = 'UNPAID';
    $paymentClass  = 'danger';

}

?>

<div class="alert alert-<?= $paymentClass ?> text-center">

    <h4 class="mb-0">

        <?= $paymentStatus ?>

    </h4>

</div>
<!-- ==========================================================
SIGNATURES
========================================================== -->

<div class="row mt-5">

    <div class="col-md-4 text-center">

        ___________________________

        <br>

        Prepared By

        <br>

        <strong>

            <?= htmlspecialchars($sale['full_name']) ?>

        </strong>

    </div>

    <div class="col-md-4 text-center">

        ___________________________

        <br>

        Customer Signature

    </div>

    <div class="col-md-4 text-center">

        ___________________________

        <br>

        Authorized Signature

    </div>

</div>
<div class="text-center mt-5 no-print">

    <button
        class="btn btn-primary me-2"
        onclick="window.print();">

        <i class="bi bi-printer"></i>

        Print Invoice

    </button>

    <a
        href="view.php?id=<?= $saleId ?>"
        class="btn btn-secondary">

        <i class="bi bi-arrow-left"></i>

        Back

    </a>

</div>

</div>
<script>

const autoPrint = false;

if (autoPrint) {

    window.onload = function () {

        window.print();

    };

}

</script>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>