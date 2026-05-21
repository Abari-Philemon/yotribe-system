<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';

/**
 * MODULE ACCESS
 */
require_permission('staff');

$farm_id = farm_id();

$message = '';
$alert = 'success';


$id = (int)($_GET['id'] ?? 0);

if($id > 0){

    $stmt = $pdo->prepare("
        UPDATE staff
        SET
            approval_status = 'approved',
            status = 'active',
            active = 1
        WHERE id = ?
    ");

    $stmt->execute([$id]);
}

header('Location: manage.php');
exit;