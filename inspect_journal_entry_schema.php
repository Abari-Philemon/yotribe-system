<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

foreach (['journal_entries', 'journal_entry_lines'] as $table) {
    echo "\nTABLE: {$table}\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        printf(
            "%s | %s | Nullable: %s\n",
            $column['Field'],
            $column['Type'],
            $column['Null']
        );
    }
}