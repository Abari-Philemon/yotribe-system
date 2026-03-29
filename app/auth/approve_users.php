<?php
require_once __DIR__ . '/../middleware/auth_guard.php';
require_role(['super_admin','owner']);

require_once __DIR__ . '/../config/database.php';

if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    $pdo->prepare("
        UPDATE staff SET approval_status='approved', active=1 WHERE id=?
    ")->execute([$id]);
}

$users = $pdo->query("
    SELECT * FROM staff WHERE approval_status='pending'
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Pending Staff Approvals</h2>

<table border="1">
<tr>
    <th>Name</th>
    <th>Role</th>
    <th>Farm</th>
    <th>Action</th>
</tr>

<?php foreach ($users as $u): ?>
<tr>
    <td><?= htmlspecialchars($u['full_name']) ?></td>
    <td><?= $u['role'] ?></td>
    <td><?= $u['farm_id'] ?></td>
    <td>
        <a href="?approve=<?= $u['id'] ?>">Approve</a>
    </td>
</tr>
<?php endforeach; ?>
</table>
