<?php
declare(strict_types=1);

require __DIR__ . '/app/config/database.php';

$activeCompanies = $pdo->query(
    "SELECT COUNT(*) FROM companies WHERE status = 'active'"
)->fetchColumn();

$financeSettings = $pdo->query(
    'SELECT COUNT(*) FROM finance_settings'
)->fetchColumn();

echo "Active companies: {$activeCompanies}" . PHP_EOL;
echo "Finance settings: {$financeSettings}" . PHP_EOL;