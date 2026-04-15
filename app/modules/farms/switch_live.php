<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$farm_id = (int)($_POST['farm_id'] ?? 0);

// Validate farm exists
$stmt = $pdo->prepare("SELECT id, name FROM farms WHERE id = ?");
$stmt->execute([$farm_id]);
$farm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$farm) {
    http_response_code(400);
    echo json_encode(['status' => 'error']);
    exit;
}

// Set session
$_SESSION['farm_id']   = $farm['id'];
$_SESSION['farm_name'] = $farm['name'];

echo json_encode(['status' => 'success']);