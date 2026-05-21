<?php

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../helpers/permission.php';

require_permission('staff');

$farm_id = farm_id();
$current_role = $_SESSION['role'] ?? '';

$page_title = "Create Staff";

$message = '';
$alert = 'success';

/**
 * =========================================================
 * LOAD ROLE PERMISSIONS FILE
 * =========================================================
 */
$permissions = require __DIR__ . '/../../config/permissions.php';
$available_roles = array_keys($permissions);

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
 * HANDLE CREATE STAFF
 * =========================================================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        $_POST['csrf_token'] !== $_SESSION['csrf_token']
    ) {
        die("Invalid CSRF token");
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? '';

    /**
     * =====================================================
     * VALIDATION
     * =====================================================
     */
    if (!$full_name || !$username || !$password || !$role) {
        $message = "All fields are required.";
        $alert = "danger";
    }

    /**
     * =====================================================
     * ROLE VALIDATION (FROM PERMISSION FILE)
     * =====================================================
     */
    elseif (!in_array($role, $available_roles)) {
        $message = "Invalid role selected.";
        $alert = "danger";
    }

    else {

        /**
         * =================================================
         * CHECK DUPLICATE USERNAME
         * =================================================
         */
        $check = $pdo->prepare("SELECT id FROM staff WHERE username = ?");
        $check->execute([$username]);

        if ($check->fetch()) {

            $message = "Username already exists.";
            $alert = "danger";

        } else {

            /**
             * =================================================
             * INSERT STAFF
             * =================================================
             */
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO staff
                (full_name, username, password, role, farm_id, status, active, approval_status, created_at)
                VALUES
                (?, ?, ?, ?, ?, 'pending', 0, 'pending', NOW())
            ");

            $stmt->execute([
                $full_name,
                $username,
                $hashed_password,
                $role,
                $farm_id
            ]);

            $message = "Staff created successfully.";
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

?>

<style>
.card-box{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 30px rgba(0,0,0,.06);
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>
        <h3 class="mb-1">➕ Create Staff</h3>
        <small class="text-muted">Add new staff under this farm</small>
    </div>

    <a href="manage.php" class="btn btn-secondary">
        ← Back
    </a>

</div>

<?php if($message): ?>
<div class="alert alert-<?= htmlspecialchars($alert) ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="card card-box">
    <div class="card-body">

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Role</label>
                <select name="role" class="form-control" required>

                    <option value="">-- Select Role --</option>

                    <?php foreach ($available_roles as $r): ?>
                        <option value="<?= $r ?>">
                            <?= ucfirst(str_replace('_', ' ', $r)) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <button class="btn btn-success w-100">
                Create Staff
            </button>

        </form>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>