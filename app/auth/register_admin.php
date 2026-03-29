<?php
require '../config/database.php';

// OPTIONAL: Run once to create initial super_admin
$username = 'admin';
$password = 'Admin123!'; 
$full_name = 'System Admin';
$role = 'super_admin';
$farm_id = 1;

// Hash password
$password_hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO staff (username, password, role, full_name, farm_id) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$username, $password_hash, $role, $full_name, $farm_id]);

echo "Super Admin created successfully.";
?>
