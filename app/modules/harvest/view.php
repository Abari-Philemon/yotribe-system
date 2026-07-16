<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Management
 * View Harvest
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('harvest');

/*
|--------------------------------------------------------------------------
| Context
|--------------------------------------------------------------------------
*/

$farm_id = farm_id();

$page_title = 'Harvest Details';

$harvest_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$harvest_id) {

    $_SESSION['error'] = 'Invalid harvest selected.';

    header('Location: history.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Harvest Information
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../helpers/harvest_helper.php';

$harvest = getHarvestById(
    $pdo,
    $harvest_id,
    $farm_id
);

if (!$harvest) {

    $_SESSION['error'] = 'Harvest not found.';

    header('Location: history.php');

    exit;

}

$ponds = getHarvestPonds(
    $pdo,
    $harvest_id
);

$logs = getHarvestLogs(
    $pdo,
    $harvest_id
);

$summary = harvestSummary(
    $ponds
);

/*
|--------------------------------------------------------------------------
| Participating Ponds
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT

    hp.*,

    pt.pond_code,

    ps.current_count

FROM harvest_ponds hp

INNER JOIN ponds_tanks pt

    ON pt.id = hp.pond_id

INNER JOIN pond_stocking ps

    ON ps.id = hp.pond_stocking_id

WHERE

    hp.harvest_id = ?

ORDER BY

    pt.pond_code ASC

");

$stmt->execute([

    $harvest_id

]);

$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Audit Logs
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

SELECT *

FROM harvest_logs

WHERE harvest_id = ?

ORDER BY created_at DESC

");

$stmt->execute([

    $harvest_id

]);

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/sidebar.php';

?>

<div class="container-fluid py-4">

    <!-- =======================================================
        PAGE HEADER
    ======================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="mb-1">

                Harvest Details

            </h3>

            <small class="text-muted">

                <?= htmlspecialchars($harvest['harvest_no']) ?>

            </small>

        </div>

        <div>

            <a href="history.php"
               class="btn btn-outline-secondary">

                <i class="bi bi-arrow-left"></i>

                Back

            </a>

        </div>

    </div>

    <!-- =======================================================
        SUMMARY CARDS
    ======================================================== -->

    <div class="row mb-4">

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body">

                    <small class="text-muted">

                        Harvest Number

                    </small>

                    <h5>

                        <?= htmlspecialchars($harvest['harvest_no']) ?>

                    </h5>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body">

                    <small class="text-muted">

                        Batch

                    </small>

                    <h5>

                        <?= htmlspecialchars($harvest['batch_code']) ?>

                    </h5>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body">

                    <small class="text-muted">

                        Participating Ponds

                    </small>

                    <h5>

                        <?= count($ponds) ?>

                    </h5>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body">

                    <small class="text-muted">

                        Status

                    </small>

                    <h5>

                        <span class="badge bg-success">

                            <?= strtoupper($harvest['status']) ?>

                        </span>

                    </h5>

                </div>

            </div>

        </div>

    </div>
        <!-- =======================================================
        HARVEST INFORMATION
    ======================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-success text-white">

            <h5 class="mb-0">

                <i class="bi bi-info-circle"></i>

                Harvest Information

            </h5>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-3 mb-3">

                    <label class="form-label fw-bold">

                        Farm

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars($harvest['farm_name']) ?>"
                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label fw-bold">

                        Fish Batch

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars($harvest['batch_code']) ?>"
                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label fw-bold">

                        Species

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars($harvest['species']) ?>"
                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label fw-bold">

                        Source

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= ucfirst(htmlspecialchars($harvest['source'])) ?>"
                        readonly>

                </div>

            </div>

            <div class="row">

                <div class="col-md-3 mb-3">

                    <label class="form-label fw-bold">

                        Harvest Date

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= date('d M Y', strtotime($harvest['harvest_date'])) ?>"
                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label fw-bold">

                        Initial Fish

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= number_format($harvest['initial_count']) ?>"
                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label fw-bold">

                        Current Fish

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= number_format($harvest['current_count']) ?>"
                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label fw-bold">

                        Status

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= strtoupper($harvest['status']) ?>"
                        readonly>

                </div>

            </div>

        </div>

    </div>



    <!-- =======================================================
        PARTICIPATING PONDS
    ======================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            <h5 class="mb-0">

                <i class="bi bi-water"></i>

                Participating Ponds

            </h5>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table
                    class="table table-bordered table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>Pond</th>

                            <th class="text-end">

                                Current Fish

                            </th>

                            <th>

                                Harvest Start

                            </th>

                            <th>

                                Harvest End

                            </th>

                            <th>

                                Remarks

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($ponds as $pond): ?>

                            <tr>

                                <td>

                                    <?= htmlspecialchars($pond['pond_code']) ?>

                                </td>

                                <td class="text-end">

                                    <?= number_format($pond['current_count']) ?>

                                </td>

                                <td>

                                    <?= date(
                                        'H:i',
                                        strtotime($pond['harvest_start'])
                                    ) ?>

                                </td>

                                <td>

                                    <?= date(
                                        'H:i',
                                        strtotime($pond['harvest_end'])
                                    ) ?>

                                </td>

                                <td>

                                    <?= $pond['remarks']
                                        ? htmlspecialchars($pond['remarks'])
                                        : '<span class="text-muted">—</span>' ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>



    <!-- =======================================================
        GENERAL REMARKS
    ======================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <h5 class="mb-0">

                <i class="bi bi-chat-left-text"></i>

                General Remarks

            </h5>

        </div>

        <div class="card-body">

            <?= !empty($harvest['remarks'])
                ? nl2br(htmlspecialchars($harvest['remarks']))
                : '<span class="text-muted">No remarks available.</span>' ?>

        </div>

    </div>
        <!-- =======================================================
        HARVEST ACTIVITY LOG
    ======================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-secondary text-white">

            <h5 class="mb-0">

                <i class="bi bi-clock-history"></i>

                Harvest Activity Log

            </h5>

        </div>

        <div class="card-body">

            <?php if (empty($logs)): ?>

                <div class="alert alert-warning mb-0">

                    No activity has been recorded.

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-striped table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th width="18%">

                                    Date / Time

                                </th>

                                <th width="15%">

                                    Action

                                </th>

                                <th>

                                    Description

                                </th>

                                <th width="12%">

                                    Staff

                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($logs as $log): ?>

                                <tr>

                                    <td>

                                        <?= date(
                                            'd M Y H:i',
                                            strtotime($log['created_at'])
                                        ) ?>

                                    </td>

                                    <td>

                                        <?php

                                        $badge = 'secondary';

                                        switch ($log['action']) {

                                            case 'CREATE':
                                                $badge = 'success';
                                                break;

                                            case 'UPDATE':
                                                $badge = 'primary';
                                                break;

                                            case 'SALE':
                                                $badge = 'warning';
                                                break;

                                            case 'CLOSE':
                                                $badge = 'danger';
                                                break;

                                        }

                                        ?>

                                        <span class="badge bg-<?= $badge ?>">

                                            <?= htmlspecialchars($log['action']) ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars($log['description']) ?>

                                    </td>

                                    <td>

                                        <?= (int)$log['staff_id'] ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>



    <!-- =======================================================
        MANAGEMENT ACTIONS
    ======================================================== -->

    <div class="card shadow-sm">

        <div class="card-header">

            <h5 class="mb-0">

                <i class="bi bi-gear"></i>

                Harvest Actions

            </h5>

        </div>

        <div class="card-body">

            <div class="d-flex flex-wrap gap-2">

                <!-- Print -->

                <a
                    href="print.php?id=<?= $harvest_id ?>"
                    class="btn btn-outline-primary">

                    <i class="bi bi-printer"></i>

                    Print

                </a>

                <!-- History -->

                <a
                    href="history.php"
                    class="btn btn-outline-secondary">

                    <i class="bi bi-clock-history"></i>

                    History

                </a>

                <!-- Reports -->

                <a
                    href="report.php"
                    class="btn btn-outline-info">

                    <i class="bi bi-bar-chart"></i>

                    Reports

                </a>

                <?php if ($harvest['is_open']): ?>

                    <a
                        href="close.php?id=<?= $harvest_id ?>"
                        class="btn btn-danger"

                        onclick="return confirm(
                            'Close this harvest?\n\nAfter closing, no more sales can be recorded.'
                        );">

                        <i class="bi bi-lock-fill"></i>

                        Close Harvest

                    </a>

                <?php else: ?>

                    <button
                        class="btn btn-success"
                        disabled>

                        <i class="bi bi-check-circle"></i>

                        Harvest Closed

                    </button>

                <?php endif; ?>

            </div>

        </div>

    </div>
        <!-- =======================================================
        PAGE INFORMATION
    ======================================================== -->

    <div class="row mt-4">

        <div class="col-md-6">

            <div class="card border-0 bg-light">

                <div class="card-body">

                    <small class="text-muted">

                        <strong>Created By:</strong>

                        <?= (int)$harvest['created_by'] ?>

                        <br>

                        <strong>Created At:</strong>

                        <?= date(
                            'd M Y H:i',
                            strtotime($harvest['created_at'])
                        ) ?>

                    </small>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card border-0 bg-light">

                <div class="card-body text-end">

                    <small class="text-muted">

                        Harvest #

                        <strong>

                            <?= htmlspecialchars($harvest['harvest_no']) ?>

                        </strong>

                    </small>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', () => {

    /*
    ----------------------------------------------------------
    Auto-hide Success Messages
    ----------------------------------------------------------
    */

    const alerts = document.querySelectorAll('.alert-success');

    alerts.forEach(alert => {

        setTimeout(() => {

            alert.classList.add('fade');

            setTimeout(() => {

                alert.remove();

            }, 300);

        }, 4000);

    });

    /*
    ----------------------------------------------------------
    Bootstrap Tooltips
    ----------------------------------------------------------
    */

    const tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );

    tooltipTriggerList.map(function (tooltipTriggerEl) {

        return new bootstrap.Tooltip(tooltipTriggerEl);

    });

});
</script>