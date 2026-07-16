<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * Create Sale
 * Version 1.0
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('sales');

$farm_id  = farm_id();
$staff_id = $_SESSION['staff_id'];

$page_title = 'Create Sale';

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}

/*
|--------------------------------------------------------------------------
| Generate Sale Number
|--------------------------------------------------------------------------
*/

$today = date('Ymd');

$stmt = $pdo->prepare("
SELECT COUNT(*) + 1
FROM sales
WHERE DATE(sale_date)=CURDATE()
");

$stmt->execute();

$sequence = str_pad(
    (string)$stmt->fetchColumn(),
    4,
    '0',
    STR_PAD_LEFT
);

$sale_no = "SAL-{$today}-{$sequence}";

/*
|--------------------------------------------------------------------------
| Open Harvests Ready For Sale
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
    ON fb.id = h.fish_batch_id

WHERE

    h.farm_id = ?

AND h.is_open = 1

ORDER BY h.harvest_date ASC
");

$stmt->execute([$farm_id]);

$harvests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Sale Types
|--------------------------------------------------------------------------
*/

$saleTypes = [

    'customer_sale'      => 'Customer Sale',

    'staff_share'        => 'Staff Share',

    'company_use'        => 'Company Use',

    'donation'           => 'Donation',

    'promotion'          => 'Promotion',

    'mortality_disposal' => 'Mortality Disposal',

    'return'             => 'Return'

];

/*
|--------------------------------------------------------------------------
| Payment Methods
|--------------------------------------------------------------------------
*/

$paymentMethods = [

    'cash'     => 'Cash',

    'transfer' => 'Bank Transfer',

    'pos'      => 'POS',

    'wallet'   => 'Wallet',

    'credit'   => 'Credit'

];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container-fluid py-4">

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h3 class="mb-1">

            Create Sale

        </h3>

        <small class="text-muted">

            Sales & Distribution Management

        </small>

    </div>

    <a href="dashboard.php"
       class="btn btn-outline-secondary">

        <i class="bi bi-arrow-left"></i>

        Dashboard

    </a>

</div>
<form action="save.php" method="POST" id="salesForm">

<input
type="hidden"
name="csrf_token"
value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

<input
type="hidden"
name="sale_no"
value="<?= htmlspecialchars($sale_no) ?>">

<!-- ==========================================================
SALE INFORMATION
=========================================================== -->

<div class="card shadow-sm mb-4">

<div class="card-header bg-success text-white">

<h5 class="mb-0">

Sale Information

</h5>

</div>

<div class="card-body">

<div class="row">

<div class="col-md-3 mb-3">

<label class="form-label">

Sale Number

</label>

<input
type="text"
class="form-control"
value="<?= htmlspecialchars($sale_no) ?>"
readonly>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Sale Date

</label>

<input
type="datetime-local"
name="sale_date"
class="form-control"
value="<?= date('Y-m-d\TH:i') ?>"
required>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Sale Type

</label>

<select
name="sale_type"
id="sale_type"
class="form-select"
required>

<?php foreach($saleTypes as $key=>$value): ?>

<option value="<?= $key ?>">

<?= htmlspecialchars($value) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Harvest

</label>

<select
name="harvest_id"
id="harvest_id"
class="form-select"
required>

<option value="">

Select Harvest

</option>

<?php foreach($harvests as $harvest): ?>

<option
value="<?= $harvest['id'] ?>">

<?= htmlspecialchars($harvest['harvest_no']) ?>

-

<?= htmlspecialchars($harvest['batch_code']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

</div>

</div>

<!-- ==========================================================
CUSTOMER INFORMATION
=========================================================== -->

<div class="card shadow-sm mb-4">

<div class="card-header bg-primary text-white">

<h5 class="mb-0">

Customer Information

</h5>

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4 mb-3">

<label class="form-label">

Customer Name

</label>

<input
type="text"
name="customer_name"
class="form-control"
placeholder="Walk-in Customer">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Phone Number

</label>

<input
type="text"
name="customer_phone"
class="form-control">

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Address

</label>

<input
type="text"
name="customer_address"
class="form-control">

</div>

</div>

</div>

</div>

<!-- ==========================================================
SALE ITEMS
=========================================================== -->

<div class="card shadow-sm mb-4">

<div class="card-header bg-info text-white d-flex justify-content-between">

<h5 class="mb-0">

Sale Items

</h5>

<button
type="button"
class="btn btn-light btn-sm"
id="addItem">

<i class="bi bi-plus-circle"></i>

Add Item

</button>

</div>

<div class="card-body p-0">

<div class="table-responsive">

<table class="table table-bordered mb-0">

<thead>

<tr>

<th width="25%">

Harvest Pond

</th>

<th width="10%">

Fish

</th>

<th width="15%">

Weight (Kg)

</th>

<th width="15%">

Unit Price

</th>

<th width="15%">

Total

</th>

<th width="20%">

Remarks

</th>

<th width="5%"></th>

</tr>

</thead>

<tbody id="saleItems">

<!-- AJAX -->

</tbody>

</table>

</div>

</div>

</div>

<!-- ==========================================================
PAYMENT INFORMATION
=========================================================== -->

<div class="card shadow-sm mb-4">

<div class="card-header bg-warning">

<h5 class="mb-0">

Payment Information

</h5>

</div>

<div class="card-body">

<div class="row">

<div class="col-md-4">

<label>

Payment Method

</label>

<select
name="payment_method"
class="form-select">

<?php foreach($paymentMethods as $key=>$value): ?>

<option value="<?= $key ?>">

<?= htmlspecialchars($value) ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-4">

<label>

Amount Paid

</label>

<input
type="number"
step="0.01"
name="amount_paid"
id="amount_paid"
class="form-control">

</div>

<div class="col-md-4">

<label>

Reference No.

</label>

<input
type="text"
name="reference_no"
class="form-control">

</div>

</div>

</div>

</div>

<!-- ==========================================================
SALE SUMMARY
=========================================================== -->

<div class="card shadow-sm mb-4">

<div class="card-header">

Sale Summary

</div>

<div class="card-body">

<div class="row">

<div class="col-md-3">

<label>

Subtotal

</label>

<input
type="text"
id="subtotal"
class="form-control"
readonly>

</div>

<div class="col-md-3">

<label>

Discount

</label>

<input
type="number"
step="0.01"
name="discount"
id="discount"
class="form-control"
value="0">

</div>

<div class="col-md-3">

<label>

Grand Total

</label>

<input
type="text"
id="grand_total"
class="form-control"
readonly>

</div>

<div class="col-md-3">

<label>

Balance

</label>

<input
type="text"
id="balance"
class="form-control"
readonly>

</div>

</div>

</div>

</div>

<div class="d-flex justify-content-end">

<a
href="dashboard.php"
class="btn btn-secondary me-2">

Cancel

</a>

<button
type="submit"
class="btn btn-success">

<i class="bi bi-save"></i>

Save Sale

</button>

</div>

</form>
<!-- ==========================================================
HARVEST INVENTORY
=========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-secondary text-white">

        <h5 class="mb-0">

            Harvest Inventory

        </h5>

    </div>

    <div class="card-body">

        <div class="alert alert-info">

            Select a harvest above to load all harvested ponds and
            available fish for sale.

        </div>

        <div class="table-responsive">

            <table class="table table-bordered table-hover">

                <thead class="table-light">

                <tr>

                    <th width="15%">Pond</th>

                    <th width="15%">Harvested Fish</th>

                    <th width="15%">Available Fish</th>

                    <th width="15%">Harvest Weight (Kg)</th>

                    <th width="15%">Available Weight (Kg)</th>

                    <th width="25%">Status</th>

                </tr>

                </thead>

                <tbody id="harvestInventory">

                    <tr>

                        <td colspan="6"
                            class="text-center text-muted">

                            Select a harvest to load inventory.

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- ==========================================================
SALE ITEMS
=========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

        <h5 class="mb-0">

            Sale Items

        </h5>

        <button
            type="button"
            class="btn btn-light btn-sm"
            id="addItem">

            <i class="bi bi-plus-circle"></i>

            Add Item

        </button>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-bordered align-middle mb-0">

                <thead class="table-light">

                <tr>

                    <th width="25%">Harvest Pond</th>

                    <th width="10%">Available</th>

                    <th width="10%">Fish Sold</th>

                    <th width="15%">Weight (Kg)</th>

                    <th width="15%">Unit Price</th>

                    <th width="15%">Line Total</th>

                    <th width="10%"></th>

                </tr>

                </thead>

                <tbody id="saleItems">

                    <!-- AJAX rows -->

                </tbody>

            </table>

        </div>

    </div>

</div>
<!-- ==========================================================
SALE SUMMARY
=========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-warning">

        <h5 class="mb-0">

            Sale Summary

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-3">

                <label class="form-label">

                    Subtotal

                </label>

                <input
                    type="text"
                    id="subtotal"
                    class="form-control text-end"
                    value="0.00"
                    readonly>

            </div>

            <div class="col-md-3">

                <label class="form-label">

                    Discount

                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    id="discount"
                    name="discount"
                    class="form-control text-end"
                    value="0">

            </div>

            <div class="col-md-3">

                <label class="form-label">

                    Grand Total

                </label>

                <input
                    type="text"
                    id="grand_total"
                    class="form-control text-end fw-bold"
                    value="0.00"
                    readonly>

            </div>

            <div class="col-md-3">

                <label class="form-label">

                    Balance

                </label>

                <input
                    type="text"
                    id="balance"
                    class="form-control text-end fw-bold"
                    value="0.00"
                    readonly>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
GENERAL REMARKS
=========================================================== -->

<div class="card shadow-sm mb-4">

    <div class="card-header">

        <h5 class="mb-0">

            Remarks

        </h5>

    </div>

    <div class="card-body">

        <textarea

            name="remarks"

            rows="4"

            class="form-control"

            placeholder="Optional remarks..."></textarea>

    </div>

</div>

<!-- ==========================================================
OFFLINE STATUS
=========================================================== -->

<div class="card border-info shadow-sm mb-4">

    <div class="card-header bg-info text-white">

        <h5 class="mb-0">

            Synchronization Status

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-4">

                <strong>Network</strong>

                <br>

                <span
                    id="networkStatus"
                    class="badge bg-success">

                    ONLINE

                </span>

            </div>

            <div class="col-md-4">

                <strong>Sync Queue</strong>

                <br>

                <span id="pendingQueue">

                    0 Pending

                </span>

            </div>

            <div class="col-md-4">

                <strong>Offline Mode</strong>

                <br>

                <span class="text-muted">

                    Sales continue even when offline.

                </span>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
ACTION BUTTONS
=========================================================== -->

<div class="d-flex justify-content-between">

    <a
        href="dashboard.php"
        class="btn btn-secondary">

        <i class="bi bi-arrow-left"></i>

        Back

    </a>

    <div>

        <button
            type="reset"
            class="btn btn-warning">

            <i class="bi bi-arrow-counterclockwise"></i>

            Reset

        </button>

        <button
            type="submit"
            class="btn btn-success">

            <i class="bi bi-save"></i>

            Save Sale

        </button>

    </div>

</div>

</form>

<?php require_once __DIR__.'/../../includes/footer.php'; ?>

<script src="assets/sales.js"></script>

<script>

/*--------------------------------------------------------------
Network Status
--------------------------------------------------------------*/

function updateNetworkStatus(){

    const badge=document.getElementById('networkStatus');

    if(navigator.onLine){

        badge.className='badge bg-success';

        badge.textContent='ONLINE';

    }else{

        badge.className='badge bg-danger';

        badge.textContent='OFFLINE';

    }

}

window.addEventListener('online',updateNetworkStatus);
window.addEventListener('offline',updateNetworkStatus);

updateNetworkStatus();

</script>