<?php
require __DIR__ . '/app/config/database.php';

$sql = "
    SELECT f.id, f.name, f.status, cf.company_id
    FROM farms f
    INNER JOIN company_farms cf ON cf.farm_id = f.id
    WHERE cf.company_id = 1
    ORDER BY f.id
";

foreach ($pdo->query($sql)->fetchAll() as $row) {
    echo implode(' | ', $row) . PHP_EOL;
}
