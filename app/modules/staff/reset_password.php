<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('staff');

$id = (int)$_GET['id'];
$newPassword = 'ChangeMe123!';

$hash = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE staff SET password=? WHERE id=?");
$stmt->execute([$hash, $id]);

echo "Password reset successful. New password: <strong>$newPassword</strong>";
echo "<br><a href='manage.php'>Back</a>";
