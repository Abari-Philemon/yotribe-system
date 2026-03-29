<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        INSERT INTO staff (full_name, username, password, role, farm_id, active, approval_status)
        VALUES (:full_name, :username, :password, :role, :farm_id, 0, 'pending')
    ");

    $stmt->execute([
        'full_name' => $_POST['full_name'],
        'username'  => $_POST['username'],
        'password'  => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'role'      => $_POST['role'],
        'farm_id'   => $_POST['farm_id']
    ]);

    echo "Registration submitted. Await admin approval.";
    exit;
}
?>
