<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("SHOW COLUMNS FROM farms");
echo "=== FARMS TABLE COLUMNS ===\n";
print_r($stmt->fetchAll());

$stmt = $pdo->query("SELECT * FROM farms WHERE id IN (1, 2) ORDER BY id");
echo "\n=== FARM A AND FARM B RECORDS ===\n";
print_r($stmt->fetchAll());
