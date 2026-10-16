<?php
declare(strict_types=1);

require_once __DIR__ . '/app/config/database.php';

$tables = ['financial_years', 'financial_periods', 'journal_entries'];

foreach ($tables as $table) {
    echo "\n=== INDEXES: {$table} ===\n";
    $safe = str_replace('`', '``', $table);

    foreach ($pdo->query("SHOW INDEX FROM `{$safe}`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo $row['Key_name']
            . ' | unique=' . (int) ($row['Non_unique'] === '0')
            . ' | column=' . $row['Column_name']
            . ' | seq=' . $row['Seq_in_index'] . "\n";
    }

    echo "\n=== FOREIGN KEYS: {$table} ===\n";
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND REFERENCED_TABLE_NAME IS NOT NULL'
    );
    $stmt->execute(['table' => $table]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo $row['COLUMN_NAME'] . ' -> '
            . $row['REFERENCED_TABLE_NAME'] . '.'
            . $row['REFERENCED_COLUMN_NAME'] . "\n";
    }
}

foreach (['financial_years', 'financial_periods'] as $table) {
    echo "\n=== EXISTING DATA: {$table} ===\n";
    $safe = str_replace('`', '``', $table);

    foreach ($pdo->query(
        "SELECT id, company_id, start_date, end_date, status, is_current
         FROM `{$safe}` ORDER BY company_id, start_date"
    )->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
    }
}