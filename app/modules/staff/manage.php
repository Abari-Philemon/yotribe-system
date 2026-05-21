<?php

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('staff');

/**
 * =========================================================
 * CURRENT USER
 * =========================================================
 */
$current_staff_id = $_SESSION['staff_id'] ?? 0;

/**
 * =========================================================
 * PAGE TITLE
 * =========================================================
 */
$page_title = 'Manage Staff';

$message = '';
$alert = 'success';

/**
 * =========================================================
 * CSRF TOKEN
 * =========================================================
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * =========================================================
 * HANDLE ACTIONS (GLOBAL SCOPE)
 * =========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        die('Invalid CSRF token.');
    }

    $staff_id = (int) ($_POST['staff_id'] ?? 0);

    if ($staff_id === $current_staff_id) {
        $message = "You cannot perform this action on your own account.";
        $alert = "danger";
    } else {

        /**
         * APPROVE
         */
        if (isset($_POST['approve'])) {

            $stmt = $pdo->prepare("
                UPDATE staff
                SET approval_status = 'approved',
                    status = 'active',
                    active = 1
                WHERE id = ?
            ");

            $stmt->execute([$staff_id]);

            $message = "Staff approved successfully.";
        }

        /**
         * DISABLE
         */
        if (isset($_POST['disable'])) {

            $stmt = $pdo->prepare("
                UPDATE staff
                SET status = 'disabled',
                    active = 0
                WHERE id = ?
            ");

            $stmt->execute([$staff_id]);

            $message = "Staff disabled.";
            $alert = "warning";
        }

        /**
         * ACTIVATE
         */
        if (isset($_POST['activate'])) {

            $stmt = $pdo->prepare("
                UPDATE staff
                SET status = 'active',
                    active = 1
                WHERE id = ?
            ");

            $stmt->execute([$staff_id]);

            $message = "Staff activated.";
        }
    }
}

/**
 * =========================================================
 * LOAD ALL STAFF (NO FARM FILTER)
 * =========================================================
 */
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.full_name,
        s.username,
        s.role,
        s.farm_id,
        s.status,
        s.active,
        s.approval_status,
        s.last_login,
        s.created_at,
        f.farm_name AS farm_name
    FROM staff s
    LEFT JOIN farms f
        ON f.id = s.farm_id
    ORDER BY s.created_at DESC
");

$stmt->execute();
$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<style>
.page-card{
    border:none;
    border-radius:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.05);
}

.badge-soft-success{ background:#e9f7ef; color:#198754; }
.badge-soft-warning{ background:#fff3cd; color:#997404; }
.badge-soft-danger{ background:#fdeaea; color:#dc3545; }

.table td, .table th{ vertical-align:middle; }

.action-form{
    display:inline-block;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h3 class="mb-1">👥 Staff Management</h3>
        <small class="text-muted">Manage all staff across all farms</small>
    </div>

    <a href="register.php" class="btn btn-success">
        + Create Staff
    </a>

</div>

<?php if($message): ?>
<div class="alert alert-<?= htmlspecialchars($alert) ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="card page-card">
    <div class="card-body">

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Farm</th>
                        <th>Approval</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th width="280">Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if(empty($staffs)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No staff found
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($staffs as $index => $s): ?>

                    <tr>

                        <td><?= $index + 1 ?></td>

                        <td>
                            <strong><?= htmlspecialchars($s['full_name']) ?></strong>
                        </td>

                        <td><?= htmlspecialchars($s['username']) ?></td>

                        <td>
                            <span class="badge bg-dark">
                                <?= ucfirst($s['role']) ?>
                            </span>
                        </td>

                        <td>
                            <?= htmlspecialchars($s['farm_name'] ?? 'N/A') ?>
                        </td>

                        <td>
                            <?php if($s['approval_status'] === 'approved'): ?>
                                <span class="badge badge-soft-success">Approved</span>
                            <?php else: ?>
                                <span class="badge badge-soft-warning">Pending</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if($s['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif($s['status'] === 'disabled'): ?>
                                <span class="badge bg-danger">Disabled</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if(!empty($s['last_login'])): ?>
                                <?= date('d M Y h:i A', strtotime($s['last_login'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Never</span>
                            <?php endif; ?>
                        </td>

                        <td>

                            <div class="d-flex flex-wrap gap-2">

                                <?php if($s['id'] != $current_staff_id): ?>

                                    <?php if($s['approval_status'] === 'pending'): ?>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">

                                            <button name="approve" class="btn btn-sm btn-success"
                                                onclick="return confirm('Approve this staff?')">
                                                Approve
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if($s['status'] === 'active'): ?>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">

                                            <button name="disable" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Disable this staff?')">
                                                Disable
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if($s['status'] === 'disabled'): ?>
                                        <form method="POST" class="action-form">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">

                                            <button name="activate" class="btn btn-sm btn-primary"
                                                onclick="return confirm('Activate this staff?')">
                                                Activate
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                <?php endif; ?>

                                <a href="reset_password.php?id=<?= $s['id'] ?>"
                                   class="btn btn-sm btn-dark">
                                    Reset Password
                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>