<?php
declare(strict_types=1);

require __DIR__ . '/app/config/database.php';

$queries = [
    'finance_settings' => 'SHOW COLUMNS FROM finance_settings',
    'finance_settings_indexes' => 'SHOW INDEX FROM finance_settings',
];

foreach ($queries as $label => $sql) {
    echo PHP_EOL . '=== ' . $label . ' ===' . PHP_EOL;

    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}