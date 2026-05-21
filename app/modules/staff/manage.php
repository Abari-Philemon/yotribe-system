<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';

require_once __DIR__ . '/../../helpers/permission.php';

/**
 * MODULE ACCESS
 */
require_permission('staff');

/**
 * FARM CONTEXT
 */
$farm_id = farm_id();

/**
 * PAGE TITLE
 */
$page_title = "Manage Staff";

$message = '';
$alert = 'success';

/**
 * =========================================================
 * HANDLE ACTIONS
 * =========================================================
 */

if (isset($_GET['approve'])) {

    $staff_id = (int) $_GET['approve'];

    $stmt = $pdo->prepare("
        UPDATE staff
        SET
            approval_status = 'approved',
            status = 'active',
            active = 1
        WHERE id = ?
    ");

    $stmt->execute([$staff_id]);

    $message = "Staff account approved successfully.";
}

if (isset($_GET['disable'])) {

    $staff_id = (int) $_GET['disable'];

    $stmt = $pdo->prepare("
        UPDATE staff
        SET
            status = 'disabled',
            active = 0
        WHERE id = ?
    ");

    $stmt->execute([$staff_id]);

    $message = "Staff account disabled.";
    $alert = 'warning';
}

if (isset($_GET['activate'])) {

    $staff_id = (int) $_GET['activate'];

    $stmt = $pdo->prepare("
        UPDATE staff
        SET
            status = 'active',
            active = 1
        WHERE id = ?
    ");

    $stmt->execute([$staff_id]);

    $message = "Staff account activated.";
}

/**
 * =========================================================
 * LOAD STAFF
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
        f.name
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

.badge-soft-success{
    background:#e9f7ef;
    color:#198754;
}

.badge-soft-warning{
    background:#fff3cd;
    color:#997404;
}

.badge-soft-danger{
    background:#fdeaea;
    color:#dc3545;
}

.table td,
.table th{
    vertical-align:middle;
}

</style>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h3 class="mb-1">👥 Staff Management</h3>
        <small class="text-muted">
            Manage approvals, activation and passwords
        </small>
    </div>

    <a href="register.php" class="btn btn-success">
        + Create Staff
    </a>

</div>

<?php if($message): ?>

<div class="alert alert-<?= $alert ?>">
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
                        <th width="260">Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if(empty($staffs)): ?>

                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No staff records found
                        </td>
                    </tr>

                <?php endif; ?>

                <?php foreach ($staffs as $index => $s): ?>

                    <tr>

                        <td>
                            <?= $index + 1 ?>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars($s['full_name']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($s['username']) ?>
                        </td>

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

                                <span class="badge badge-soft-success">
                                    Approved
                                </span>

                            <?php else: ?>

                                <span class="badge badge-soft-warning">
                                    Pending
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if($s['status'] === 'active'): ?>

                                <span class="badge bg-success">
                                    Active
                                </span>

                            <?php elseif($s['status'] === 'disabled'): ?>

                                <span class="badge bg-danger">
                                    Disabled
                                </span>

                            <?php else: ?>

                                <span class="badge bg-warning text-dark">
                                    Pending
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if(!empty($s['last_login'])): ?>

                                <?= htmlspecialchars($s['last_login']) ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    Never
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="d-flex flex-wrap gap-2">

                                <?php if($s['approval_status'] === 'pending'): ?>

                                    <a
                                        href="?approve=<?= $s['id'] ?>"
                                        class="btn btn-sm btn-success"
                                        onclick="return confirm('Approve this staff account?')"
                                    >
                                        Approve
                                    </a>

                                <?php endif; ?>

                                <?php if($s['status'] === 'active'): ?>

                                    <a
                                        href="?disable=<?= $s['id'] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Disable this staff account?')"
                                    >
                                        Disable
                                    </a>

                                <?php endif; ?>

                                <?php if($s['status'] === 'disabled'): ?>

                                    <a
                                        href="?activate=<?= $s['id'] ?>"
                                        class="btn btn-sm btn-primary"
                                        onclick="return confirm('Activate this staff account?')"
                                    >
                                        Activate
                                    </a>

                                <?php endif; ?>

                                <a
                                    href="reset_password.php?id=<?= $s['id'] ?>"
                                    class="btn btn-sm btn-dark"
                                >
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