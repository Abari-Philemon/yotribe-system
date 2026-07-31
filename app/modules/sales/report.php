<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * Sales Report
 * Version 2.0 Enterprise
 * ============================================================
 *
 * Description:
 * ------------------------------------------------------------
 * Enterprise Sales Reporting Module
 *
 * Features
 * --------
 * • Sales Summary
 * • Sales Transactions
 * • Revenue Analysis
 * • Customer Sales
 * • Fish Sold
 * • Weight Sold
 * • Payment Analysis
 * • Outstanding Balances
 * • Export Ready
 *
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales');

$page_title = 'Sales Report';
$module     = 'sales';

$farmId  = farm_id();
$staffId = $_SESSION['staff_id'];

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

$customer = trim($_GET['customer'] ?? '');

$status = trim($_GET['status'] ?? '');

$paymentMethod = trim(
    $_GET['payment_method'] ?? ''
);

$saleNo = trim($_GET['sale_no'] ?? '');

$recordedBy = filter_input(
    INPUT_GET,
    'recorded_by',
    FILTER_VALIDATE_INT
);

/*
|--------------------------------------------------------------------------
| Default Dates
|--------------------------------------------------------------------------
*/

if ($dateFrom === '') {

    $dateFrom = date('Y-m-01');

}

if ($dateTo === '') {

    $dateTo = date('Y-m-d');

}

/*
|--------------------------------------------------------------------------
| Load Staff
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT

        id,
        full_name

    FROM staff

    WHERE

        farm_id = ?

    AND status = 'active'

    ORDER BY

        full_name

");

$stmt->execute([$farmId]);

$staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Payment Methods
|--------------------------------------------------------------------------
*/

$paymentMethods = [

    'cash'     => 'Cash',
    'transfer' => 'Transfer',
    'pos'      => 'POS',
    'credit'   => 'Credit',
    'wallet'   => 'Wallet',
    'multiple' => 'Multiple'

];

/*
|--------------------------------------------------------------------------
| Sale Status
|--------------------------------------------------------------------------
*/

$saleStatus = [

    'draft'      => 'Draft',
    'completed'  => 'Completed',
    'cancelled'  => 'Cancelled',
    'refunded'   => 'Refunded'

];

/*
|--------------------------------------------------------------------------
| Build WHERE Clause
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];

$where[] = "s.farm_id = ?";

$params[] = $farmId;

$where[] = "DATE(s.sale_date) BETWEEN ? AND ?";

$params[] = $dateFrom;

$params[] = $dateTo;

if ($customer !== '') {

    $where[] = "s.customer_name LIKE ?";

    $params[] = "%{$customer}%";

}

if ($status !== '') {

    $where[] = "s.status = ?";

    $params[] = $status;

}

if ($saleNo !== '') {

    $where[] = "s.sale_no LIKE ?";

    $params[] = "%{$saleNo}%";

}

if ($recordedBy) {

    $where[] = "s.recorded_by = ?";

    $params[] = $recordedBy;

}

if ($paymentMethod !== '') {

    $where[] = "

        EXISTS (

            SELECT 1

            FROM sale_payments sp

            WHERE

                sp.sale_id = s.id

            AND sp.payment_method = ?

        )

    ";

    $params[] = $paymentMethod;

}

$whereSQL = implode(

    "\nAND ",

    $where

);

/*
|--------------------------------------------------------------------------
| KPI Summary Query
|--------------------------------------------------------------------------
*/

$summarySQL = "

SELECT

    COUNT(DISTINCT s.id) AS total_transactions,

    COALESCE(SUM(s.total_amount),0) AS total_sales,

    COALESCE(SUM(s.amount_paid),0) AS total_paid,

    COALESCE(SUM(s.balance),0) AS total_balance,

    COALESCE(SUM(si.quantity_fish),0) AS total_fish,

    COALESCE(SUM(si.quantity_kg),0) AS total_weight

FROM sales s

LEFT JOIN sale_items si

    ON si.sale_id = s.id

WHERE

{$whereSQL}

";

$stmt = $pdo->prepare($summarySQL);

$stmt->execute($params);

$summary = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| KPI Variables
|--------------------------------------------------------------------------
*/

$totalTransactions = (int)$summary['total_transactions'];

$totalSales = (float)$summary['total_sales'];

$totalPaid = (float)$summary['total_paid'];

$totalBalance = (float)$summary['total_balance'];

$totalFish = (int)$summary['total_fish'];

$totalWeight = (float)$summary['total_weight'];

$averageSale = $totalTransactions > 0

    ? $totalSales / $totalTransactions

    : 0;

/*
|--------------------------------------------------------------------------
| Main Report SQL
|--------------------------------------------------------------------------
|
| Part 2 continues from here...
|
*/
/*
|--------------------------------------------------------------------------
| Main Report Query
|--------------------------------------------------------------------------
|
| Load complete report dataset
|
*/

$reportSQL = "

SELECT

    s.id,
    s.uuid,
    s.sale_no,
    s.sale_date,
    s.customer_name,
    s.customer_phone,
    s.sale_type,
    s.status,

    s.subtotal,
    s.discount,
    s.tax,
    s.total_amount,
    s.amount_paid,
    s.balance,

    st.full_name AS recorded_by_name,

    COALESCE(SUM(si.quantity_fish),0) AS total_fish,

    COALESCE(SUM(si.quantity_kg),0) AS total_weight,

    GROUP_CONCAT(
        DISTINCT sp.payment_method
        ORDER BY sp.payment_method
        SEPARATOR ', '
    ) AS payment_methods

FROM sales s

LEFT JOIN sale_items si

    ON si.sale_id = s.id

LEFT JOIN sale_payments sp

    ON sp.sale_id = s.id

LEFT JOIN staff st

    ON st.id = s.recorded_by

WHERE

{$whereSQL}

GROUP BY

    s.id

ORDER BY

    s.sale_date DESC,
    s.id DESC

";

$stmt = $pdo->prepare($reportSQL);

$stmt->execute($params);

$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Customer List
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT DISTINCT

    customer_name

FROM sales

WHERE

    farm_id = ?

AND customer_name IS NOT NULL

AND customer_name <> ''

ORDER BY

    customer_name ASC

");

$stmt->execute([$farmId]);

$customers = $stmt->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| Report Generated Time
|--------------------------------------------------------------------------
*/

$generatedAt = date('d M Y H:i:s');

/*
|--------------------------------------------------------------------------
| Load Layout
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../includes/header.php';

?>

<div class="container-fluid">

    <!-- =======================================================
    Page Header
    ======================================================== -->

    <div class="d-flex
                justify-content-between
                align-items-center
                mb-4">

        <div>

            <h2 class="mb-1">

                Sales Report

            </h2>

            <div class="text-muted">

                Sales & Distribution Management

            </div>

        </div>

        <div class="text-end">

            <small class="text-muted">

                Generated

                <br>

                <?= htmlspecialchars($generatedAt) ?>

            </small>

        </div>

    </div>

    <!-- =======================================================
    Toolbar
    ======================================================== -->

    <div class="mb-4">

        <a href="index.php"
           class="btn btn-secondary">

            ← Back

        </a>

        <a href="create.php"
           class="btn btn-success">

            New Sale

        </a>

        <button
            class="btn btn-primary"
            onclick="window.print()">

            Print

        </button>

        <a
            href="export_excel.php?<?= http_build_query($_GET) ?>"
            class="btn btn-outline-success">

            Excel

        </a>

        <a
            href="export_pdf.php?<?= http_build_query($_GET) ?>"
            class="btn btn-outline-danger">

            PDF

        </a>

    </div>

    <!-- =======================================================
    Filters
    ======================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <strong>

                Report Filters

            </strong>

        </div>

        <div class="card-body">

            <form
                method="GET"
                class="row g-3">

                <div class="col-md-2">

                    <label class="form-label">

                        Date From

                    </label>

                    <input
                        type="date"
                        name="date_from"
                        class="form-control"
                        value="<?= htmlspecialchars($dateFrom) ?>">

                </div>

                <div class="col-md-2">

                    <label class="form-label">

                        Date To

                    </label>

                    <input
                        type="date"
                        name="date_to"
                        class="form-control"
                        value="<?= htmlspecialchars($dateTo) ?>">

                </div>

                <div class="col-md-3">

                    <label class="form-label">

                        Customer

                    </label>

                    <select
                        name="customer"
                        class="form-select">

                        <option value="">

                            All Customers

                        </option>

                        <?php foreach ($customers as $name): ?>

                            <option
                                value="<?= htmlspecialchars($name) ?>"
                                <?= $customer === $name ? 'selected' : '' ?>>

                                <?= htmlspecialchars($name) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-2">

                    <label class="form-label">

                        Status

                    </label>

                    <select
                        name="status"
                        class="form-select">

                        <option value="">

                            All

                        </option>

                        <?php foreach ($saleStatus as $key => $label): ?>

                            <option
                                value="<?= $key ?>"
                                <?= $status === $key ? 'selected' : '' ?>>

                                <?= $label ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-3">

                    <label class="form-label">

                        Payment Method

                    </label>

                    <select
                        name="payment_method"
                        class="form-select">

                        <option value="">

                            All

                        </option>

                        <?php foreach ($paymentMethods as $key => $label): ?>

                            <option
                                value="<?= $key ?>"
                                <?= $paymentMethod === $key ? 'selected' : '' ?>>

                                <?= $label ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>
                                <div class="col-md-2">

                    <label class="form-label">

                        Recorded By

                    </label>

                    <select
                        name="recorded_by"
                        class="form-select">

                        <option value="">

                            All Staff

                        </option>

                        <?php foreach ($staffList as $staff): ?>

                            <option
                                value="<?= (int)$staff['id'] ?>"
                                <?= $recordedBy == $staff['id'] ? 'selected' : '' ?>>

                                <?= htmlspecialchars($staff['full_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-2">

                    <label class="form-label">

                        Sale No

                    </label>

                    <input
                        type="text"
                        name="sale_no"
                        class="form-control"
                        placeholder="Search..."
                        value="<?= htmlspecialchars($saleNo) ?>">

                </div>

                <div class="col-md-4 d-flex align-items-end">

                    <button
                        type="submit"
                        class="btn btn-primary me-2">

                        Generate Report

                    </button>

                    <a
                        href="report.php"
                        class="btn btn-outline-secondary">

                        Reset

                    </a>

                </div>

            </form>

        </div>

    </div>

    <!-- ======================================================
    KPI Summary
    ======================================================= -->

    <div class="row mb-4">

        <div class="col-lg-2 col-md-4 mb-3">

            <div class="card border-primary shadow-sm">

                <div class="card-body text-center">

                    <h6 class="text-muted">

                        Transactions

                    </h6>

                    <h3>

                        <?= number_format($totalTransactions) ?>

                    </h3>

                </div>

            </div>

        </div>

        <div class="col-lg-2 col-md-4 mb-3">

            <div class="card border-success shadow-sm">

                <div class="card-body text-center">

                    <h6 class="text-muted">

                        Revenue

                    </h6>

                    <h4>

                        ₦<?= number_format($totalSales,2) ?>

                    </h4>

                </div>

            </div>

        </div>

        <div class="col-lg-2 col-md-4 mb-3">

            <div class="card border-info shadow-sm">

                <div class="card-body text-center">

                    <h6 class="text-muted">

                        Paid

                    </h6>

                    <h4>

                        ₦<?= number_format($totalPaid,2) ?>

                    </h4>

                </div>

            </div>

        </div>

        <div class="col-lg-2 col-md-4 mb-3">

            <div class="card border-warning shadow-sm">

                <div class="card-body text-center">

                    <h6 class="text-muted">

                        Outstanding

                    </h6>

                    <h4>

                        ₦<?= number_format($totalBalance,2) ?>

                    </h4>

                </div>

            </div>

        </div>

        <div class="col-lg-2 col-md-4 mb-3">

            <div class="card border-dark shadow-sm">

                <div class="card-body text-center">

                    <h6 class="text-muted">

                        Fish Sold

                    </h6>

                    <h3>

                        <?= number_format($totalFish) ?>

                    </h3>

                </div>

            </div>

        </div>

        <div class="col-lg-2 col-md-4 mb-3">

            <div class="card border-secondary shadow-sm">

                <div class="card-body text-center">

                    <h6 class="text-muted">

                        Weight

                    </h6>

                    <h4>

                        <?= number_format($totalWeight,2) ?>

                        Kg

                    </h4>

                </div>

            </div>

        </div>

    </div>

    <!-- ======================================================
    Sales Report Table
    ======================================================= -->

    <div class="card shadow-sm">

        <div class="card-header d-flex justify-content-between">

            <strong>

                Sales Transactions

            </strong>

            <span>

                <?= count($reports) ?>

                Record(s)

            </span>

        </div>

        <div class="table-responsive">

            <table
                class="table table-bordered table-hover table-striped align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th>#</th>

                        <th>Sale No</th>

                        <th>Date</th>

                        <th>Customer</th>

                        <th>Fish</th>

                        <th>Weight (Kg)</th>

                        <th>Payment</th>

                        <th>Status</th>

                        <th>Total</th>

                        <th>Paid</th>

                        <th>Balance</th>

                        <th>Staff</th>

                        <th class="text-center">

                            Action

                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($reports)): ?>

                    <tr>

                        <td
                            colspan="13"
                            class="text-center text-muted">

                            No sales found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($reports as $index => $row): ?>

                        <tr>

                            <td>

                                <?= $index + 1 ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($row['sale_no']) ?>

                            </td>

                            <td>

                                <?= date(
                                    'd-M-Y',
                                    strtotime($row['sale_date'])
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($row['customer_name']) ?>

                            </td>

                            <td class="text-end">

                                <?= number_format((int)$row['total_fish']) ?>

                            </td>

                            <td class="text-end">

                                <?= number_format((float)$row['total_weight'],2) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($row['payment_methods'] ?? '-') ?>

                            </td>

                            <td>

                                <span class="badge bg-primary">

                                    <?= ucfirst($row['status']) ?>

                                </span>

                            </td>

                            <td class="text-end">

                                ₦<?= number_format((float)$row['total_amount'],2) ?>

                            </td>

                            <td class="text-end">

                                ₦<?= number_format((float)$row['amount_paid'],2) ?>

                            </td>

                            <td class="text-end">

                                ₦<?= number_format((float)$row['balance'],2) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($row['recorded_by_name']) ?>

                            </td>

                            <td class="text-center">

                                <a
                                    href="view.php?id=<?= (int)$row['id'] ?>"
                                    class="btn btn-sm btn-outline-primary">

                                    View

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
                                <tfoot class="table-light">

                    <tr>

                        <th colspan="4" class="text-end">

                            GRAND TOTAL

                        </th>

                        <th class="text-end">

                            <?= number_format($totalFish) ?>

                        </th>

                        <th class="text-end">

                            <?= number_format($totalWeight, 2) ?>

                        </th>

                        <th></th>

                        <th></th>

                        <th class="text-end">

                            ₦<?= number_format($totalSales, 2) ?>

                        </th>

                        <th class="text-end">

                            ₦<?= number_format($totalPaid, 2) ?>

                        </th>

                        <th class="text-end">

                            ₦<?= number_format($totalBalance, 2) ?>

                        </th>

                        <th colspan="2"></th>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>

    <!-- =======================================================
    Report Summary
    ======================================================== -->

    <div class="card mt-4 shadow-sm">

        <div class="card-header">

            <strong>

                Report Summary

            </strong>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6">

                    <table class="table table-sm">

                        <tr>

                            <th width="45%">

                                Report Period

                            </th>

                            <td>

                                <?= htmlspecialchars($dateFrom) ?>

                                -

                                <?= htmlspecialchars($dateTo) ?>

                            </td>

                        </tr>

                        <tr>

                            <th>

                                Transactions

                            </th>

                            <td>

                                <?= number_format($totalTransactions) ?>

                            </td>

                        </tr>

                        <tr>

                            <th>

                                Fish Sold

                            </th>

                            <td>

                                <?= number_format($totalFish) ?>

                            </td>

                        </tr>

                        <tr>

                            <th>

                                Weight Sold

                            </th>

                            <td>

                                <?= number_format($totalWeight,2) ?>

                                Kg

                            </td>

                        </tr>

                    </table>

                </div>

                <div class="col-md-6">

                    <table class="table table-sm">

                        <tr>

                            <th width="45%">

                                Revenue

                            </th>

                            <td>

                                ₦<?= number_format($totalSales,2) ?>

                            </td>

                        </tr>

                        <tr>

                            <th>

                                Amount Paid

                            </th>

                            <td>

                                ₦<?= number_format($totalPaid,2) ?>

                            </td>

                        </tr>

                        <tr>

                            <th>

                                Outstanding

                            </th>

                            <td>

                                ₦<?= number_format($totalBalance,2) ?>

                            </td>

                        </tr>

                        <tr>

                            <th>

                                Average Sale

                            </th>

                            <td>

                                ₦<?= number_format($averageSale,2) ?>

                            </td>

                        </tr>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
JavaScript
========================================================== -->

<script>

document.addEventListener(

    'DOMContentLoaded',

    function () {

        const form = document.querySelector('form');

        form?.addEventListener(

            'submit',

            function () {

                const btn = this.querySelector(
                    'button[type="submit"]'
                );

                if (btn) {

                    btn.disabled = true;

                    btn.innerHTML =
                        'Generating...';

                }

            }

        );

    }

);

</script>

<?php

require_once __DIR__ .
    '/../../includes/footer.php';