<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('staff');

// Fetch staff
$stmt = $pdo->query("SELECT id, full_name, username, role, status, farm_id FROM staff ORDER BY created_at DESC");
$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Staff Management</h2>

<table border="1" cellpadding="8">
<tr>
    <th>Name</th>
    <th>Username</th>
    <th>Role</th>
    <th>Status</th>
    <th>Farm</th>
    <th>Action</th>
</tr>

<?php foreach ($staffs as $s): ?>
<tr>
    <td><?= htmlspecialchars($s['full_name']) ?></td>
    <td><?= htmlspecialchars($s['username']) ?></td>
    <td><?= $s['role'] ?></td>
    <td><?= $s['status'] ?></td>
    <td><?= $s['farm_id'] ?></td>
    <td>
        <?php if ($s['status'] === 'pending'): ?>
            <a href="approve.php?id=<?= $s['id'] ?>">Approve</a>
        <?php endif; ?>

        <?php if ($s['status'] === 'active'): ?>
            <a href="disable.php?id=<?= $s['id'] ?>">Disable</a>
        <?php endif; ?>

        <a href="reset_password.php?id=<?= $s['id'] ?>">Reset Password</a>
    </td>
</tr>
<?php endforeach; ?>
</table>
