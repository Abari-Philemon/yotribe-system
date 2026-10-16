<?php
require __DIR__ . '/app/config/database.php';

echo "=== FINANCE SETTINGS ===\n";
$stmt = $pdo->query("SELECT * FROM finance_settings WHERE company_id = 1");
print_r($stmt->fetchAll());

echo "\n=== POSTING RULES ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM finance_posting_rules");
print_r($stmt->fetchAll());

echo "\n=== FINANCE SETTINGS COLUMNS ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM finance_settings");
print_r($stmt->fetchAll());
