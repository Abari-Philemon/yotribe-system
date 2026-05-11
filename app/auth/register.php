<?php
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

require_role(['super_admin','owner']);

$current_role = $_SESSION['role'];
$current_farm = $_SESSION['farm_id'] ?? null;

$message = '';
$alert = 'success';

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD FARMS
 * Super admin sees all farms
 * Owner sees only own farm
 */
if ($current_role === 'super_admin') {

    $stmt = $pdo->query("
        SELECT id, farm_name
        FROM farms
        WHERE status = 'active'
        ORDER BY farm_name ASC
    ");

} else {

    $stmt = $pdo->prepare("
        SELECT id, farm_name
        FROM farms
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$current_farm]);
}

$farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ROLES
 */
$roles = [
    'manager'      => 'Manager',
    'storekeeper'  => 'Store Keeper',
    'hatchery'     => 'Hatchery Officer',
    'production'   => 'Production Staff',
    'staff'        => 'General Staff'
];

/**
 * HANDLE SUBMIT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * CSRF CHECK
     */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('CSRF validation failed');
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = trim($_POST['role'] ?? '');
    $farm_id   = (int) ($_POST['farm_id'] ?? 0);

    /**
     * VALIDATION
     */
    if ($full_name === '') {

        $message = "Full name is required";
        $alert = 'danger';

    } elseif ($username === '') {

        $message = "Username is required";
        $alert = 'danger';

    } elseif (strlen($password) < 6) {

        $message = "Password must be at least 6 characters";
        $alert = 'danger';

    } elseif (!array_key_exists($role, $roles)) {

        $message = "Invalid role selected";
        $alert = 'danger';

    } elseif ($farm_id <= 0) {

        $message = "Invalid farm selected";
        $alert = 'danger';

    } else {

        try {

            /**
             * OWNER CAN ONLY CREATE STAFF
             * INSIDE OWN FARM
             */
            if (
                $current_role === 'owner' &&
                $farm_id != $current_farm
            ) {
                throw new Exception(
                    "Unauthorized farm selection"
                );
            }

            /**
             * CHECK USERNAME
             */
            $stmt = $pdo->prepare("
                SELECT id
                FROM staff
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);

            if ($stmt->fetch()) {
                throw new Exception("Username already exists");
            }

            /**
             * HASH PASSWORD
             */
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            /**
             * INSERT STAFF
             */
            $stmt = $pdo->prepare("
                INSERT INTO staff (
                    full_name,
                    username,
                    password,
                    role,
                    farm_id,
                    active,
                    created_at
                )
                VALUES (
                    :full_name,
                    :username,
                    :password,
                    :role,
                    :farm_id,
                    1,
                    NOW()
                )
            ");

            $stmt->execute([
                'full_name' => $full_name,
                'username'  => $username,
                'password'  => $hashed_password,
                'role'      => $role,
                'farm_id'   => $farm_id
            ]);

            $message = "Staff account created successfully";
            $alert = 'success';

        } catch (Exception $e) {

            $message = $e->getMessage();
            $alert = 'danger';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>

.cardx{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 30px rgba(0,0,0,.05);
}

</style>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h3>👤 Register Staff</h3>

    <a href="index.php" class="btn btn-light">
        ← Back
    </a>

</div>

<?php if($message): ?>

<div class="alert alert-<?= $alert ?>">
    <?= htmlspecialchars($message) ?>
</div>

<?php endif; ?>

<div class="row justify-content-center">

    <div class="col-lg-7">

        <div class="cardx bg-white p-4">

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= $_SESSION['csrf_token'] ?>"
                >

                <!-- FULL NAME -->
                <div class="mb-3">

                    <label class="fw-semibold mb-2">
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="full_name"
                        class="form-control"
                        required
                    >

                </div>

                <!-- USERNAME -->
                <div class="mb-3">

                    <label class="fw-semibold mb-2">
                        Username
                    </label>

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        required
                    >

                </div>

                <!-- PASSWORD -->
                <div class="mb-3">

                    <label class="fw-semibold mb-2">
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        minlength="6"
                        required
                    >

                </div>

                <!-- ROLE -->
                <div class="mb-3">

                    <label class="fw-semibold mb-2">
                        Role
                    </label>

                    <select
                        name="role"
                        class="form-select"
                        required
                    >

                        <?php foreach($roles as $key => $label): ?>

                        <option value="<?= $key ?>">
                            <?= htmlspecialchars($label) ?>
                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- FARM -->
                <div class="mb-4">

                    <label class="fw-semibold mb-2">
                        Farm
                    </label>

                    <select
                        name="farm_id"
                        class="form-select"
                        required
                    >

                        <?php foreach($farms as $farm): ?>

                        <option value="<?= $farm['id'] ?>">

                            <?= htmlspecialchars(
                                $farm['farm_name']
                            ) ?>

                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button class="btn btn-primary w-100">
                    Create Staff Account
                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>