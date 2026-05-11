<?php
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

require_role(['super_admin','owner']);

$message = '';
$alert = 'success';

/**
 * CSRF TOKEN
 */
if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD FARMS
 */
$stmt = $pdo->prepare("
    SELECT id, name
    FROM farms
    ORDER BY name ASC
");

$stmt->execute();

$farms = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * HANDLE SUBMIT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * CSRF VALIDATION
     */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token');
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role      = $_POST['role'] ?? '';
    $farm_id   = (int) ($_POST['farm_id'] ?? 0);

    /**
     * VALIDATION
     */
    if (
        $full_name === '' ||
        $username === '' ||
        $password === '' ||
        $role === '' ||
        $farm_id <= 0
    ) {

        $message = "All fields are required.";
        $alert = 'danger';

    } elseif (strlen($password) < 6) {

        $message = "Password must be at least 6 characters.";
        $alert = 'danger';

    } else {

        try {

            /**
             * CHECK EXISTING USERNAME
             */
            $stmt = $pdo->prepare("
                SELECT id
                FROM staff
                WHERE username = ?
                LIMIT 1
            ");

            $stmt->execute([$username]);

            if ($stmt->fetch()) {

                throw new Exception(
                    "Username already exists."
                );
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
                    approval_status,
                    status,
                    failed_attempts,
                    locked_until,
                    last_login,
                    remember_token
                )
                VALUES (
                    :full_name,
                    :username,
                    :password,
                    :role,
                    :farm_id,
                    0,
                    'pending',
                    'pending',
                    0,
                    NULL,
                    NULL,
                    NULL
                )
            ");

            $stmt->execute([

                'full_name' => $full_name,
                'username'  => $username,
                'password'  => $hashed_password,
                'role'      => $role,
                'farm_id'   => $farm_id
            ]);

            $message = "
                Registration submitted successfully.
                Awaiting administrator approval.
            ";

            $alert = 'success';

        } catch (Exception $e) {

            $message = $e->getMessage();
            $alert = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Register Staff | Yotribe
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body{
            background:#f4f7fb;
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            font-family:Arial,sans-serif;
            padding:20px;
        }

        .register-card{
            width:100%;
            max-width:550px;
            background:#fff;
            border-radius:20px;
            padding:35px;
            box-shadow:0 15px 40px rgba(0,0,0,.08);
        }

        .brand{
            font-size:30px;
            font-weight:700;
            color:#198754;
        }

        .subtitle{
            color:#6c757d;
            margin-bottom:25px;
        }

        .form-control,
        .form-select{
            height:50px;
            border-radius:12px;
        }

        textarea.form-control{
            height:auto;
        }

        .btn-submit{
            height:50px;
            border-radius:12px;
            font-weight:600;
        }

    </style>

</head>

<body>

<div class="register-card">

    <div class="text-center mb-4">

        <div class="brand">
            🐟 Yotribe
        </div>

        <div class="subtitle">
            Staff Registration Portal
        </div>

    </div>

    <?php if ($message): ?>

        <div class="alert alert-<?= $alert ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= $_SESSION['csrf_token'] ?>"
        >

        <!-- FULL NAME -->
        <div class="mb-3">

            <label class="form-label fw-semibold">
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

            <label class="form-label fw-semibold">
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

            <label class="form-label fw-semibold">
                Password
            </label>

            <input
                type="password"
                name="password"
                class="form-control"
                required
            >

        </div>

        <!-- ROLE -->
        <div class="mb-3">

            <label class="form-label fw-semibold">
                Role
            </label>

            <select
                name="role"
                class="form-select"
                required
            >

                <option value="">
                    Select Role
                </option>

                <option value="manager">
                    Manager
                </option>

                <option value="storekeeper">
                    Store Keeper
                </option>

                <option value="hatchery">
                    Hatchery Officer
                </option>

                <option value="production">
                    Production Staff
                </option>

            </select>

        </div>

        <!-- FARM -->
        <div class="mb-4">

            <label class="form-label fw-semibold">
                Farm
            </label>

            <select
                name="farm_id"
                class="form-select"
                required
            >

                <option value="">
                    Select Farm
                </option>

                <?php foreach($farms as $farm): ?>

                    <option value="<?= $farm['id'] ?>">

                        <?= htmlspecialchars(
                            $farm['farm_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <!-- SUBMIT -->
        <button
            type="submit"
            class="btn btn-success btn-submit w-100"
        >
            Create Staff Account
        </button>

        <!-- LOGIN LINK -->
        <div class="text-center mt-3">

            <small class="text-muted">
                Already have an account?
            </small>

            <br>

            <a
                href="/yotribe-system/app/auth/login.php"
                class="text-decoration-none fw-semibold"
            >
                Login Here
            </a>

        </div>

    </form>

</div>

</body>
</html>