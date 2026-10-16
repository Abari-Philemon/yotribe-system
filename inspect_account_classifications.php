<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

foreach (['account_types', 'account_classes'] as $table) {
    echo "\n--- {$table} ---\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    echo "Columns: " . implode(', ', $columns) . "\n";

    $stmt = $pdo->query("SELECT * FROM `{$table}` WHERE company_id = 1 ORDER BY id");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $display = array_intersect_key(
            $row,
            array_flip([
                'id', 'company_id', 'type_code', 'type_name',
                'class_code', 'class_name', 'normal_balance',
                'is_active', 'is_system'
            ])
        );

        echo json_encode($display, JSON_UNESCAPED_SLASHES) . "\n";
    }
}