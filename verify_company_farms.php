<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->prepare("
    SELECT
        c.id AS company_id,
        c.legal_name,
        f.id AS farm_id,
        f.name AS farm_name
    FROM company_farms cf
    INNER JOIN companies c ON c.id = cf.company_id
    INNER JOIN farms f ON f.id = cf.farm_id
    WHERE c.id = ?
    ORDER BY f.id
");

$stmt->execute([1]);
print_r($stmt->fetchAll());
