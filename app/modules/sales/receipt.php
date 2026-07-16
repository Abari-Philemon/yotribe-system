<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales Management
 * Receipt
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales');

$farm_id = farm_id();

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
| Load Items
|--------------------------------------------------------------------------
*/

$stmt=$pdo->prepare("

SELECT

product_name,

quantity_fish,

quantity_kg,

unit_price,

line_total

FROM sale_items

WHERE sale_id=?

ORDER BY id

");

$stmt->execute([$saleId]);

$items=$stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load Payments
|--------------------------------------------------------------------------
*/

$stmt=$pdo->prepare("

SELECT *

FROM sale_payments

WHERE sale_id=?

ORDER BY payment_date

");

$stmt->execute([$saleId]);

$payments=$stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load Receipt
|--------------------------------------------------------------------------
*/

$stmt=$pdo->prepare("

SELECT *

FROM sale_receipts

WHERE sale_id=?

LIMIT 1

");

$stmt->execute([$saleId]);

$receipt=$stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Increment Print Count
|--------------------------------------------------------------------------
*/

if ($receipt) {

    $pdo->prepare("

        UPDATE sale_receipts

        SET

            print_count=print_count+1,

            last_printed_at=NOW()

        WHERE id=?

    ")->execute([

        $receipt['id']

    ]);

}

$page_title='Receipt';

require_once __DIR__.'/../../includes/header.php';
?>
<style>

    @media print{

        body{

            background:#ffffff;

        }

        .navbar,
        .sidebar,
        .no-print,
        .btn,
        footer{

            display:none !important;

        }

        .container{

            width:100%;

            max-width:100%;

            margin:0;

            padding:0;

        }

        .card{

            border:none !important;

            box-shadow:none !important;

        }

    }

    .receipt-paper{

        max-width:420px;

        margin:auto;

    }

</style>
<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-5">
            <div class="card shadow-lg border-0">

    <div class="card-body">

        <!-- ======================================================
             COMPANY HEADER
        ======================================================= -->

        <div class="text-center mb-4">

            <h3 class="mb-1">

                YOTRIBE AGRO ALLIED SERVICES

            </h3>

            <div>

                Fish Farm Management System

            </div>

            <small class="text-muted">

                Sales Receipt

            </small>

        </div>

        <hr>

        <!-- ======================================================
             RECEIPT INFORMATION
        ======================================================= -->

        <table class="table table-borderless table-sm">

            <tr>

                <th width="40%">

                    Receipt No

                </th>

                <td>

                    <?= htmlspecialchars(
                        $receipt['receipt_no'] ?? '-'
                    ) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Sale No

                </th>

                <td>

                    <?= htmlspecialchars(
                        $sale['sale_no']
                    ) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Date

                </th>

                <td>

                    <?= date(
                        'd M Y H:i',
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

        </table>

        <hr>

        <!-- ======================================================
             CUSTOMER INFORMATION
        ======================================================= -->

        <h5>

            Customer

        </h5>

        <table class="table table-borderless table-sm">

            <tr>

                <th width="40%">

                    Name

                </th>

                <td>

                    <?= htmlspecialchars(
                        $sale['customer_name']
                        ?: 'Walk-in Customer'
                    ) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Phone

                </th>

                <td>

                    <?= htmlspecialchars(
                        $sale['customer_phone']
                        ?: '-'
                    ) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Address

                </th>

                <td>

                    <?= htmlspecialchars(
                        $sale['customer_address']
                        ?: '-'
                    ) ?>

                </td>

            </tr>

        </table>

        <hr>

        <!-- ======================================================
             ITEMS
        ======================================================= -->

        <table class="table table-bordered table-sm">

            <thead class="table-light">

            <tr>

                <th>Item</th>

                <th class="text-end">

                    Qty

                </th>

                <th class="text-end">

                    Kg

                </th>

                <th class="text-end">

                    Amount

                </th>

            </tr>

            </thead>

            <tbody>

            <?php foreach ($items as $item): ?>

                <tr>

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

                        ₦<?= number_format(
                            $item['line_total'],
                            2
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>
        <!-- ======================================================
        TOTALS
        ====================================================== -->

        <table class="table table-sm">

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

        <tr>

            <th>

                Total

            </th>

            <td class="text-end">

                <strong>

                    ₦<?= number_format(
                        $sale['total_amount'],
                        2
                    ) ?>

                </strong>

            </td>

        </tr>

        <tr>

            <th>

                Paid

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

                Balance

            </th>

            <td class="text-end text-danger">

                ₦<?= number_format(
                    $sale['balance'],
                    2
                ) ?>

            </td>

        </tr>

        </table>
        <h5 class="mt-4">

        Payment Details

        </h5>

        <table class="table table-bordered table-sm">

        <thead>

        <tr>

        <th>Method</th>

        <th>Reference</th>

        <th class="text-end">

        Amount

        </th>

        </tr>

        </thead>

        <tbody>

        <?php foreach($payments as $payment): ?>

        <tr>

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

        </tbody>

        </table>
        <hr>

<div class="text-center mt-4">

<strong>

Served By

</strong>

<br>

<?= htmlspecialchars(
    $sale['full_name']
) ?>

<br><br>

<strong>

Thank You For Your Patronage

</strong>

<br>

<small class="text-muted">

This receipt serves as proof of payment.

</small>

</div>

</div>

</div>
<!-- ==========================================================
REPRINT WATERMARK
========================================================== -->

<?php if (($receipt['print_count'] ?? 0) > 1): ?>

<div class="alert alert-warning text-center fw-bold">

    REPRINT COPY

    <br>

    <small>

        This receipt has been printed

        <?= (int)$receipt['print_count'] ?>

        times.

    </small>

</div>

<?php endif; ?>

<!-- ==========================================================
ACTION BUTTONS
========================================================== -->

<div class="text-center mt-4 mb-3 no-print">

    <?php if (canAccess('sales.print')): ?>

        <button
            type="button"
            class="btn btn-primary me-2"
            onclick="printReceipt()">

            <i class="bi bi-printer"></i>

            Print Receipt

        </button>

    <?php endif; ?>

    <?php if (canAccess('sales.view')): ?>

        <a
            href="view.php?id=<?= $saleId ?>"
            class="btn btn-secondary me-2">

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    <?php endif; ?>

    <button
        type="button"
        class="btn btn-danger"
        onclick="window.close();">

        <i class="bi bi-x-circle"></i>

        Close

    </button>

</div>

</div>

</div>
<script>

function printReceipt(){

    window.print();

}

/*
----------------------------------------------------------
Optional Auto Print
----------------------------------------------------------
*/

const autoPrint = false;

if(autoPrint){

    window.onload=function(){

        window.print();

    };

}

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
