<?php
declare(strict_types=1);

require __DIR__ . '/../../app/config/database.php';

echo "=== Company 1 ===\n";
foreach ($pdo->query(
    'SELECT id, uuid, legal_name FROM companies WHERE id = 1'
)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n=== Existing farms ===\n";
foreach ($pdo->query(
    'SELECT id, name, location, status FROM farms ORDER BY id LIMIT 20'
)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n=== Finance document table indexes ===\n";
foreach (['finance_document_types', 'finance_document_sequences'] as $table) {
    echo "\n--- {$table} ---\n";
    foreach ($pdo->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo $row['Key_name'] . ' | ' . $row['Column_name']
            . ' | unique=' . ($row['Non_unique'] === 0 ? 'yes' : 'no') . "\n";
    }
}

echo "\n=== Journal foreign keys ===\n";
$sql = "
    SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'journal_entries'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY ORDINAL_POSITION
";
foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['COLUMN_NAME'] . ' -> '
        . $row['REFERENCED_TABLE_NAME'] . '.'
        . $row['REFERENCED_COLUMN_NAME'] . "\n";
}