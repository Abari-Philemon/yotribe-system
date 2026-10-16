<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_permission('finance');

require_once __DIR__ . '/../../middleware/csrf.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use App\Core\Database;
use App\Modules\Finance\Repositories\FinanceSettingsRepository;
use App\Modules\Finance\Requests\FinanceSettingsRequest;
use App\Modules\Finance\Services\CompanyContext;
use App\Modules\Finance\Services\FinanceSettingsService;

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

$db = new Database($pdo);
$pageTitle = 'Finance Settings';
$errors = [];
$success = null;
$settings = [];
$cashAccounts = [];
$bankAccounts = [];
$companyName = '';
$companyId = 0;
$actorId = filter_var($_SESSION['staff_id'] ?? null, FILTER_VALIDATE_INT);
$actorId = ($actorId !== false && $actorId !== null && $actorId > 0) ? $actorId : null;

$defaults = [
    'default_currency' => 'NGN',
    'currency_symbol' => '₦',
    'decimal_places' => 2,
    'financial_year_start_month' => 1,
    'financial_year_start_day' => 1,
    'default_tax_rate' => '0.00',
    'allow_backdated_entries' => 0,
    'allow_negative_cash' => 0,
    'auto_post_journals' => 1,
    'default_cash_account_id' => '',
    'default_bank_account_id' => '',
];

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

try {
    $companyId = (new CompanyContext($db))->resolve($_SESSION);

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
    $settingsService = new FinanceSettingsService($settingsRepository);
    $existingSettings = $settingsService->getByCompanyId($companyId);

    $cashAccounts = $db->fetchAll(
        'SELECT id, account_code, account_name
         FROM accounts
         WHERE company_id = :company_id
           AND is_active = 1
           AND is_cash_account = 1
         ORDER BY account_code',
        ['company_id' => $companyId]
    );

    $bankAccounts = $db->fetchAll(
        'SELECT id, bank_name, account_name, currency_code
         FROM bank_accounts
         WHERE company_id = :company_id
           AND is_active = 1
         ORDER BY bank_name, account_name',
        ['company_id' => $companyId]
    );

    if ($existingSettings !== null) {
        $settings = array_merge($defaults, $existingSettings);
    } else {
        $settings = $defaults;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? null;

        if (!is_string($submittedToken) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
            http_response_code(403);
            $errors[] = 'Your session token is invalid or expired. Reload the page and try again.';
        } else {
            $input = [];
            $requiredFields = [
                'default_currency',
                'currency_symbol',
                'decimal_places',
                'financial_year_start_month',
                'financial_year_start_day',
                'default_tax_rate',
                'default_cash_account_id',
                'default_bank_account_id',
            ];

            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $_POST)) {
                    $errors[] = 'A required settings field is missing: ' . $field . '.';
                } elseif (!is_string($_POST[$field])) {
                    $errors[] = 'Invalid input received for: ' . $field . '.';
                } else {
                    $input[$field] = $_POST[$field];
                }
            }

            foreach ([
                'allow_backdated_entries',
                'allow_negative_cash',
                'auto_post_journals',
            ] as $field) {
                if (!array_key_exists($field, $_POST)) {
                    $input[$field] = '0';
                } elseif (
                    !is_string($_POST[$field])
                    || $_POST[$field] !== '1'
                ) {
                    $errors[] = 'Invalid checkbox value received for: '
                        . $field . '.';
                } else {
                    $input[$field] = '1';
                }
            }

            try {
                if ($errors !== []) {
                    throw new InvalidArgumentException(implode(' ', $errors));
                }

                $validated = (new FinanceSettingsRequest())->validate($input);

                $allowedCashIds = array_map(
                    static fn (array $row): int => (int) $row['id'],
                    $cashAccounts
                );
                $allowedBankIds = array_map(
                    static fn (array $row): int => (int) $row['id'],
                    $bankAccounts
                );

                if (isset($validated['default_cash_account_id']) &&
                    $validated['default_cash_account_id'] !== null &&
                    !in_array($validated['default_cash_account_id'], $allowedCashIds, true)) {
                    throw new InvalidArgumentException('Select an active cash account belonging to this company.');
                }

                if (isset($validated['default_bank_account_id']) &&
                    $validated['default_bank_account_id'] !== null &&
                    !in_array($validated['default_bank_account_id'], $allowedBankIds, true)) {
                    throw new InvalidArgumentException('Select an active bank account belonging to this company.');
                }

                $pdo->beginTransaction();

                if ($existingSettings === null) {
                    $settingsService->createForCompany($companyId, $validated, $actorId);
                } else {
                    $settingsService->updateForCompany($companyId, $validated, $actorId);
                }

                $pdo->commit();

                header('Location: /yotribe-system/app/modules/finance/settings.php?saved=1');
                exit;
            } catch (InvalidArgumentException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $exception->getMessage();
                $safePostedValues = [];
                foreach ($requiredFields as $field) {
                    if (isset($_POST[$field]) && is_string($_POST[$field])) {
                        $safePostedValues[$field] = $_POST[$field];
                    }
                }
                foreach ([
                    'allow_backdated_entries',
                    'allow_negative_cash',
                    'auto_post_journals',
                ] as $field) {
                    $safePostedValues[$field] =
                        isset($_POST[$field]) && is_string($_POST[$field])
                        ? $_POST[$field]
                        : '0';
                }
                $settings = array_merge($defaults, $settings, $safePostedValues);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Finance settings save error: ' . $exception->getMessage());
                $errors[] = 'Settings could not be saved. Check the application logs and try again.';
                $safePostedValues = [];
                foreach ($requiredFields as $field) {
                    if (isset($_POST[$field]) && is_string($_POST[$field])) {
                        $safePostedValues[$field] = $_POST[$field];
                    }
                }
                foreach ([
                    'allow_backdated_entries',
                    'allow_negative_cash',
                    'auto_post_journals',
                ] as $field) {
                    $safePostedValues[$field] =
                        isset($_POST[$field]) && is_string($_POST[$field])
                        ? $_POST[$field]
                        : '0';
                }
                $settings = array_merge($defaults, $settings, $safePostedValues);
            }
        }
    } elseif (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $success = 'Finance settings saved successfully.';
        $settings = array_merge(
            $defaults,
            $settingsService->getByCompanyId($companyId) ?? []
        );
    }
} catch (Throwable $exception) {
    error_log('Finance settings page error: ' . $exception->getMessage());
    http_response_code(500);
    $errors[] = 'Finance Settings could not be loaded. Check the application logs.';
}

$booleanChecked = static fn (string $field): bool =>
    in_array($settings[$field] ?? 0, [1, '1', true, 'true'], true);
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
        .panel { background: #fff; border: 1px solid #e5eaf2; border-radius: 14px; padding: 24px; }
        .muted { color: #64748b; }
        .form-label { font-weight: 600; }
        .section-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 16px; }
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Finance Settings</h1>
            <p class="muted mb-0"><?= $escape($companyName) ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="/yotribe-system/app/modules/finance/index.php">Back to Overview</a>
    </div>

    <?php if ($success !== null): ?>
        <div class="alert alert-success" role="status"><?= $escape($success) ?></div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger" role="alert"><?= $escape($error) ?></div>
    <?php endforeach; ?>

    <form method="post" class="panel">
        <input type="hidden" name="csrf_token" value="<?= $escape(csrf_token()) ?>">

        <h2 class="section-title">Currency and Number Format</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label" for="default_currency">Currency code</label>
                <input class="form-control" id="default_currency" name="default_currency" required maxlength="3" pattern="[A-Za-z]{3}" value="<?= $escape($settings['default_currency'] ?? 'NGN') ?>">
                <div class="form-text">Three-letter code, e.g. NGN or GHS.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="currency_symbol">Currency symbol</label>
                <input class="form-control" id="currency_symbol" name="currency_symbol" required maxlength="10" value="<?= $escape($settings['currency_symbol'] ?? '₦') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="decimal_places">Decimal places</label>
                <select class="form-select" id="decimal_places" name="decimal_places">
                    <?php for ($i = 0; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>" <?= (string)($settings['decimal_places'] ?? 2) === (string)$i ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <h2 class="section-title">Financial Year and Tax</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label" for="financial_year_start_month">Financial year start month</label>
                <select class="form-select" id="financial_year_start_month" name="financial_year_start_month" required>
                    <?php foreach ([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $number => $month): ?>
                        <option value="<?= $number ?>" <?= (string)($settings['financial_year_start_month'] ?? 1) === (string)$number ? 'selected' : '' ?>><?= $escape($month) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="financial_year_start_day">Financial year start day</label>
                <input class="form-control" type="number" id="financial_year_start_day" name="financial_year_start_day" min="1" max="31" required value="<?= $escape($settings['financial_year_start_day'] ?? 1) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="default_tax_rate">Default tax rate (%)</label>
                <input class="form-control" type="number" id="default_tax_rate" name="default_tax_rate" min="0" max="100" step="0.01" required value="<?= $escape($settings['default_tax_rate'] ?? '0.00') ?>">
            </div>
        </div>

        <h2 class="section-title">Default Accounts</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="default_cash_account_id">Default cash account (optional)</label>
                <select class="form-select" id="default_cash_account_id" name="default_cash_account_id">
                    <option value="">Not selected</option>
                    <?php foreach ($cashAccounts as $account): ?>
                        <option value="<?= (int)$account['id'] ?>" <?= (string)($settings['default_cash_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                            <?= $escape($account['account_code'] . ' — ' . $account['account_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($cashAccounts === []): ?><div class="form-text">No active cash accounts are available for this company.</div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="default_bank_account_id">Default bank account (optional)</label>
                <select class="form-select" id="default_bank_account_id" name="default_bank_account_id">
                    <option value="">Not selected</option>
                    <?php foreach ($bankAccounts as $account): ?>
                        <option value="<?= (int)$account['id'] ?>" <?= (string)($settings['default_bank_account_id'] ?? '') === (string)$account['id'] ? 'selected' : '' ?>>
                            <?= $escape($account['bank_name'] . ' — ' . $account['account_name'] . ' (' . $account['currency_code'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($bankAccounts === []): ?><div class="form-text">No active bank accounts are available for this company.</div><?php endif; ?>
            </div>
        </div>

        <h2 class="section-title">Posting and Control Rules</h2>
        <div class="mb-4">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="allow_backdated_entries" name="allow_backdated_entries" value="1" <?= $booleanChecked('allow_backdated_entries') ? 'checked' : '' ?>>
                <label class="form-check-label" for="allow_backdated_entries">Allow backdated entries</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="allow_negative_cash" name="allow_negative_cash" value="1" <?= $booleanChecked('allow_negative_cash') ? 'checked' : '' ?>>
                <label class="form-check-label" for="allow_negative_cash">Allow negative cash balances</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="auto_post_journals" name="auto_post_journals" value="1" <?= $booleanChecked('auto_post_journals') ? 'checked' : '' ?>>
                <label class="form-check-label" for="auto_post_journals">Automatically post eligible journals</label>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">Save Finance Settings</button>
            <a class="btn btn-outline-secondary" href="/yotribe-system/app/modules/finance/index.php">Cancel</a>
        </div>
    </form>
</main>
</body>
</html>