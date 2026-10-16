<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Requests (Validation)/AccountRequest.php';
require_once __DIR__ . '/Controllers/AccountController.php';
require_once __DIR__ . '/Routes/web.php';

require_permission('finance');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = (string)$_SESSION['csrf_token'];
$staffId = (int)($_SESSION['staff_id'] ?? 0);
$companyId = finance_company_id();
$controller = new \App\Modules\Finance\Controllers\AccountController($pdo, $companyId);
$message = null;
$messageType = 'success';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($csrfToken, $postedToken)) {
        http_response_code(419);
        $message = 'Your session token has expired. Refresh the page and try again.';
        $messageType = 'danger';
    } else {
        $action = (string)($_POST['action'] ?? 'create');
        try {
            if ($action === 'toggle') {
                $id = filter_var($_POST['account_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) throw new InvalidArgumentException('Invalid account selected.');
                $message = $controller->toggle((int)$id, $staffId);
            } else {
                $result = $controller->create($_POST, $staffId);
                $message = $result['message'];
                $errors = $result['errors'];
                if (!$result['ok']) $messageType = 'danger';
            }
        } catch (Throwable $e) {
            error_log('[YOTRIBE Finance COA] ' . $e->getMessage());
            $message = 'The request could not be completed. Check the PHP error log for details.';
            $messageType = 'danger';
        }
    }
}

try {
    $data = $controller->index();
} catch (Throwable $e) {
    error_log('[YOTRIBE Finance COA] ' . $e->getMessage());
    http_response_code(500);
    $pageError = 'Chart of Accounts could not be loaded. Check the database connection, finance company ID configuration, and PHP error log.';
    $data = ['accounts'=>[], 'types'=>[], 'classes'=>[], 'parents'=>[], 'search'=>''];
}

$page_title = 'Chart of Accounts';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
$esc = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<main class="col-md-10 ms-sm-auto px-md-4 py-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom pb-3 mb-4">
        <div><h1 class="h2 mb-1">Chart of Accounts</h1><p class="text-muted mb-0">Manage the ledger accounts used by YOTRIBE farm finance.</p></div>
        <span class="badge text-bg-light border">Module 007 · Finance</span>
    </div>
    <?php if (isset($pageError)): ?><div class="alert alert-danger"><?= $esc($pageError) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-<?= $esc($messageType) ?> alert-dismissible fade show" role="alert"><?= $esc($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total accounts</div><div class="fs-3 fw-semibold"><?= count($data['accounts']) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Active accounts</div><div class="fs-3 fw-semibold"><?= count(array_filter($data['accounts'], static fn($a) => (int)$a['is_active'] === 1)) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Posting accounts</div><div class="fs-3 fw-semibold"><?= count(array_filter($data['accounts'], static fn($a) => (int)$a['allow_posting'] === 1)) ?></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Account classes</div><div class="fs-3 fw-semibold"><?= count($data['classes']) ?></div></div></div></div>
    </div>
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card shadow-sm"><div class="card-header bg-white"><h2 class="h5 mb-0">Add account</h2></div><div class="card-body">
                <form method="post" action="index.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="action" value="create">
                    <div class="mb-3"><label for="account_code" class="form-label">Account code *</label><input class="form-control <?= isset($errors['account_code'])?'is-invalid':'' ?>" id="account_code" name="account_code" maxlength="30" required value="<?= $esc($_POST['account_code'] ?? '') ?>" placeholder="e.g. 1001"><div class="invalid-feedback"><?= $esc($errors['account_code'] ?? '') ?></div></div>
                    <div class="mb-3"><label for="account_name" class="form-label">Account name *</label><input class="form-control <?= isset($errors['account_name'])?'is-invalid':'' ?>" id="account_name" name="account_name" maxlength="150" required value="<?= $esc($_POST['account_name'] ?? '') ?>" placeholder="e.g. Farm Cash"><div class="invalid-feedback"><?= $esc($errors['account_name'] ?? '') ?></div></div>
                    <div class="mb-3"><label for="account_type_id" class="form-label">Account type *</label><select class="form-select <?= isset($errors['account_type_id'])?'is-invalid':'' ?>" id="account_type_id" name="account_type_id" required><option value="">Select type</option><?php foreach ($data['types'] as $type): ?><option value="<?= (int)$type['id'] ?>" data-normal="<?= $esc($type['normal_balance']) ?>" <?= (string)($_POST['account_type_id'] ?? '') === (string)$type['id']?'selected':'' ?>><?= $esc($type['type_name']) ?> (<?= $esc(ucfirst($type['normal_balance'])) ?>)</option><?php endforeach; ?></select><div class="invalid-feedback"><?= $esc($errors['account_type_id'] ?? '') ?></div></div>
                    <div class="mb-3"><label for="account_class_id" class="form-label">Account class *</label><select class="form-select <?= isset($errors['account_class_id'])?'is-invalid':'' ?>" id="account_class_id" name="account_class_id" required><option value="">Select class</option><?php foreach ($data['classes'] as $class): ?><option value="<?= (int)$class['id'] ?>" data-type="<?= (int)$class['account_type_id'] ?>" <?= (string)($_POST['account_class_id'] ?? '') === (string)$class['id']?'selected':'' ?>><?= $esc($class['class_code'] . ' — ' . $class['class_name']) ?></option><?php endforeach; ?></select><div class="invalid-feedback"><?= $esc($errors['account_class_id'] ?? '') ?></div></div>
                    <div class="mb-3"><label for="parent_account_id" class="form-label">Parent account</label><select class="form-select <?= isset($errors['parent_account_id'])?'is-invalid':'' ?>" id="parent_account_id" name="parent_account_id"><option value="">No parent (top-level)</option><?php foreach ($data['parents'] as $parent): ?><option value="<?= (int)$parent['id'] ?>" <?= (string)($_POST['parent_account_id'] ?? '') === (string)$parent['id']?'selected':'' ?>><?= $esc($parent['account_code'] . ' — ' . $parent['account_name']) ?></option><?php endforeach; ?></select><div class="invalid-feedback"><?= $esc($errors['parent_account_id'] ?? '') ?></div></div>
                    <div class="mb-3"><label for="short_name" class="form-label">Short name</label><input class="form-control" id="short_name" name="short_name" maxlength="50" value="<?= $esc($_POST['short_name'] ?? '') ?>"></div>
                    <div class="mb-3"><label for="opening_balance" class="form-label">Opening balance (NGN)</label><input class="form-control <?= isset($errors['opening_balance'])?'is-invalid':'' ?>" type="number" min="0" step="0.01" id="opening_balance" name="opening_balance" value="<?= $esc($_POST['opening_balance'] ?? '0.00') ?>"><div class="invalid-feedback"><?= $esc($errors['opening_balance'] ?? '') ?></div><div class="form-text">Opening balance is stored as provided. Confirm debit/credit opening-balance treatment before go-live.</div></div>
                    <div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="2"><?= $esc($_POST['description'] ?? '') ?></textarea></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="allow_posting" name="allow_posting" checked><label class="form-check-label" for="allow_posting">Allow journal posting to this account</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="is_cash_account" name="is_cash_account"><label class="form-check-label" for="is_cash_account">Cash account</label></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_bank_account" name="is_bank_account"><label class="form-check-label" for="is_bank_account">Bank account</label></div>
                    <button class="btn btn-primary w-100" type="submit">Create account</button>
                </form>
            </div></div>
        </div>
        <div class="col-xl-8">
            <div class="card shadow-sm"><div class="card-header bg-white d-flex justify-content-between align-items-center gap-2 flex-wrap"><h2 class="h5 mb-0">Accounts</h2><form class="d-flex gap-2" method="get"><input class="form-control form-control-sm" name="q" value="<?= $esc($data['search']) ?>" placeholder="Search code or name" aria-label="Search accounts"><button class="btn btn-sm btn-outline-primary">Search</button><?php if ($data['search'] !== ''): ?><a class="btn btn-sm btn-outline-secondary" href="index.php">Clear</a><?php endif; ?></form></div>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Code</th><th>Account</th><th>Type / class</th><th>Normal</th><th>Posting</th><th>Status</th><th></th></tr></thead><tbody>
                <?php if (!$data['accounts']): ?><tr><td colspan="7" class="text-center text-muted py-5">No accounts found. Create your first account using the form.</td></tr><?php endif; ?>
                <?php foreach ($data['accounts'] as $account): ?><tr><td class="fw-semibold text-nowrap"><?= $esc($account['account_code']) ?></td><td><?= $esc($account['account_name']) ?><?php if ($account['parent_name']): ?><div class="small text-muted">Parent: <?= $esc($account['parent_name']) ?></div><?php endif; ?><?php if ((int)$account['is_system'] === 1): ?><span class="badge text-bg-light border">System</span><?php endif; ?></td><td><div><?= $esc($account['type_name']) ?></div><div class="small text-muted"><?= $esc($account['class_name']) ?></div></td><td><?= $esc(ucfirst($account['normal_balance'])) ?></td><td><?= (int)$account['allow_posting']===1?'Yes':'No' ?></td><td><span class="badge text-bg-<?= (int)$account['is_active']===1?'success':'secondary' ?>"><?= (int)$account['is_active']===1?'Active':'Inactive' ?></span></td><td><?php if ((int)$account['is_system'] !== 1): ?><form method="post" action="index.php" onsubmit="return confirm('Change this account status?')"><input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>"><button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int)$account['is_active']===1?'Deactivate':'Activate' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?>
                </tbody></table></div><div class="card-footer bg-white small text-muted">Company context: <?= (int)$companyId ?> · Account changes are recorded in the finance audit log.</div>
            </div>
        </div>
    </div>
</main>
<script>
(() => {
 const type = document.getElementById('account_type_id');
 const cls = document.getElementById('account_class_id');
 const filterClasses = () => {
   const selectedType = type.value;
   [...cls.options].forEach(option => {
     if (!option.value) { option.hidden = false; return; }
     option.hidden = selectedType !== '' && option.dataset.type !== selectedType;
     if (option.hidden && option.selected) cls.value = '';
   });
 };
 type.addEventListener('change', filterClasses); filterClasses();
})();
</script>
</body></html>
