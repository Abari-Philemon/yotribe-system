<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('staff');

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("UPDATE staff SET status='active' WHERE id=?");
$stmt->execute([$id]);

header("Location: manage.php");
exit;
