<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/database.php';

foreach (['financial_years', 'financial_periods'] as $table) {
    echo "\n--- INDEXES: {$table} ---\n";

    $statement = $pdo->query("SHOW INDEX FROM `{$table}`");

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo 'Key=' . $row['Key_name']
            . ' | Unique=' . ((int) $row['Non_unique'] === 0 ? 'YES' : 'NO')
            . ' | Column=' . $row['Column_name']
            . ' | Sequence=' . $row['Seq_in_index']
            . PHP_EOL;
    }

    echo "\n--- FOREIGN KEYS: {$table} ---\n";

    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME, CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION'
    );

    $statement->execute(['table_name' => $table]);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        echo "No foreign keys found.\n";
    }

    foreach ($rows as $row) {
        echo $row['CONSTRAINT_NAME']
            . ' | ' . $row['COLUMN_NAME']
            . ' -> ' . $row['REFERENCED_TABLE_NAME']
            . '.' . $row['REFERENCED_COLUMN_NAME']
            . PHP_EOL;
    }
}
