<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/../../helpers/rbac.php';

/**
 * MODULE ACCESS
 */
require_permission('reports');

$farm_id = farm_id();

$message = '';
$alert = 'success';

/**
 * =========================================================
 * VALIDATE USER ID
 * =========================================================
 */

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    die('Invalid staff ID');
}

/**
 * =========================================================
 * LOAD STAFF
 * =========================================================
 */

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        username,
        role,
        status
    FROM staff
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    die('Staff account not found');
}

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
 * HANDLE PASSWORD RESET
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token');
    }

    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    /**
     * VALIDATION
     */

    if ($new_password === '') {

        $message = "Password is required.";
        $alert = 'danger';

    } elseif (strlen($new_password) < 8) {

        $message = "Password must be at least 8 characters.";
        $alert = 'danger';

    } elseif ($new_password !== $confirm_password) {

        $message = "Passwords do not match.";
        $alert = 'danger';

    } else {

        $hashed_password = password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );

        /**
         * RESET PASSWORD
         */

        $stmt = $pdo->prepare("
            UPDATE staff
            SET
                password = ?,
                failed_attempts = 0,
                locked_until = NULL,
                remember_token = NULL
            WHERE id = ?
        ");

        $stmt->execute([
            $hashed_password,
            $id
        ]);

        $message = "Password reset successfully.";
        $alert = 'success';
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>

.reset-card{
    border:none;
    border-radius:20px;
    box-shadow:0 10px 30px rgba(0,0,0,.05);
}

</style>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h3 class="mb-1">
            🔐 Reset Staff Password
        </h3>

        <small class="text-muted">
            Update account password securely
        </small>

    </div>

    <a href="manage.php" class="btn btn-light">
        ← Back
    </a>

</div>

<?php if($message): ?>

<div class="alert alert-<?= $alert ?>">
    <?= htmlspecialchars($message) ?>
</div>

<?php endif; ?>

<div class="row justify-content-center">

    <div class="col-lg-6">

        <div class="card reset-card">

            <div class="card-body p-4">

                <div class="mb-4">

                    <h5 class="mb-1">
                        <?= htmlspecialchars($staff['full_name']) ?>
                    </h5>

                    <div class="text-muted">

                        Username:
                        <strong>
                            <?= htmlspecialchars($staff['username']) ?>
                        </strong>

                    </div>

                    <div class="mt-2">

                        <span class="badge bg-dark">
                            <?= ucfirst($staff['role']) ?>
                        </span>

                        <?php if($staff['status'] === 'active'): ?>

                            <span class="badge bg-success">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="badge bg-danger">
                                <?= ucfirst($staff['status']) ?>
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= $_SESSION['csrf_token'] ?>"
                    >

                    <!-- PASSWORD -->
                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            New Password
                        </label>

                        <input
                            type="password"
                            name="new_password"
                            class="form-control"
                            minlength="8"
                            required
                        >

                    </div>

                    <!-- CONFIRM -->
                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            class="form-control"
                            minlength="8"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Reset Password
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
