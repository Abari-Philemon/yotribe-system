<?php
require __DIR__ . '\app\config\database.php';

$rows = $pdo->query(
    'SELECT company_id, farm_id, created_at
     FROM company_farms
     WHERE company_id = 1
     ORDER BY farm_id'
)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "NO FARM LINKS FOUND FOR COMPANY 1";
    exit;
}

print_r($rows);
