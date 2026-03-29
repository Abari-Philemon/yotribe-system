<?php
session_start();
require_once __DIR__ . '/../config/database.php';

/**
 * Already logged in
 */
if (isset($_SESSION['staff_id'])) {
    header("Location: /yotribe-system/app/modules/dashboard/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($username === '' || $password === '') {
        $error = "Username and password are required.";
    } else {

        $stmt = $pdo->prepare("
            SELECT 
                id,
                full_name,
                username,
                password,
                role,
                farm_id,
                active,
                approval_status
            FROM staff
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Invalid login credentials.";
        } elseif ($user['approval_status'] !== 'approved') {
            $error = "Account pending admin approval.";
        } elseif ((int)$user['active'] !== 1) {
            $error = "Account is disabled. Contact administrator.";
        } elseif (!password_verify($password, $user['password'])) {
            $error = "Invalid login credentials.";
        } else {

            /**
             * LOGIN SUCCESS
             */
            $_SESSION['staff_id']  = (int)$user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            /**
             * FARM CONTEXT
             * (Do NOT force farm if user can manage multiple farms)
             */
            if (!empty($user['farm_id'])) {
                $_SESSION['farm_id'] = (int)$user['farm_id'];
            }

            /**
             * Remember Me
             */
            if ($remember) {
                setcookie(
                    'yotribe_auth',
                    base64_encode(json_encode([
                        'id'   => $user['id'],
                        'role' => $user['role']
                    ])),
                    time() + (86400 * 30),
                    '/',
                    '',
                    false,
                    true
                );
            }

            /**
             * Redirect
             */
            header("Location: /yotribe-system/app/modules/dashboard/index.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login | Yotribe Agro Allied Services</title>
    <link rel="stylesheet" href="/yotribe-system/public/css/style.css">
</head>
<body>

<h2>Staff Login</h2>

<?php if ($error): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="POST">
    <label>Username</label><br>
    <input type="text" name="username" required><br><br>

    <label>Password</label><br>
    <input type="password" name="password" required><br><br>

    <label>
        <input type="checkbox" name="remember"> Remember Me
    </label><br><br>

    <button type="submit">Login</button>
</form>

</body>
</html>

