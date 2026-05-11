<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['super_admin','owner']);

$id = (int)($_GET['id'] ?? 0);

if($id > 0){

    $stmt = $pdo->prepare("
        UPDATE staff
        SET
            status = 'disabled',
            active = 0
        WHERE id = ?
    ");

    $stmt->execute([$id]);
}

header('Location: manage.php');
exit;