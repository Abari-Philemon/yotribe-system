<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Harvest Management
 * Harvest Dashboard Report
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/../../helpers/harvest_report_helper.php';

require_permission('harvest');

$farm_id = farm_id();

$page_title = 'Harvest Reports';

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$from_date = trim($_GET['from_date'] ?? '');
$to_date   = trim($_GET['to_date'] ?? '');
$status    = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| Dashboard Data
|--------------------------------------------------------------------------
*/

$dashboard      = getHarvestDashboardStats($pdo, $farm_id);
$monthlyTrend   = getMonthlyHarvestTrend($pdo, $farm_id);
$statusSummary  = getHarvestStatusSummary($pdo, $farm_id);
$recentHarvests = getRecentHarvests($pdo, $farm_id, 10);
$topBatches     = getTopHarvestBatches($pdo, $farm_id, 5);
$topPonds       = getTopHarvestPonds($pdo, $farm_id, 5);
$revenue        = getHarvestRevenue($pdo, $farm_id);
$staffShares    = getStaffShareSummary($pdo, $farm_id);

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
         Harvest Analytics Dashboard
      </h3>
      <small class="text-muted">
      Executive Harvest Report
      </small>
   </div>
   <div>
      <a href="history.php"
         class="btn btn-outline-secondary">
      <i class="bi bi-clock-history"></i>
      Harvest History
      </a>
   </div>
</div>
<!-- ===========================================================
   FILTERS
   =========================================================== -->
<div class="card shadow-sm mb-4">
   <div class="card-body">
      <form method="GET" class="row g-3">
         <div class="col-md-3">
            <label class="form-label">
            From Date
            </label>
            <input
               type="date"
               name="from_date"
               class="form-control"
               value="<?= htmlspecialchars($from_date) ?>">
         </div>
         <div class="col-md-3">
            <label class="form-label">
            To Date
            </label>
            <input
               type="date"
               name="to_date"
               class="form-control"
               value="<?= htmlspecialchars($to_date) ?>">
         </div>
         <div class="col-md-3">
            <label class="form-label">
            Status
            </label>
            <select
               name="status"
               class="form-select">
               <option value="">All</option>
               <option
                  value="selling"
                  <?= $status === 'selling' ? 'selected' : '' ?>>
                  Selling
               </option>
               <option
                  value="closed"
                  <?= $status === 'closed' ? 'selected' : '' ?>>
                  Closed
               </option>
            </select>
         </div>
         <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">
            <i class="bi bi-search"></i>
            Generate Report
            </button>
         </div>
      </form>
   </div>
</div>
<!-- ===========================================================
   KPI CARDS
   =========================================================== -->
<div class="row">
   <div class="col-lg-3 col-md-6 mb-3">
      <div class="card border-0 shadow-sm bg-primary text-white">
         <div class="card-body text-center">
            <h2>
               <?= number_format($dashboard['total_harvests']) ?>
            </h2>
            <small>
            Total Harvests
            </small>
         </div>
      </div>
   </div>
   <div class="col-lg-3 col-md-6 mb-3">
      <div class="card border-0 shadow-sm bg-success text-white">
         <div class="card-body text-center">
            <h2>
               <?= number_format($dashboard['open_harvests']) ?>
            </h2>
            <small>
            Open Harvests
            </small>
         </div>
      </div>
   </div>
   <div class="col-lg-3 col-md-6 mb-3">
      <div class="card border-0 shadow-sm bg-secondary text-white">
         <div class="card-body text-center">
            <h2>
               <?= number_format($dashboard['closed_harvests']) ?>
            </h2>
            <small>
            Closed Harvests
            </small>
         </div>
      </div>
   </div>
   <div class="col-lg-3 col-md-6 mb-3">
      <div class="card border-0 shadow-sm bg-info text-white">
         <div class="card-body text-center">
            <h2>
               <?= number_format($dashboard['estimated_fish']) ?>
            </h2>
            <small>
            Estimated Fish Harvested
            </small>
         </div>
      </div>
   </div>
</div>
<div class="row">
   <div class="col-lg-4 mb-3">
      <div class="card shadow-sm">
         <div class="card-body text-center">
            <h3>
               <?= number_format($dashboard['participating_ponds']) ?>
            </h3>
            <small>
            Participating Ponds
            </small>
         </div>
      </div>
   </div>
   <div class="col-lg-4 mb-3">
      <div class="card shadow-sm">
         <div class="card-body text-center">
            <h3>
               ₦<?= number_format((float)$revenue['revenue'], 2) ?>
            </h3>
            <small>
            Harvest Revenue
            </small>
         </div>
      </div>
   </div>
   <div class="col-lg-4 mb-3">
      <div class="card shadow-sm">
         <div class="card-body text-center">
            <h3>
               <?= number_format((float)$staffShares['quantity_kg'], 2) ?>
               kg
            </h3>
            <small>
            Staff Shares
            </small>
         </div>
      </div>
   </div>
</div>
<!-- ===========================================================
   EXECUTIVE SUMMARY
   =========================================================== -->
<div class="row">
   <!-- Recent Harvests -->
   <div class="col-lg-8 mb-4">
      <div class="card shadow-sm">
         <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
               <i class="bi bi-clock-history"></i>
               Recent Harvests
            </h5>
         </div>
         <div class="card-body p-0">
            <div class="table-responsive">
               <table class="table table-hover table-striped mb-0">
                  <thead>
                     <tr>
                        <th>Harvest No</th>
                        <th>Batch</th>
                        <th>Date</th>
                        <th>Ponds</th>
                        <th>Status</th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php if (empty($recentHarvests)): ?>
                     <tr>
                        <td colspan="5"
                           class="text-center text-muted py-4">
                           No harvest records found.
                        </td>
                     </tr>
                     <?php else: ?>
                     <?php foreach ($recentHarvests as $item): ?>
                     <tr>
                        <td>
                           <a href="view.php?id=<?= $item['id'] ?>">
                           <?= htmlspecialchars($item['harvest_no']) ?>
                           </a>
                        </td>
                        <td>
                           <?= htmlspecialchars($item['batch_code']) ?>
                        </td>
                        <td>
                           <?= date(
                              'd M Y',
                              strtotime($item['harvest_date'])
                              ) ?>
                        </td>
                        <td class="text-center">
                           <?= number_format($item['ponds']) ?>
                        </td>
                        <td>
                           <span class="badge bg-<?= harvestStatusBadge($item['status']) ?>">
                           <?= formatHarvestStatus($item['status']) ?>
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
   <!-- Revenue Summary -->
   <div class="col-lg-4 mb-4">
      <div class="card shadow-sm">
         <div class="card-header bg-success text-white">
            <h5 class="mb-0">
               Revenue Summary
            </h5>
         </div>
         <div class="card-body">
            <table class="table table-sm">
               <tr>
                  <th>
                     Transactions
                  </th>
                  <td class="text-end">
                     <?= number_format($revenue['transactions']) ?>
                  </td>
               </tr>
               <tr>
                  <th>
                     Fish Sold
                  </th>
                  <td class="text-end">
                     <?= number_format($revenue['quantity_kg'],2) ?>
                     kg
                  </td>
               </tr>
               <tr>
                  <th>
                     Revenue
                  </th>
                  <td class="text-end fw-bold">
                     ₦<?= number_format((float)$revenue['revenue'], 2) ?>
                  </td>
               </tr>
            </table>
         </div>
      </div>
   </div>
</div>
<!-- ===========================================================
   TOP PERFORMING BATCHES
   =========================================================== -->
<div class="row">
   <div class="col-lg-6 mb-4">
      <div class="card shadow-sm">
         <div class="card-header bg-info text-white">
            <h5 class="mb-0">
               Top Performing Batches
            </h5>
         </div>
         <div class="card-body p-0">
            <table class="table table-striped mb-0">
               <thead>
                  <tr>
                     <th>Batch</th>
                     <th>Species</th>
                     <th class="text-end">
                        Harvests
                     </th>
                     <th class="text-end">
                        Stocked
                     </th>
                  </tr>
               </thead>
               <tbody>
                  <?php foreach($topBatches as $batch): ?>
                  <tr>
                     <td>
                        <?= htmlspecialchars($batch['batch_code']) ?>
                     </td>
                     <td>
                        <?= htmlspecialchars($batch['species']) ?>
                     </td>
                     <td class="text-end">
                        <?= number_format($batch['harvests']) ?>
                     </td>
                     <td class="text-end">
                        <?= number_format($batch['stocked_fish']) ?>
                     </td>
                  </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
         </div>
      </div>
   </div>
   <!-- ===========================================================
      TOP PERFORMING PONDS
      =========================================================== -->
   <div class="col-lg-6 mb-4">
      <div class="card shadow-sm">
         <div class="card-header bg-warning">
            <h5 class="mb-0">
               Top Performing Ponds
            </h5>
         </div>
         <div class="card-body p-0">
            <table class="table table-striped mb-0">
               <thead>
                  <tr>
                     <th>Pond</th>
                     <th class="text-end">
                        Harvests
                     </th>
                     <th class="text-end">
                        Estimated Fish
                     </th>
                  </tr>
               </thead>
               <tbody>
                  <?php foreach($topPonds as $pond): ?>
                  <tr>
                     <td>
                        <?= htmlspecialchars($pond['pond_code']) ?>
                     </td>
                     <td class="text-end">
                        <?= number_format($pond['harvests']) ?>
                     </td>
                     <td class="text-end">
                        <?= number_format($pond['estimated_fish']) ?>
                     </td>
                  </tr>
                  <?php endforeach; ?>
               </tbody>
            </table>
         </div>
      </div>
   </div>
</div>
<!-- ===========================================================
   ANALYTICS CHARTS
   =========================================================== -->
<div class="row">
   <!-- Monthly Harvest Trend -->
   <div class="col-lg-8 mb-4">
      <div class="card shadow-sm">
         <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
               <i class="bi bi-graph-up"></i>
               Monthly Harvest Trend
            </h5>
         </div>
         <div class="card-body">
            <canvas id="monthlyHarvestChart"
               height="110"></canvas>
         </div>
      </div>
   </div>
   <!-- Harvest Status -->
   <div class="col-lg-4 mb-4">
      <div class="card shadow-sm">
         <div class="card-header bg-success text-white">
            <h5 class="mb-0">
               Harvest Status Distribution
            </h5>
         </div>
         <div class="card-body">
            <canvas id="statusChart"
               height="220"></canvas>
         </div>
      </div>
   </div>
</div>
<!-- ===========================================================
   STAFF SHARE SUMMARY
   =========================================================== -->
<div class="row">
   <div class="col-lg-4 mb-4">
      <div class="card shadow-sm">
         <div class="card-header bg-warning">
            <h5 class="mb-0">
               Staff Share Summary
            </h5>
         </div>
         <div class="card-body">
            <table class="table table-sm">
               <tr>
                  <th>
                     Distributions
                  </th>
                  <td class="text-end">
                     <?= number_format(
                        $staffShares['distributions'] ?? 0
                        ) ?>
                  </td>
               </tr>
               <tr>
                  <th>
                     Quantity Shared
                  </th>
                  <td class="text-end">
                     <?= number_format(
                        $staffShares['quantity_kg'] ?? 0,
                        2
                        ) ?>
                     kg
                  </td>
               </tr>
            </table>
         </div>
      </div>
   </div>
   <!-- ===========================================================
      EXECUTIVE INSIGHTS
      =========================================================== -->
   <div class="col-lg-8 mb-4">
      <div class="card shadow-sm">
         <div class="card-header bg-dark text-white">
            <h5 class="mb-0">
               Executive Insights
            </h5>
         </div>
         <div class="card-body">
            <ul class="mb-0">
               <li>
                  <strong>
                  <?= number_format($dashboard['total_harvests']) ?>
                  </strong>
                  harvest(s) have been recorded.
               </li>
               <li>
                  <strong>
                  <?= number_format($dashboard['open_harvests']) ?>
                  </strong>
                  harvest(s) remain open.
               </li>
               <li>
                  <strong>
                  <?= number_format($dashboard['closed_harvests']) ?>
                  </strong>
                  harvest(s) have been completed.
               </li>
               <li>
                  Estimated harvested fish:
                  <strong>
                  <?= number_format($dashboard['estimated_fish']) ?>
                  </strong>
                  fish.
               </li>
               <li>
                  Harvest revenue:
                  <strong>
                  ₦<?= number_format(
                     $revenue['revenue'],
                     2
                     ) ?>
                  </strong>
               </li>
               <li>
                  Staff share quantity:
                  <strong>
                  <?= number_format(
                     $staffShares['quantity_kg'] ?? 0,
                     2
                     ) ?>
                  kg
                  </strong>
               </li>
            </ul>
         </div>
      </div>
   </div>
</div>



<!-- ===========================================================
    CHART.JS
=========================================================== -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const monthlyLabels = <?= json_encode(
    array_column($monthlyTrend, 'month_name')
) ?>;

const monthlyData = <?= json_encode(
    array_map(
        'intval',
        array_column($monthlyTrend, 'total')
    )
) ?>;

new Chart(

document.getElementById('monthlyHarvestChart'),

{

type:'bar',

data:{

labels:monthlyLabels,

datasets:[{

label:'Harvests',

data:monthlyData,

borderWidth:1

}]

},

options:{

responsive:true,

plugins:{

legend:{

display:false

}

}

}

}

);

const statusLabels = <?= json_encode(
    array_column($statusSummary, 'status')
) ?>;

const statusData = <?= json_encode(
    array_map(
        'intval',
        array_column($statusSummary, 'total')
    )
) ?>;

new Chart(

document.getElementById('statusChart'),

{

type:'pie',

data:{

labels:statusLabels,

datasets:[{

data:statusData

}]

},

options:{

responsive:true

}

}

);

</script>
