<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * Dashboard
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';


require_permission('sales');

$farm_id = farm_id();

$page_title = 'Sales Dashboard';

/*
|--------------------------------------------------------------------------
| Today's Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT

    COUNT(*) AS total_sales,

    COALESCE(SUM(total_amount),0) AS revenue,

    COALESCE(SUM(balance),0) AS outstanding

FROM sales

WHERE

    farm_id = ?

AND DATE(sale_date)=CURDATE()

AND status='completed'
");

$stmt->execute([$farm_id]);

$today = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Payment Summary
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT

payment_method,

COUNT(*) total,

SUM(amount) amount

FROM sale_payments

INNER JOIN sales
ON sales.id=sale_payments.sale_id

WHERE

sales.farm_id=?

AND DATE(payment_date)=CURDATE()

GROUP BY payment_method
");

$stmt->execute([$farm_id]);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Pending Sync
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT COUNT(*)

FROM sales_sync_queue

WHERE status IN ('pending','failed')
");

$stmt->execute();

$pendingSync = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Harvest Inventory Ready For Sale
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT COUNT(*)

FROM harvests

WHERE

farm_id=?

AND is_open=1
");

$stmt->execute([$farm_id]);

$openHarvests = (int)$stmt->fetchColumn();

require_once __DIR__.'/../../includes/header.php';
require_once __DIR__.'/../../includes/sidebar.php';
?>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h3>

Sales Dashboard

</h3>

<small class="text-muted">

Sales & Distribution Management

</small>

</div>

<div>

<a href="create.php"
class="btn btn-success">

<i class="bi bi-plus-circle"></i>

New Sale

</a>

</div>

</div>

<div class="row">

<div class="col-lg-3 col-md-6 mb-3">

<div class="card bg-primary text-white shadow-sm">

<div class="card-body text-center">

<h2>

<?= number_format($today['total_sales']) ?>

</h2>

<small>

Today's Sales

</small>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-3">

<div class="card bg-success text-white shadow-sm">

<div class="card-body text-center">

<h2>

₦<?= number_format((float)($today['revenue'] ?? 0), 2) ?>

</h2>

<small>

Today's Revenue

</small>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-3">

<div class="card bg-warning shadow-sm">

<div class="card-body text-center">

<h2>

₦<?= number_format($today['outstanding'],2) ?>

</h2>

<small>

Outstanding Credit

</small>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-3">

<div class="card bg-info text-white shadow-sm">

<div class="card-body text-center">

<h2>

<?= number_format($openHarvests) ?>

</h2>

<small>

Open Harvests

</small>

</div>

</div>

</div>

</div>
<!-- ===========================================================
    PAYMENT SUMMARY
=========================================================== -->

<div class="row">

    <div class="col-lg-4 mb-4">

        <div class="card shadow-sm">

            <div class="card-header bg-success text-white">

                <h5 class="mb-0">

                    <i class="bi bi-cash-stack"></i>

                    Payment Summary

                </h5>

            </div>

            <div class="card-body p-0">

                <table class="table table-striped table-hover mb-0">

                    <thead>

                        <tr>

                            <th>Method</th>

                            <th class="text-end">Transactions</th>

                            <th class="text-end">Amount</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($payments)): ?>

                        <tr>

                            <td colspan="3" class="text-center text-muted">

                                No payments today.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($payments as $payment): ?>

                            <tr>

                                <td>

                                    <?= ucfirst(htmlspecialchars($payment['payment_method'])) ?>

                                </td>

                                <td class="text-end">

                                    <?= number_format($payment['total']) ?>

                                </td>

                                <td class="text-end">

                                    ₦<?= number_format($payment['amount'],2) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

<?php

/*
|--------------------------------------------------------------------------
| Recent Sales
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    s.id,

    s.sale_no,

    s.customer_name,

    s.total_amount,

    s.sale_date,

    s.status

FROM sales s

WHERE

    s.farm_id=?

ORDER BY

    s.sale_date DESC

LIMIT 10

");

$stmt->execute([$farm_id]);

$recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

    <!-- =======================================================
         RECENT SALES
    ======================================================== -->

    <div class="col-lg-8 mb-4">

        <div class="card shadow-sm">

            <div class="card-header bg-primary text-white">

                <h5 class="mb-0">

                    <i class="bi bi-clock-history"></i>

                    Recent Sales

                </h5>

            </div>

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead>

                        <tr>

                            <th>Sale No</th>

                            <th>Customer</th>

                            <th>Date</th>

                            <th class="text-end">Amount</th>

                            <th>Status</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php if (empty($recentSales)): ?>

                            <tr>

                                <td colspan="5"
                                    class="text-center text-muted">

                                    No sales recorded.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($recentSales as $sale): ?>

                                <tr>

                                    <td>

                                        <a href="view.php?id=<?= $sale['id'] ?>">

                                            <?= htmlspecialchars($sale['sale_no']) ?>

                                        </a>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $sale['customer_name'] ?: 'Walk-in Customer'
                                        ) ?>

                                    </td>

                                    <td>

                                        <?= date(
                                            'd M Y H:i',
                                            strtotime($sale['sale_date'])
                                        ) ?>

                                    </td>

                                    <td class="text-end">

                                        ₦<?= number_format(
                                            $sale['total_amount'],
                                            2
                                        ) ?>

                                    </td>

                                    <td>

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

                                            <?= ucfirst(
                                                htmlspecialchars($sale['status'])
                                            ) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- ===========================================================
    QUICK ACTIONS & SYNC STATUS
=========================================================== -->

<div class="row">

    <div class="col-lg-6 mb-4">

        <div class="card shadow-sm">

            <div class="card-header bg-dark text-white">

                <h5 class="mb-0">

                    Quick Actions

                </h5>

            </div>

            <div class="card-body d-grid gap-2">

                <a href="create.php"
                   class="btn btn-success">

                    <i class="bi bi-plus-circle"></i>

                    New Sale

                </a>

                <a href="history.php"
                   class="btn btn-primary">

                    <i class="bi bi-clock-history"></i>

                    Sales History

                </a>

                <a href="report.php"
                   class="btn btn-info text-white">

                    <i class="bi bi-graph-up"></i>

                    Sales Reports

                </a>

                <a href="receipt.php"
                   class="btn btn-secondary">

                    <i class="bi bi-receipt"></i>

                    Receipt Lookup

                </a>

            </div>

        </div>

    </div>

    <div class="col-lg-6 mb-4">

        <div class="card shadow-sm border-danger">

            <div class="card-header bg-danger text-white">

                <h5 class="mb-0">

                    Synchronization Status

                </h5>

            </div>

            <div class="card-body">

                <table class="table table-sm mb-0">

                    <tr>

                        <th>Pending Queue</th>

                        <td class="text-end">

                            <?= number_format($pendingSync) ?>

                        </td>

                    </tr>

                    <tr>

                        <th>Mode</th>

                        <td class="text-end">

                            <span class="badge bg-success">

                                ONLINE

                            </span>

                        </td>

                    </tr>

                    <tr>

                        <th>Last Sync</th>

                        <td class="text-end">

                            <span id="lastSync">

                                Just Now

                            </span>

                        </td>

                    </tr>

                </table>

            </div>

        </div>

    </div>

</div>
<?php
/*
|--------------------------------------------------------------------------
| Monthly Sales Trend
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT
    DATE_FORMAT(sale_date,'%b') AS month_name,
    SUM(total_amount) AS revenue
FROM sales
WHERE farm_id = ?
AND YEAR(sale_date)=YEAR(CURDATE())
AND status='completed'
GROUP BY MONTH(sale_date)
ORDER BY MONTH(sale_date)
");
$stmt->execute([$farm_id]);
$salesTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Top Customers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT

customer_name,

COUNT(*) total_sales,

SUM(total_amount) total_amount

FROM sales

WHERE

farm_id=?

AND customer_name IS NOT NULL

AND status='completed'

GROUP BY customer_name

ORDER BY total_amount DESC

LIMIT 10
");
$stmt->execute([$farm_id]);

$topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Harvests Ready For Sale
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT

h.id,

h.harvest_no,

fb.batch_code,

fb.species,

h.harvest_date

FROM harvests h

INNER JOIN fish_batches fb
ON fb.id=h.fish_batch_id

WHERE

h.farm_id=?

AND h.is_open=1

ORDER BY h.harvest_date ASC
");

$stmt->execute([$farm_id]);

$readyHarvests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ==========================================================
MONTHLY SALES TREND
=========================================================== -->

<div class="row">

<div class="col-lg-8 mb-4">

<div class="card shadow-sm">

<div class="card-header bg-primary text-white">

<h5 class="mb-0">

Monthly Sales Trend

</h5>

</div>

<div class="card-body">

<canvas id="salesTrendChart"
height="110"></canvas>

</div>

</div>

</div>

<!-- ==========================================================
PAYMENT METHODS
=========================================================== -->

<div class="col-lg-4 mb-4">

<div class="card shadow-sm">

<div class="card-header bg-success text-white">

<h5 class="mb-0">

Payment Methods

</h5>

</div>

<div class="card-body">

<canvas id="paymentChart"
height="220"></canvas>

</div>

</div>

</div>

</div>

<!-- ==========================================================
READY HARVESTS
=========================================================== -->

<div class="row">

<div class="col-lg-6 mb-4">

<div class="card shadow-sm">

<div class="card-header bg-warning">

<h5 class="mb-0">

Harvests Ready For Sale

</h5>

</div>

<div class="card-body p-0">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Harvest</th>

<th>Batch</th>

<th>Date</th>

</tr>

</thead>

<tbody>

<?php if(empty($readyHarvests)): ?>

<tr>

<td colspan="3"
class="text-center text-muted">

No active harvest.

</td>

</tr>

<?php else: ?>

<?php foreach($readyHarvests as $row): ?>

<tr>

<td>

<a href="../harvest/view.php?id=<?= $row['id'] ?>">

<?= htmlspecialchars($row['harvest_no']) ?>

</a>

</td>

<td>

<?= htmlspecialchars($row['batch_code']) ?>

</td>

<td>

<?= date('d M Y',strtotime($row['harvest_date'])) ?>

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
TOP CUSTOMERS
=========================================================== -->

<div class="col-lg-6 mb-4">

<div class="card shadow-sm">

<div class="card-header bg-info text-white">

<h5 class="mb-0">

Top Customers

</h5>

</div>

<div class="card-body p-0">

<table class="table table-striped mb-0">

<thead>

<tr>

<th>Customer</th>

<th class="text-end">

Sales

</th>

<th class="text-end">

Revenue

</th>

</tr>

</thead>

<tbody>

<?php if(empty($topCustomers)): ?>

<tr>

<td colspan="3"
class="text-center text-muted">

No customer sales.

</td>

</tr>

<?php else: ?>

<?php foreach($topCustomers as $customer): ?>

<tr>

<td>

<?= htmlspecialchars($customer['customer_name']) ?>

</td>

<td class="text-end">

<?= number_format($customer['total_sales']) ?>

</td>

<td class="text-end">

₦<?= number_format($customer['total_amount'],2) ?>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const salesLabels = <?= json_encode(array_column($salesTrend,'month_name')); ?>;

const salesData = <?= json_encode(array_map('floatval',array_column($salesTrend,'revenue'))); ?>;

new Chart(document.getElementById('salesTrendChart'),{

type:'line',

data:{

labels:salesLabels,

datasets:[{

label:'Revenue',

data:salesData,

fill:false,

tension:0.3

}]

}

});

const paymentLabels = <?= json_encode(array_map(
fn($p)=>ucfirst($p['payment_method']),
$payments
)); ?>;

const paymentValues = <?= json_encode(array_map(
fn($p)=>(float)$p['amount'],
$payments
)); ?>;

new Chart(document.getElementById('paymentChart'),{

type:'doughnut',

data:{

labels:paymentLabels,

datasets:[{

data:paymentValues

}]

}

});

</script>
<!-- ===========================================================
EXECUTIVE INSIGHTS
=========================================================== -->

<div class="row">

<div class="col-lg-8 mb-4">

<div class="card shadow-sm">

<div class="card-header bg-dark text-white">

<h5 class="mb-0">

<i class="bi bi-bar-chart-line"></i>

Executive Insights

</h5>

</div>

<div class="card-body">

<ul class="mb-0">

<li>

Today's Revenue:

<strong>

₦<?= number_format((float)$today['revenue'],2) ?>

</strong>

</li>

<li>

Total Sales Today:

<strong>

<?= number_format($today['total_sales']) ?>

</strong>

</li>

<li>

Outstanding Credit:

<strong>

₦<?= number_format((float)$today['outstanding'],2) ?>

</strong>

</li>

<li>

Harvests Available For Sale:

<strong>

<?= number_format($openHarvests) ?>

</strong>

</li>

<li>

Pending Offline Synchronization:

<strong>

<?= number_format($pendingSync) ?>

</strong>

</li>

</ul>

</div>

</div>

</div>



<!-- ===========================================================
OFFLINE SYNC STATUS
=========================================================== -->

<div class="col-lg-4 mb-4">

<div class="card border-danger shadow-sm">

<div class="card-header bg-danger text-white">

<h5 class="mb-0">

Offline Synchronization

</h5>

</div>

<div class="card-body">

<div class="d-flex justify-content-between mb-2">

<span>

Network

</span>

<span
id="networkStatus"
class="badge bg-success">

ONLINE

</span>

</div>

<div class="d-flex justify-content-between mb-2">

<span>

Pending Queue

</span>

<strong>

<?= number_format($pendingSync) ?>

</strong>

</div>

<div class="d-flex justify-content-between mb-2">

<span>

Last Sync

</span>

<strong id="lastSyncTime">

<?= date('H:i:s') ?>

</strong>

</div>

<div class="d-grid mt-3">

<button

class="btn btn-outline-primary"

id="syncNow">

<i class="bi bi-arrow-repeat"></i>

Sync Now

</button>

</div>

</div>

</div>

</div>

</div>

<!-- ===========================================================
FOOTER
=========================================================== -->

<hr>

<div class="row">

<div class="col-md-6">

<small class="text-muted">

Generated:

<?= date('d M Y H:i:s') ?>

</small>

</div>

<div class="col-md-6 text-end">

<small class="text-muted">

YOTRIBE IFMS

Sales & Distribution

Version 1.0

</small>

</div>

</div>

</div>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>

<script>

/*=============================================================
Network Status
=============================================================*/

function updateNetworkStatus(){

const badge=document.getElementById('networkStatus');

if(navigator.onLine){

badge.className='badge bg-success';

badge.innerHTML='ONLINE';

}else{

badge.className='badge bg-danger';

badge.innerHTML='OFFLINE';

}

}

window.addEventListener('online',updateNetworkStatus);

window.addEventListener('offline',updateNetworkStatus);

updateNetworkStatus();

/*=============================================================
Sync Button
=============================================================*/

document.getElementById('syncNow')

.addEventListener('click',function(){

this.disabled=true;

this.innerHTML='Synchronizing...';

setTimeout(()=>{

this.disabled=false;

this.innerHTML='<i class="bi bi-arrow-repeat"></i> Sync Now';

document.getElementById('lastSyncTime').innerHTML=
new Date().toLocaleTimeString();

},1500);

});

</script>