<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Controllers/FinancialPeriodController.php';
require_permission('finance');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = (string)$_SESSION['csrf_token'];
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$companyId = finance_company_id();
$controller = new \App\Modules\Finance\Controllers\FinancialPeriodController($pdo, $companyId);
$message = null; $messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(419); $message = 'Your session token has expired. Refresh the page and try again.'; $messageType = 'danger';
    } else {
        try {
            $action = (string)($_POST['action'] ?? 'create_year');
            if ($action === 'period_status') {
                $id = filter_var($_POST['period_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) throw new RuntimeException('Select a valid accounting period.');
                $message = $controller->setStatus((int)$id, (string)($_POST['status'] ?? ''), $staffId);
            } else {
                $message = $controller->createYear($_POST, $staffId);
            }
        } catch (Throwable $e) {
            error_log('[YOTRIBE Finance Periods] ' . $e->getMessage());
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'The request could not be completed. Check the PHP error log for details.';
            $messageType = 'danger';
        }
    }
}

try { $data = $controller->index(); }
catch (Throwable $e) { error_log('[YOTRIBE Finance Periods] ' . $e->getMessage()); http_response_code(500); $pageError = 'Financial periods could not be loaded. Confirm the Module 007 migration and company context.'; $data = ['years'=>[], 'periods'=>[], 'summary'=>['years'=>0,'open_years'=>0,'periods'=>0,'open_periods'=>0]]; }
$page_title = 'Financial Periods';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
$esc = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$today = new DateTimeImmutable('today');
$defaultStart = new DateTimeImmutable($today->format('Y') . '-01-01');
$defaultEnd = new DateTimeImmutable($today->format('Y') . '-12-31');
?>
<main class="col-md-10 ms-sm-auto px-md-4 py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom pb-3 mb-4">
    <div><h1 class="h2 mb-1">Financial Years &amp; Periods</h1><p class="text-muted mb-0">Configure financial years and monthly posting periods for farm accounting.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="index.php">Chart of Accounts</a><span class="badge text-bg-light border align-self-center">Module 007 · Finance</span></div>
  </div>
  <?php if (isset($pageError)): ?><div class="alert alert-danger"><?= $esc($pageError) ?></div><?php endif; ?>
  <?php if ($message): ?><div class="alert alert-<?= $esc($messageType) ?> alert-dismissible fade show" role="alert"><?= $esc($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Financial years</div><div class="fs-3 fw-semibold"><?= (int)$data['summary']['years'] ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Open years</div><div class="fs-3 fw-semibold"><?= (int)$data['summary']['open_years'] ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Accounting periods</div><div class="fs-3 fw-semibold"><?= (int)$data['summary']['periods'] ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Open periods</div><div class="fs-3 fw-semibold"><?= (int)$data['summary']['open_periods'] ?></div></div></div></div>
  </div>
  <div class="row g-4">
    <div class="col-xl-4"><div class="card shadow-sm"><div class="card-header bg-white"><h2 class="h5 mb-0">Create financial year</h2></div><div class="card-body">
      <form method="post" action="periods.php">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="action" value="create_year">
        <div class="mb-3"><label class="form-label" for="year_name">Financial year name *</label><input class="form-control" id="year_name" name="year_name" maxlength="100" required value="<?= $esc($_POST['year_name'] ?? ('Financial Year ' . $today->format('Y'))) ?>" placeholder="e.g. 2026 Financial Year"></div>
        <div class="mb-3"><label class="form-label" for="start_date">Start date *</label><input class="form-control" type="date" id="start_date" name="start_date" required value="<?= $esc($_POST['start_date'] ?? $defaultStart->format('Y-m-d')) ?>"></div>
        <div class="mb-3"><label class="form-label" for="end_date">End date *</label><input class="form-control" type="date" id="end_date" name="end_date" required value="<?= $esc($_POST['end_date'] ?? $defaultEnd->format('Y-m-d')) ?>"></div>
        <div class="alert alert-info small">Creates 12 monthly periods in one transaction. Dates must cover exactly 12 months, start on the first day of a month, and end on the last day of a month. Existing financial years cannot overlap.</div>
        <button class="btn btn-primary w-100" type="submit">Create year and periods</button>
      </form>
    </div></div></div>
    <div class="col-xl-8"><div class="card shadow-sm"><div class="card-header bg-white"><h2 class="h5 mb-0">Financial years</h2></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Year</th><th>Date range</th><th>Periods</th><th>Status</th><th>Current</th></tr></thead><tbody>
      <?php if (!$data['years']): ?><tr><td colspan="5" class="text-center text-muted py-4">No financial years created yet.</td></tr><?php endif; ?>
      <?php foreach ($data['years'] as $year): ?><tr><td><div class="fw-semibold"><?= $esc($year['year_name']) ?></div><div class="small text-muted"><?= $esc($year['year_code']) ?></div></td><td class="text-nowrap"><?= $esc($year['start_date']) ?><br><span class="text-muted">to</span> <?= $esc($year['end_date']) ?></td><td><?= (int)$year['period_count'] ?>/12<div class="small text-muted"><?= (int)$year['open_periods'] ?> open</div></td><td><span class="badge text-bg-<?= $year['status']==='OPEN'?'success':($year['status']==='CLOSED'?'secondary':'dark') ?>"><?= $esc($year['status']) ?></span></td><td><?= (int)$year['is_current']===1?'<span class="badge text-bg-primary">Current</span>':'—' ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div></div>
  </div>
  <div class="card shadow-sm mt-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Monthly accounting periods</h2></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Year / Period</th><th>Date range</th><th>Status</th><th>Current</th><th>Action</th></tr></thead><tbody>
    <?php if (!$data['periods']): ?><tr><td colspan="5" class="text-center text-muted py-4">Periods will appear here after you create a financial year.</td></tr><?php endif; ?>
    <?php foreach ($data['periods'] as $period): ?><tr><td><div class="fw-semibold"><?= $esc($period['period_name']) ?></div><div class="small text-muted"><?= $esc($period['year_code']) ?> · <?= $esc($period['period_code']) ?></div></td><td class="text-nowrap"><?= $esc($period['start_date']) ?><br><span class="text-muted">to</span> <?= $esc($period['end_date']) ?></td><td><span class="badge text-bg-<?= $period['status']==='OPEN'?'success':($period['status']==='CLOSED'?'secondary':'dark') ?>"><?= $esc($period['status']) ?></span></td><td><?= (int)$period['is_current']===1?'<span class="badge text-bg-primary">Current</span>':'—' ?></td><td><form method="post" action="periods.php" class="d-flex gap-2 align-items-center"><input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="action" value="period_status"><input type="hidden" name="period_id" value="<?= (int)$period['id'] ?>"><select class="form-select form-select-sm" name="status" aria-label="New status for <?= $esc($period['period_name']) ?>"><option value="OPEN" <?= $period['status']==='OPEN'?'selected':'' ?>>Open</option><option value="CLOSED" <?= $period['status']==='CLOSED'?'selected':'' ?>>Closed</option><option value="LOCKED" <?= $period['status']==='LOCKED'?'selected':'' ?>>Locked</option></select><button class="btn btn-sm btn-outline-primary" type="submit">Save</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div><div class="card-footer bg-white small text-muted">Company context: <?= (int)$companyId ?>. Period status changes are recorded in the finance audit log.</div></div>
</main>
</body></html>
