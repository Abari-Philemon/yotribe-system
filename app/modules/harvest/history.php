<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Management
 * Harvest History
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('harvest');

$farm_id = farm_id();

$page_title = 'Harvest History';

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$status = trim($_GET['status'] ?? '');

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| Query
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    h.id,

    h.harvest_no,

    h.harvest_date,

    h.status,

    h.is_open,

    h.created_at,

    fb.batch_code,

    fb.species,

    COUNT(hp.id) AS ponds

FROM harvests h

INNER JOIN fish_batches fb

    ON fb.id = h.fish_batch_id

LEFT JOIN harvest_ponds hp

    ON hp.harvest_id = h.id

WHERE

    h.farm_id = :farm_id

";

$params = [

    ':farm_id' => $farm_id

];

if ($status !== '') {

    $sql .= " AND h.status = :status ";

    $params[':status'] = $status;

}

if ($search !== '') {

    $sql .= "

    AND (

        h.harvest_no LIKE :search

        OR fb.batch_code LIKE :search

    )

    ";

    $params[':search'] = "%{$search}%";

}

$sql .= "

GROUP BY h.id

ORDER BY h.created_at DESC

";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$harvests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="mb-1">

            Harvest History

            </h3>

            <small class="text-muted">

            All Harvest Records

            </small>

        </div>

        <div>

            <a href="create.php"
            class="btn btn-success">

            <i class="bi bi-plus-circle"></i>

            New Harvest

            </a>

        </div>

    /div>

<!-- =====================================================
FILTERS
====================================================== -->

<div class="card shadow-sm mb-4">

<div class="card-body">

<form class="row g-3">

<div class="col-md-4">

<input
type="text"
name="search"
class="form-control"
placeholder="Search Harvest No or Batch"
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-3">

<select
name="status"
class="form-select">

<option value="">

All Status

</option>

<option
value="selling"
<?= $status=='selling'?'selected':'' ?>>

Selling

</option>

<option
value="closed"
<?= $status=='closed'?'selected':'' ?>>

Closed

</option>

</select>

</div>

<div class="col-md-2">

<button
class="btn btn-primary w-100">

Search

</button>

</div>

<div class="col-md-2">

<a
href="history.php"
class="btn btn-secondary w-100">

Reset

</a>

</div>

</form>

</div>

</div>

<!-- ===========================================================
    HARVEST HISTORY
=========================================================== -->

<div class="card shadow-sm">

    <div class="card-header bg-primary text-white">

        <h5 class="mb-0">

            <i class="bi bi-clock-history"></i>

            Harvest Records

        </h5>

    </div>

    <div class="card-body">

        <?php if (empty($harvests)): ?>

            <div class="alert alert-warning mb-0">

                No harvest records found.

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-striped table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th width="12%">

                                Harvest No

                            </th>

                            <th width="12%">

                                Batch

                            </th>

                            <th width="12%">

                                Species

                            </th>

                            <th width="12%">

                                Harvest Date

                            </th>

                            <th class="text-center" width="8%">

                                Ponds

                            </th>

                            <th width="10%">

                                Status

                            </th>

                            <th width="15%">

                                Created

                            </th>

                            <th width="19%" class="text-center">

                                Actions

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($harvests as $harvest): ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars($harvest['harvest_no']) ?>

                                    </strong>

                                </td>

                                <td>

                                    <?= htmlspecialchars($harvest['batch_code']) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars($harvest['species']) ?>

                                </td>

                                <td>

                                    <?= date(
                                        'd M Y',
                                        strtotime($harvest['harvest_date'])
                                    ) ?>

                                </td>

                                <td class="text-center">

                                    <span class="badge bg-info">

                                        <?= number_format($harvest['ponds']) ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if ($harvest['is_open']): ?>

                                        <span class="badge bg-success">

                                            SELLING

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">

                                            CLOSED

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?= date(
                                        'd M Y H:i',
                                        strtotime($harvest['created_at'])
                                    ) ?>

                                </td>

                                <td>

                                    <div class="btn-group btn-group-sm">

                                        <!-- View -->

                                        <a
                                            href="view.php?id=<?= $harvest['id'] ?>"
                                            class="btn btn-outline-primary"
                                            title="View Harvest">

                                            <i class="bi bi-eye"></i>

                                        </a>

                                        <!-- Print -->

                                        <a
                                            href="print.php?id=<?= $harvest['id'] ?>"
                                            class="btn btn-outline-dark"
                                            title="Print">

                                            <i class="bi bi-printer"></i>

                                        </a>

                                        <?php if ($harvest['is_open']): ?>

                                            <!-- Close -->

                                            <form
                                                action="close.php"
                                                method="POST"
                                                class="d-inline">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                                <input
                                                    type="hidden"
                                                    name="harvest_id"
                                                    value="<?= $harvest['id'] ?>">

                                                <button
                                                    type="submit"
                                                    class="btn btn-outline-danger"
                                                    title="Close Harvest"

                                                    onclick="return confirm(
                                                        'Close this harvest?\n\nNo further sales will be allowed.'
                                                    );">

                                                    <i class="bi bi-lock"></i>

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <button
                                                class="btn btn-outline-success"
                                                disabled
                                                title="Harvest Closed">

                                                <i class="bi bi-check-circle"></i>

                                            </button>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>
<!-- ===========================================================
    HARVEST SUMMARY
=========================================================== -->

<div class="row mt-4">

    <?php

    $totalHarvests = count($harvests);

    $openHarvests = 0;

    $closedHarvests = 0;

    foreach ($harvests as $item) {

        if ((int)$item['is_open'] === 1) {

            $openHarvests++;

        } else {

            $closedHarvests++;

        }

    }

    ?>

    <div class="col-md-4 mb-3">

        <div class="card border-0 bg-success text-white shadow-sm">

            <div class="card-body text-center">

                <h2>

                    <?= number_format($totalHarvests) ?>

                </h2>

                <small>

                    Total Harvests

                </small>

            </div>

        </div>

    </div>

    <div class="col-md-4 mb-3">

        <div class="card border-0 bg-primary text-white shadow-sm">

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

    <div class="col-md-4 mb-3">

        <div class="card border-0 bg-secondary text-white shadow-sm">

            <div class="card-body text-center">

                <h2>

                    <?= number_format($closedHarvests) ?>

                </h2>

                <small>

                    Closed Harvests

                </small>

            </div>

        </div>

    </div>

</div>



<!-- ===========================================================
    PAGE FOOTER
=========================================================== -->

<div class="mt-4">

    <small class="text-muted">

        Showing

        <strong>

            <?= number_format($totalHarvests) ?>

        </strong>

        harvest record(s).

    </small>

</div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', () => {

    /*
    ------------------------------------------------------------
    Enable Bootstrap Tooltips
    ------------------------------------------------------------
    */

    const tooltipTriggerList = [].slice.call(

        document.querySelectorAll('[title]')

    );

    tooltipTriggerList.map(function (element) {

        return new bootstrap.Tooltip(element);

    });

    /*
    ------------------------------------------------------------
    Auto-hide Success Messages
    ------------------------------------------------------------
    */

    document.querySelectorAll('.alert-success')

        .forEach(alert => {

            setTimeout(() => {

                alert.classList.add('fade');

                setTimeout(() => {

                    alert.remove();

                }, 300);

            }, 4000);

        });

});
</script>