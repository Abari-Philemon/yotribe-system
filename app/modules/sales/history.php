<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales Management
 * Sales History
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales');

$farm_id = farm_id();

$page_title = 'Sales History';

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$status = trim($_GET['status'] ?? '');

$paymentMethod = trim($_GET['payment_method'] ?? '');

$fromDate = trim($_GET['from'] ?? '');

$toDate = trim($_GET['to'] ?? '');

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$perPage = 20;

$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    s.id,

    s.sale_no,

    s.sale_date,

    s.customer_name,

    s.total_amount,

    s.amount_paid,

    s.balance,

    s.status,

    h.harvest_no,

    fb.batch_code

FROM sales s

INNER JOIN harvests h
ON h.id=s.harvest_id

INNER JOIN fish_batches fb
ON fb.id=h.fish_batch_id

WHERE

s.farm_id=?

";

$params = [$farm_id];

if ($search !== '') {

    $sql .= "

    AND (

        s.sale_no LIKE ?

        OR s.customer_name LIKE ?

        OR fb.batch_code LIKE ?

    )

    ";

    $like = "%{$search}%";

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;

}

if ($status !== '') {

    $sql .= " AND s.status=? ";

    $params[] = $status;

}

if ($fromDate !== '') {

    $sql .= " AND DATE(s.sale_date)>=? ";

    $params[] = $fromDate;

}

if ($toDate !== '') {

    $sql .= " AND DATE(s.sale_date)<=? ";

    $params[] = $toDate;

}

$sql .= "

ORDER BY

s.sale_date DESC

LIMIT {$offset},{$perPage}

";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Total Records
|--------------------------------------------------------------------------
*/

$countSql = "

SELECT COUNT(*)

FROM sales

WHERE farm_id=?

";

$stmt = $pdo->prepare($countSql);

$stmt->execute([$farm_id]);

$totalRecords = (int)$stmt->fetchColumn();

$totalPages = (int)ceil($totalRecords / $perPage);

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h3>

Sales History

</h3>

<small class="text-muted">

Sales & Distribution

</small>

</div>

<a
href="create.php"
class="btn btn-success">

<i class="bi bi-plus-circle"></i>

New Sale

</a>

</div>
<div class="card shadow-sm mb-4">

<div class="card-header">

Search Filters

</div>

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-md-3">

<input
type="text"
name="search"
class="form-control"
placeholder="Sale No / Customer / Batch"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-2">

<select
name="status"
class="form-select">

<option value="">

All Status

</option>

<option value="completed"
<?= $status=='completed'?'selected':'' ?>>

Completed

</option>

<option value="cancelled"
<?= $status=='cancelled'?'selected':'' ?>>

Cancelled

</option>

<option value="refunded"
<?= $status=='refunded'?'selected':'' ?>>

Refunded

</option>

</select>

</div>

<div class="col-md-2">

<input
type="date"
name="from"
class="form-control"
value="<?= htmlspecialchars($fromDate) ?>">

</div>

<div class="col-md-2">

<input
type="date"
name="to"
class="form-control"
value="<?= htmlspecialchars($toDate) ?>">

</div>

<div class="col-md-3 d-grid">

<button
class="btn btn-primary">

<i class="bi bi-search"></i>

Search

</button>

</div>

</div>

</form>

</div>

</div>

<!-- ==========================================================
SALES HISTORY
========================================================== -->

<div class="card shadow-sm">

    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

        <h5 class="mb-0">

            Sales Transactions

        </h5>

        <span class="badge bg-light text-dark">

            <?= number_format($totalRecords) ?> Record(s)

        </span>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover table-bordered align-middle mb-0">

                <thead class="table-light">

                <tr>

                    <th width="60">#</th>

                    <th>Sale No</th>

                    <th>Date</th>

                    <th>Customer</th>

                    <th>Harvest</th>

                    <th>Batch</th>

                    <th class="text-end">Total</th>

                    <th class="text-end">Paid</th>

                    <th class="text-end">Balance</th>

                    <th>Status</th>

                    <th width="220">Actions</th>

                </tr>

                </thead>

                <tbody>

                <?php if (empty($sales)): ?>

                    <tr>

                        <td colspan="11"
                            class="text-center text-muted py-5">

                            No sales found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($sales as $index => $sale): ?>

                        <?php

                        $badge = match ($sale['status']) {

                            'completed' => 'success',

                            'cancelled' => 'danger',

                            'refunded'  => 'warning',

                            'draft'     => 'secondary',

                            default     => 'primary'

                        };

                        ?>

                        <tr>

                            <td>

                                <?= $offset + $index + 1 ?>

                            </td>

                            <td>

                                <strong>

                                    <?= htmlspecialchars($sale['sale_no']) ?>

                                </strong>

                            </td>

                            <td>

                                <?= date(
                                    'd M Y H:i',
                                    strtotime($sale['sale_date'])
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $sale['customer_name'] ?: 'Walk-in Customer'
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($sale['harvest_no']) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($sale['batch_code']) ?>

                            </td>

                            <td class="text-end">

                                ₦<?= number_format(
                                    (float)$sale['total_amount'],
                                    2
                                ) ?>

                            </td>

                            <td class="text-end text-success">

                                ₦<?= number_format(
                                    (float)$sale['amount_paid'],
                                    2
                                ) ?>

                            </td>

                            <td class="text-end">

                                <?php if ((float)$sale['balance'] > 0): ?>

                                    <span class="fw-bold text-danger">

                                        ₦<?= number_format(
                                            (float)$sale['balance'],
                                            2
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-success">

                                        Fully Paid

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span class="badge bg-<?= $badge ?>">

                                    <?= ucfirst($sale['status']) ?>

                                </span>

                            </td>

                            <td>

                                <div class="btn-group btn-group-sm">

                                    <?php if (canAccess('sales.view')): ?>

                                        <a
                                            href="view.php?id=<?= $sale['id'] ?>"
                                            class="btn btn-primary"
                                            title="View Sale">

                                            <i class="bi bi-eye"></i>

                                        </a>

                                    <?php endif; ?>

                                    <?php if (canAccess('sales.print')): ?>

                                        <a
                                            href="print.php?id=<?= $sale['id'] ?>"
                                            class="btn btn-success"
                                            title="Print Invoice">

                                            <i class="bi bi-printer"></i>

                                        </a>

                                    <?php endif; ?>

                                    <?php if (canAccess('sales.receipt')): ?>

                                        <a
                                            href="receipt.php?id=<?= $sale['id'] ?>"
                                            class="btn btn-info text-white"
                                            title="Receipt">

                                            <i class="bi bi-receipt"></i>

                                        </a>

                                    <?php endif; ?>

                                    <?php if (
                                        canAccess('sales.payment')
                                        && (float)$sale['balance'] > 0
                                    ): ?>

                                        <a
                                            href="payment.php?id=<?= $sale['id'] ?>"
                                            class="btn btn-warning"
                                            title="Add Payment">

                                            <i class="bi bi-cash-coin"></i>

                                        </a>

                                    <?php endif; ?>

                                    <?php if (
                                        canAccess('sales.refund')
                                        && $sale['status'] === 'completed'
                                    ): ?>

                                        <a
                                            href="refund.php?id=<?= $sale['id'] ?>"
                                            class="btn btn-danger"
                                            title="Refund Sale"
                                            onclick="return confirm('Are you sure you want to refund this sale?');">

                                            <i class="bi bi-arrow-counterclockwise"></i>

                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>
<!-- ==========================================================
SUMMARY STATISTICS
========================================================== -->

<?php

$totalSalesAmount = 0;
$totalPaidAmount  = 0;
$totalBalance     = 0;

foreach ($sales as $sale) {

    $totalSalesAmount += (float)$sale['total_amount'];
    $totalPaidAmount  += (float)$sale['amount_paid'];
    $totalBalance     += (float)$sale['balance'];

}

?>

<div class="row mt-4">

    <div class="col-md-4">

        <div class="card border-success shadow-sm">

            <div class="card-body text-center">

                <h6>Total Sales</h6>

                <h3 class="text-success">

                    ₦<?= number_format($totalSalesAmount, 2) ?>

                </h3>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card border-primary shadow-sm">

            <div class="card-body text-center">

                <h6>Total Paid</h6>

                <h3 class="text-primary">

                    ₦<?= number_format($totalPaidAmount, 2) ?>

                </h3>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card border-danger shadow-sm">

            <div class="card-body text-center">

                <h6>Outstanding Balance</h6>

                <h3 class="text-danger">

                    ₦<?= number_format($totalBalance, 2) ?>

                </h3>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
EXPORT OPTIONS
========================================================== -->

<div class="card shadow-sm mt-4">

    <div class="card-header">

        Export

    </div>

    <div class="card-body">

        <div class="d-flex flex-wrap gap-2">

            <a href="export_excel.php?<?= http_build_query($_GET) ?>"
               class="btn btn-success">

                <i class="bi bi-file-earmark-excel"></i>

                Export Excel

            </a>

            <a href="export_pdf.php?<?= http_build_query($_GET) ?>"
               class="btn btn-danger">

                <i class="bi bi-file-earmark-pdf"></i>

                Export PDF

            </a>

            <button
                class="btn btn-secondary"
                onclick="window.print()">

                <i class="bi bi-printer"></i>

                Print List

            </button>

        </div>

    </div>

</div>

<!-- ==========================================================
PAGINATION
========================================================== -->

<?php if ($totalPages > 1): ?>

<nav class="mt-4">

    <ul class="pagination justify-content-center">

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>

            <li class="page-item <?= $page == $i ? 'active' : '' ?>">

                <a
                    class="page-link"
                    href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">

                    <?= $i ?>

                </a>

            </li>

        <?php endfor; ?>

    </ul>

</nav>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    ------------------------------------------------------------
    Auto Refresh
    ------------------------------------------------------------
    */

    const autoRefresh = false;

    if (autoRefresh) {

        setInterval(function () {

            location.reload();

        }, 60000);

    }

});

</script>
<!-- ==========================================================
BULK ACTIONS
========================================================== -->

<div class="card shadow-sm mt-4">

    <div class="card-header bg-secondary text-white">

        <h5 class="mb-0">

            Bulk Actions

        </h5>

    </div>

    <div class="card-body">

        <div class="row align-items-center">

            <div class="col-md-4">

                <select
                    id="bulkAction"
                    class="form-select">

                    <option value="">

                        Select Action

                    </option>

                    <?php if (canAccess('sales.export')): ?>

                        <option value="excel">

                            Export Selected (Excel)

                        </option>

                        <option value="pdf">

                            Export Selected (PDF)

                        </option>

                    <?php endif; ?>

                    <?php if (canAccess('sales.print')): ?>

                        <option value="print">

                            Print Selected

                        </option>

                    <?php endif; ?>

                </select>

            </div>

            <div class="col-md-2">

                <button
                    type="button"
                    id="applyBulkAction"
                    class="btn btn-primary w-100">

                    Apply

                </button>

            </div>

            <div class="col-md-3">

                <label class="form-label mb-1">

                    Records Per Page

                </label>

                <select
                    id="perPage"
                    class="form-select">

                    <option value="20" <?= $perPage == 20 ? 'selected' : '' ?>>

                        20

                    </option>

                    <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>

                        50

                    </option>

                    <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>

                        100

                    </option>

                </select>

            </div>

            <div class="col-md-3">

                <label class="form-label mb-1">

                    Auto Refresh

                </label>

                <select
                    id="autoRefresh"
                    class="form-select">

                    <option value="0">

                        Disabled

                    </option>

                    <option value="30">

                        Every 30 Seconds

                    </option>

                    <option value="60">

                        Every 1 Minute

                    </option>

                    <option value="300">

                        Every 5 Minutes

                    </option>

                </select>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
FOOTER
========================================================== -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function () {

    /*
    ----------------------------------------------------------
    Auto Refresh
    ----------------------------------------------------------
    */

    const refresh = document.getElementById('autoRefresh');

    let timer = null;

    refresh.addEventListener('change', function () {

        if (timer) {

            clearInterval(timer);

        }

        const seconds = parseInt(this.value);

        if (seconds > 0) {

            timer = setInterval(function () {

                location.reload();

            }, seconds * 1000);

        }

    });

    /*
    ----------------------------------------------------------
    Records Per Page
    ----------------------------------------------------------
    */

    document.getElementById('perPage')

        .addEventListener('change', function () {

            const url = new URL(window.location);

            url.searchParams.set('per_page', this.value);

            window.location = url;

        });

    /*
    ----------------------------------------------------------
    Bulk Actions
    ----------------------------------------------------------
    */

    document.getElementById('applyBulkAction')

        .addEventListener('click', function () {

            const action = document.getElementById('bulkAction').value;

            if (!action) {

                alert('Please select a bulk action.');

                return;

            }

            alert(
                'Bulk action "' +
                action +
                '" will be implemented in Version 2.'
            );

        });

});
</script>