<?php
require __DIR__ . '/app/config/database.php';

echo "=== ACCOUNT TYPES ===\n";
$stmt = $pdo->query("
    SELECT *
    FROM account_types
    ORDER BY id
");
print_r($stmt->fetchAll());

echo "\n=== ACCOUNT CLASSES ===\n";
$stmt = $pdo->query("
    SELECT *
    FROM account_classes
    ORDER BY id
");
print_r($stmt->fetchAll());

