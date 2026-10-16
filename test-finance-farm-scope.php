<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query(
    "SELECT cf.company_id, f.id AS farm_id, f.name AS farm_name, f.status
     FROM company_farms cf
     INNER JOIN farms f ON f.id = cf.farm_id
     ORDER BY cf.company_id, f.id"
);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "Company ID: {$row['company_id']} | Farm ID: {$row['farm_id']} | Farm: {$row['farm_name']} | Status: {$row['status']}\n";
}
