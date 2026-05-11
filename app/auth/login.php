<?php
session_start();

require_once __DIR__ . '/../config/database.php';

/**
 * =========================================================
 * LOGIN PAGE
 * =========================================================
 */

if (isset($_SESSION['staff_id'])) {

    header("Location: /yotribe-system/app/modules/dashboard/index.php");
    exit;
}

$error = '';

/**
 * CSRF TOKEN
 */
if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * HANDLE LOGIN
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

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    /**
     * VALIDATION
     */
    if ($username === '' || $password === '') {

        $error = "Username and password are required.";

    } else {

        /**
         * LOAD USER
         */
        $stmt = $pdo->prepare("
            SELECT
                id,
                full_name,
                username,
                password,
                role,
                farm_id,
                active,
                approval_status,
                failed_attempts,
                locked_until
            FROM staff
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /**
         * INVALID USER
         */
        if (!$user) {

            $error = "Invalid login credentials.";

        } else {

            /**
             * ACCOUNT LOCK
             */
            if (
                !empty($user['locked_until']) &&
                strtotime($user['locked_until']) > time()
            ) {

                $error = "Account temporarily locked. Try again later.";

            } elseif ($user['approval_status'] !== 'approved') {

                $error = "Account pending approval.";

            } elseif ((int)$user['active'] !== 1) {

                $error = "Account disabled.";

            } elseif (!password_verify($password, $user['password'])) {

                /**
                 * FAILED LOGIN
                 */
                $failed = ((int)$user['failed_attempts']) + 1;

                $lock_until = null;

                /**
                 * LOCK AFTER 5 ATTEMPTS
                 */
                if ($failed >= 5) {

                    $lock_until = date(
                        'Y-m-d H:i:s',
                        strtotime('+15 minutes')
                    );

                    $failed = 0;
                }

                $stmt = $pdo->prepare("
                    UPDATE staff
                    SET
                        failed_attempts = ?,
                        locked_until = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $failed,
                    $lock_until,
                    $user['id']
                ]);

                $error = "Invalid login credentials.";

            } else {

                /**
                 * RESET FAILED ATTEMPTS
                 */
                $stmt = $pdo->prepare("
                    UPDATE staff
                    SET
                        failed_attempts = 0,
                        locked_until = NULL,
                        last_login = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $user['id']
                ]);

                /**
                 * REGENERATE SESSION
                 */
                session_regenerate_id(true);

                /**
                 * LOGIN SESSION
                 */
                $_SESSION['staff_id'] = (int)$user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                /**
                 * FARM CONTEXT
                 */
                if (!empty($user['farm_id'])) {

                    $_SESSION['farm_id'] = (int)$user['farm_id'];
                }

                /**
                 * REMEMBER ME
                 */
                if ($remember) {

                    $token = bin2hex(random_bytes(32));

                    setcookie(
                        'yotribe_auth',
                        $token,
                        [
                            'expires'  => time() + (86400 * 30),
                            'path'     => '/',
                            'secure'   => false,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]
                    );

                    /**
                     * SAVE TOKEN
                     */
                    $stmt = $pdo->prepare("
                        UPDATE staff
                        SET remember_token = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        hash('sha256', $token),
                        $user['id']
                    ]);
                }

                /**
                 * REDIRECT
                 */
                header(
                    "Location: /yotribe-system/app/modules/dashboard/index.php"
                );

                exit;
            }
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
        Login | Yotribe Agro Allied Services
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
            font-family:Arial, sans-serif;
        }

        .login-card{
            width:100%;
            max-width:420px;
            background:#fff;
            border-radius:20px;
            padding:35px;
            box-shadow:0 15px 40px rgba(0,0,0,.08);
        }

        .brand{
            font-size:28px;
            font-weight:700;
            color:#198754;
        }

        .subtitle{
            color:#6c757d;
            margin-bottom:25px;
        }

        .form-control{
            height:50px;
            border-radius:12px;
        }

        .btn-login{
            height:50px;
            border-radius:12px;
            font-weight:600;
        }

    </style>

</head>

<body>

<div class="login-card">

    <div class="text-center mb-4">

        <div class="brand">
            🐟 Yotribe
        </div>

        <div class="subtitle">
            Aquaculture Management System
        </div>

    </div>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST" autocomplete="off">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= $_SESSION['csrf_token'] ?>"
        >

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

        <!-- REMEMBER -->
        <div class="form-check mb-4">

            <input
                type="checkbox"
                name="remember"
                class="form-check-input"
                id="remember"
            >

            <label
                class="form-check-label"
                for="remember"
            >
                Remember Me
            </label>

        </div>

        <!-- SUBMIT -->
        <button
            type="submit"
            class="btn btn-success btn-login w-100"
        >
            Login
        </button>

    </form>
    <a href="create.php" class="btn btn-primary btn-sm w-100">
                register
            </a>

</div>

</body>
</html>