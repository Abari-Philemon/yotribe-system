<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Management
 * Print Harvest Report
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/../../helpers/harvest_helper.php';

require_permission('harvest');

/*
|--------------------------------------------------------------------------
| Context
|--------------------------------------------------------------------------
*/

$farm_id = farm_id();

$harvest_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$harvest_id) {

    exit('Invalid harvest.');

}

/*
|--------------------------------------------------------------------------
| Load Harvest
|--------------------------------------------------------------------------
*/

$harvest = getHarvestById(
    $pdo,
    $harvest_id,
    $farm_id
);

if (!$harvest) {

    exit('Harvest not found.');

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

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>

Harvest Report

</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{

    font-size:13px;

    background:#fff;

}

.report-title{

    text-align:center;

    margin-bottom:20px;

}

.report-title h2{

    margin-bottom:5px;

}

.table th{

    background:#f5f5f5;

}

.signature-box{

    height:80px;

    border-bottom:1px solid #000;

}

@media print{

    .no-print{

        display:none;

    }

}

</style>

</head>

<body>

<div class="container mt-4">

<div class="text-end mb-3 no-print">

<button

onclick="window.print();"

class="btn btn-success">

<i class="bi bi-printer"></i>

Print Report

</button>

</div>

<div class="report-title">

<h2>

YOTRIBE AGRO ALLIED SERVICES

</h2>

<h4>

HARVEST REPORT

</h4>

<small>

Generated:

<?= date('d M Y H:i') ?>

</small>

</div>
<!-- ===========================================================
    HARVEST INFORMATION
=========================================================== -->

<div class="card mb-4">

    <div class="card-header">

        <strong>Harvest Information</strong>

    </div>

    <div class="card-body">

        <table class="table table-bordered">

            <tr>

                <th width="25%">Harvest Number</th>

                <td><?= htmlspecialchars($harvest['harvest_no']) ?></td>

                <th width="20%">Harvest Date</th>

                <td><?= date('d M Y', strtotime($harvest['harvest_date'])) ?></td>

            </tr>

            <tr>

                <th>Farm</th>

                <td><?= htmlspecialchars($harvest['farm_name']) ?></td>

                <th>Status</th>

                <td><?= strtoupper(formatHarvestStatus($harvest['status'])) ?></td>

            </tr>

            <tr>

                <th>Batch Code</th>

                <td><?= htmlspecialchars($harvest['batch_code']) ?></td>

                <th>Species</th>

                <td><?= htmlspecialchars($harvest['species']) ?></td>

            </tr>

            <tr>

                <th>Source</th>

                <td><?= ucfirst(htmlspecialchars($harvest['source'])) ?></td>

                <th>Average Weight</th>

                <td><?= number_format((float)$harvest['avg_weight_g'], 2) ?> g</td>

            </tr>

        </table>

    </div>

</div>



<!-- ===========================================================
    HARVEST SUMMARY
=========================================================== -->

<div class="row mb-4">

    <div class="col-md-4">

        <div class="card text-center">

            <div class="card-body">

                <h3>

                    <?= number_format($summary['ponds']) ?>

                </h3>

                <small>

                    Participating Ponds

                </small>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card text-center">

            <div class="card-body">

                <h3>

                    <?= number_format($summary['fish']) ?>

                </h3>

                <small>

                    Estimated Fish

                </small>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card text-center">

            <div class="card-body">

                <h3>

                    <?= strtoupper(formatHarvestStatus($harvest['status'])) ?>

                </h3>

                <small>

                    Harvest Status

                </small>

            </div>

        </div>

    </div>

</div>



<!-- ===========================================================
    PARTICIPATING PONDS
=========================================================== -->

<div class="card mb-4">

    <div class="card-header">

        <strong>

            Participating Ponds

        </strong>

    </div>

    <div class="card-body p-0">

        <table class="table table-bordered table-sm mb-0">

            <thead>

                <tr>

                    <th width="15%">

                        Pond

                    </th>

                    <th width="15%">

                        Current Fish

                    </th>

                    <th width="15%">

                        Harvest Start

                    </th>

                    <th width="15%">

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

                            <?php if (!empty($pond['remarks'])): ?>

                                <?= htmlspecialchars($pond['remarks']) ?>

                            <?php else: ?>

                                <span class="text-muted">

                                    —

                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>



<!-- ===========================================================
    GENERAL REMARKS
=========================================================== -->

<div class="card mb-4">

    <div class="card-header">

        <strong>

            General Remarks

        </strong>

    </div>

    <div class="card-body">

        <?php if (!empty($harvest['remarks'])): ?>

            <?= nl2br(htmlspecialchars($harvest['remarks'])) ?>

        <?php else: ?>

            <span class="text-muted">

                No remarks recorded.

            </span>

        <?php endif; ?>

    </div>

</div>
<!-- ===========================================================
    HARVEST ACTIVITY LOG
=========================================================== -->

<div class="card mb-4">

    <div class="card-header">

        <strong>

            Harvest Activity Log

        </strong>

    </div>

    <div class="card-body p-0">

        <?php if (empty($logs)): ?>

            <div class="p-3 text-muted">

                No activity recorded.

            </div>

        <?php else: ?>

            <table class="table table-bordered table-sm mb-0">

                <thead>

                    <tr>

                        <th width="20%">

                            Date / Time

                        </th>

                        <th width="15%">

                            Action

                        </th>

                        <th>

                            Description

                        </th>

                        <th width="20%">

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

                                <?= strtoupper(
                                    htmlspecialchars($log['action'])
                                ) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars($log['description']) ?>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $log['staff_name'] ?? 'N/A'
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</div>



<!-- ===========================================================
    REPORT SUMMARY
=========================================================== -->

<div class="card mb-4">

    <div class="card-header">

        <strong>

            Harvest Summary

        </strong>

    </div>

    <div class="card-body">

        <table class="table table-bordered">

            <tr>

                <th width="30%">

                    Participating Ponds

                </th>

                <td>

                    <?= number_format($summary['ponds']) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Estimated Fish Harvested

                </th>

                <td>

                    <?= number_format($summary['fish']) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Harvest Status

                </th>

                <td>

                    <?= strtoupper(
                        formatHarvestStatus($harvest['status'])
                    ) ?>

                </td>

            </tr>

            <tr>

                <th>

                    Report Generated

                </th>

                <td>

                    <?= date('d M Y H:i') ?>

                </td>

            </tr>

        </table>

    </div>

</div>



<!-- ===========================================================
    APPROVAL SECTION
=========================================================== -->

<div class="row mt-5">

    <div class="col-md-4 text-center">

        <div class="signature-box"></div>

        <strong>

            Prepared By

        </strong>

        <br>

        <small>

            Harvest Officer

        </small>

    </div>

    <div class="col-md-4 text-center">

        <div class="signature-box"></div>

        <strong>

            Verified By

        </strong>

        <br>

        <small>

            Farm Manager

        </small>

    </div>

    <div class="col-md-4 text-center">

        <div class="signature-box"></div>

        <strong>

            Approved By

        </strong>

        <br>

        <small>

            Director / Owner

        </small>

    </div>

</div>



<!-- ===========================================================
    DOCUMENT NOTES
=========================================================== -->

<div class="mt-5">

    <small class="text-muted">

        This report is automatically generated by the
        <strong>YOTRIBE Integrated Farm Management System (IFMS)</strong>.

        It serves as the official harvest record for this batch and
        should be retained for operational, financial, and audit
        purposes.

    </small>

</div>
<!-- ===========================================================
    REPORT FOOTER
=========================================================== -->

<hr class="mt-5">

<div class="row">

    <div class="col-6">

        <small class="text-muted">

            Document Number:

            <strong>

                <?= htmlspecialchars($harvest['harvest_no']) ?>

            </strong>

        </small>

    </div>

    <div class="col-6 text-end">

        <small class="text-muted">

            Printed:

            <?= date('d M Y H:i:s') ?>

        </small>

    </div>

</div>

<div class="row mt-2">

    <div class="col-12 text-center">

        <small class="text-muted">

            YOTRIBE Integrated Farm Management System (IFMS)

            <br>

            Harvest Management Module

            <br>

            Confidential Internal Document

        </small>

    </div>

</div>

</div>

<!-- ===========================================================
    PRINT JAVASCRIPT
=========================================================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

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

/*
--------------------------------------------------------------
Optional Auto Print

Uncomment if desired

window.onload = function(){

    window.print();

};

--------------------------------------------------------------
*/

</script>

</body>

</html>