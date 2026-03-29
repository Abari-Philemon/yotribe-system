<?php
require_once __DIR__ . '/../middleware/auth_guard.php';
require_role(['super_admin','owner']);

require_once __DIR__ . '/../config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role      = $_POST['role'];
    $farm_id   = $_POST['farm_id'];

    $stmt = $pdo->prepare("
        INSERT INTO staff (full_name, username, password, role, farm_id, active)
        VALUES (:full_name, :username, :password, :role, :farm_id, 1)
    ");

    $stmt->execute([
        'full_name' => $full_name,
        'username'  => $username,
        'password'  => $password,
        'role'      => $role,
        'farm_id'   => $farm_id
    ]);

    $message = "Staff account created successfully.";
}
?>

<h2>Create Staff Account</h2>

<?php if ($message): ?>
<p style="color:green"><?= $message ?></p>
<?php endif; ?>

<form method="POST">
    <label>Full Name</label><br>
    <input type="text" name="full_name" required><br><br>

    <label>Username</label><br>
    <input type="text" name="username" required><br><br>

    <label>Password</label><br>
    <input type="password" name="password" required><br><br>

    <label>Role</label><br>
    <select name="role" required>
        <option value="manager">Manager</option>
        <option value="storekeeper">Store Keeper</option>
        <option value="hatchery">Hatchery Officer</option>
        <option value="production">Production Staff</option>
    </select><br><br>

    <label>Farm</label><br>
    <select name="farm_id" required>
        <option value="1">Farm 1</option>
        <option value="2">Farm 2</option>
    </select><br><br>

    <button type="submit">Create Account</button>
</form>
