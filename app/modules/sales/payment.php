<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales Management
 * Receive Payment
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales.payment');

$farm_id = farm_id();

$page_title = 'Receive Payment';

/*
|--------------------------------------------------------------------------
| Validate Sale
|--------------------------------------------------------------------------
*/

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
| Prevent Payment on Closed Sales
|--------------------------------------------------------------------------
*/

if (

    in_array(

        $sale['status'],

        ['cancelled','refunded'],

        true

    )

) {

    $_SESSION['error'] =

        'Payments cannot be added to this sale.';

    header("Location:view.php?id={$saleId}");

    exit;

}

if ((float)$sale['balance'] <= 0) {

    $_SESSION['error'] =

        'This sale has already been fully paid.';

    header("Location:view.php?id={$saleId}");

    exit;

}

/*
|--------------------------------------------------------------------------
| Previous Payments
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    payment_no,

    payment_date,

    payment_method,

    reference_no,

    amount,

    payment_status

FROM sale_payments

WHERE sale_id = ?

ORDER BY payment_date DESC

");

$stmt->execute([$saleId]);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <h5 class="mb-0">

            Sale Summary

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

                <div class="text-success">

                    ₦<?= number_format(
                        $sale['amount_paid'],
                        2
                    ) ?>

                </div>

            </div>

            <div class="col-md-2">

                <strong>Balance</strong>

                <div class="text-danger fw-bold">

                    ₦<?= number_format(
                        $sale['balance'],
                        2
                    ) ?>

                </div>

            </div>

        </div>

    </div>

</div>
<div class="card shadow-sm mb-4">

    <div class="card-header">

        Previous Payments

    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-sm mb-0">

            <thead>

            <tr>

                <th>Date</th>

                <th>Method</th>

                <th>Reference</th>

                <th class="text-end">

                    Amount

                </th>

                <th>Status</th>

            </tr>

            </thead>

            <tbody>

            <?php if (empty($payments)): ?>

                <tr>

                    <td colspan="5"
                        class="text-center">

                        No previous payments.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($payments as $payment): ?>

                    <tr>

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

                        <td>

                            <?= ucfirst(
                                $payment['payment_status']
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
RECEIVE PAYMENT
========================================================== -->

<form action="payment_save.php" method="POST" id="paymentForm">

    <input
        type="hidden"
        name="sale_id"
        value="<?= $saleId ?>">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-success text-white">

            <h5 class="mb-0">

                Receive Payment

            </h5>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-4 mb-3">

                    <label class="form-label">

                        Payment Date

                    </label>

                    <input
                        type="datetime-local"
                        name="payment_date"
                        class="form-control"
                        value="<?= date('Y-m-d\TH:i') ?>"
                        required>

                </div>

                <div class="col-md-4 mb-3">

                    <label class="form-label">

                        Payment Method

                    </label>

                    <select
                        name="payment_method"
                        id="payment_method"
                        class="form-select"
                        required>

                        <option value="cash">

                            Cash

                        </option>

                        <option value="transfer">

                            Bank Transfer

                        </option>

                        <option value="pos">

                            POS

                        </option>

                        <option value="deposit">

                            Bank Deposit

                        </option>

                        <option value="cheque">

                            Cheque

                        </option>

                        <option value="mobile_money">

                            Mobile Money

                        </option>

                    </select>

                </div>

                <div class="col-md-4 mb-3">

                    <label class="form-label">

                        Amount

                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        max="<?= $sale['balance'] ?>"
                        name="amount"
                        id="amount"
                        class="form-control"
                        required>

                    <small class="text-muted">

                        Outstanding Balance:

                        ₦<?= number_format($sale['balance'],2) ?>

                    </small>

                </div>

            </div>

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Reference Number

                    </label>

                    <input
                        type="text"
                        name="reference_no"
                        class="form-control"
                        placeholder="Transaction ID / Cheque No / Deposit Slip">

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Received By

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars($sale['full_name']) ?>"
                        readonly>

                </div>

            </div>

            <div class="mb-3">

                <label class="form-label">

                    Remarks

                </label>

                <textarea
                    name="remarks"
                    rows="3"
                    class="form-control"
                    placeholder="Optional payment remarks..."></textarea>

            </div>

        </div>

    </div>
    <!-- ==========================================================
PAYMENT SUMMARY
========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-warning">

        <h5 class="mb-0">

            Payment Summary

        </h5>

    </div>

    <div class="card-body">

        <div class="row text-center">

            <div class="col-md-4">

                <h6>

                    Outstanding Balance

                </h6>

                <h3 class="text-danger">

                    ₦<span id="balanceDisplay">

                        <?= number_format($sale['balance'],2) ?>

                    </span>

                </h3>

            </div>

            <div class="col-md-4">

                <h6>

                    Payment Amount

                </h6>

                <h3 class="text-primary">

                    ₦<span id="paymentDisplay">

                        0.00

                    </span>

                </h3>

            </div>

            <div class="col-md-4">

                <h6>

                    Remaining Balance

                </h6>

                <h3 class="text-success">

                    ₦<span id="remainingDisplay">

                        <?= number_format($sale['balance'],2) ?>

                    </span>

                </h3>

            </div>

        </div>

    </div>

</div>
<div class="d-flex justify-content-end">

    <a
        href="view.php?id=<?= $saleId ?>"
        class="btn btn-secondary me-2">

        Cancel

    </a>

    <button
        type="submit"
        class="btn btn-success">

        <i class="bi bi-cash-coin"></i>

        Save Payment

    </button>

</div>

</form>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>
<script>

const balance = <?= (float)$sale['balance'] ?>;

const amount = document.getElementById('amount');

amount.addEventListener('input', function(){

    let payment = parseFloat(this.value) || 0;

    if(payment > balance){

        payment = balance;

        this.value = balance.toFixed(2);

    }

    document.getElementById('paymentDisplay').textContent =
        payment.toFixed(2);

    document.getElementById('remainingDisplay').textContent =
        (balance - payment).toFixed(2);

});

</script>