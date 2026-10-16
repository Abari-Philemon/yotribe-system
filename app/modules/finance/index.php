<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_permission('finance');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\FinanceSettingsRepository;
use App\Modules\Finance\Services\CompanyContext;

$pageTitle = 'Finance Overview';
$companyName = '';
$settings = null;
$pageError = null;

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('The configured database connection is unavailable.');
    }

    $db = new Database($pdo);
    $companyContext = new CompanyContext($db);
    $companyId = $companyContext->resolve($_SESSION);

    $company = $db->fetch(
        'SELECT legal_name, trading_name
         FROM companies
         WHERE id = :id AND status = :status',
        ['id' => $companyId, 'status' => 'active']
    );

    if ($company === null) {
        throw new RuntimeException('The active company could not be loaded.');
    }

    $companyName = trim((string) ($company['trading_name'] ?? ''));
    if ($companyName === '') {
        $companyName = (string) $company['legal_name'];
    }

    $settingsRepository = new FinanceSettingsRepository($db);
    $settings = $settingsRepository->findByCompanyId($companyId);
} catch (Throwable $exception) {
    error_log('Finance overview error: ' . $exception->getMessage());
    $pageError = 'Finance could not be loaded. Check the company configuration and application logs.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($pageTitle) ?> | Yotribe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; background: #f4f7fb; color: #172033; font-family: system-ui, sans-serif; }
        .topbar { background: #0f172a; color: #fff; padding: 18px 24px; }
        .page { max-width: 1100px; margin: 0 auto; padding: 28px 20px; }
        .panel { background: #fff; border: 1px solid #e5eaf2; border-radius: 14px; padding: 22px; }
        .muted { color: #64748b; }
        .setting-row { padding: 13px 0; border-bottom: 1px solid #edf0f5; }
        .setting-row:last-child { border-bottom: 0; }
    </style>
</head>
<body>
<header class="topbar">
    <div class="container-fluid">
        <strong>YOTRIBE</strong>
        <span class="ms-2">Finance &amp; Accounting</span>
    </div>
</header>

<main class="page">
    <div class="mb-4">
        <h1 class="h3 mb-1">Finance Overview</h1>
        <p class="muted mb-0">Company-level finance settings and configuration status.</p>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($pageError) ?></div>
    <?php else: ?>
        <section class="panel mb-4">
            <div class="muted small">Active company</div>
            <h2 class="h5 mt-1 mb-0"><?= $escape($companyName) ?></h2>
        </section>

        <section class="panel">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 mb-1">Finance Settings</h2>
                    <p class="muted mb-0">Default currency, financial year, tax and posting controls.</p>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <?php if ($settings !== null): ?>
                        <span class="badge text-bg-success">Configured</span>
                    <?php else: ?>
                        <span class="badge text-bg-warning">Not configured</span>
                    <?php endif; ?>
                    <a class="btn btn-outline-primary btn-sm"
                       href="/yotribe-system/app/modules/finance/settings.php">
                        Manage Finance Settings
                    </a>
                </div>
            </div>

            <?php if ($settings === null): ?>
                <p class="mb-0">
                    No Finance Settings record exists for this company yet.
                    No settings have been created or changed by this page.
                </p>
            <?php else: ?>
                <div class="setting-row d-flex justify-content-between gap-3">
                    <span class="muted">Default currency</span>
                    <strong><?= $escape($settings['default_currency']) ?> (<?= $escape($settings['currency_symbol']) ?>)</strong>
                </div>
                <div class="setting-row d-flex justify-content-between gap-3">
                    <span class="muted">Decimal places</span>
                    <strong><?= $escape($settings['decimal_places']) ?></strong>
                </div>
                <div class="setting-row d-flex justify-content-between gap-3">
                    <span class="muted">Financial year starts</span>
                    <strong><?= $escape(sprintf('%02d-%02d', (int) $settings['financial_year_start_month'], (int) $settings['financial_year_start_day'])) ?></strong>
                </div>
                <div class="setting-row d-flex justify-content-between gap-3">
                    <span class="muted">Default tax rate</span>
                    <strong><?= $escape($settings['default_tax_rate']) ?>%</strong>
                </div>
                <div class="setting-row d-flex justify-content-between gap-3">
                    <span class="muted">Allow backdated entries</span>
                    <strong><?= (int) $settings['allow_backdated_entries'] === 1 ? 'Enabled' : 'Disabled' ?></strong>
                </div>
                <div class="setting-row d-flex justify-content-between gap-3">
                    <span class="muted">Allow negative cash</span>
                    <strong><?= (int) $settings['allow_negative_cash'] === 1 ? 'Enabled' : 'Disabled' ?></strong>
                </div>
                <div class="setting-row d-flex justify-content-between gap-3">
                    <span class="muted">Automatic journal posting</span>
                    <strong><?= (int) $settings['auto_post_journals'] === 1 ? 'Enabled' : 'Disabled' ?></strong>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <p class="muted small mt-4 mb-0">Finance overview · Read-only</p>
</main>
</body>
</html>