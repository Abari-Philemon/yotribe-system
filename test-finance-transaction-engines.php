<?php
require __DIR__ . '/app/config/database.php';

$tables = [
    'accounts',
    'journal_entries',
    'journal_entry_lines',
    'finance_document_sequences',
];

$stmt = $pdo->prepare(
    "SELECT TABLE_NAME, ENGINE
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME IN (?, ?, ?, ?)
     ORDER BY TABLE_NAME"
);
$stmt->execute($tables);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo $row['TABLE_NAME'] . ' | ' . $row['ENGINE'] . "\n";
}

if (count($rows) !== count($tables)) {
    echo "STOP: One or more required tables were not found.\n";
    exit(1);
}

foreach ($rows as $row) {
    if (strtoupper((string) $row['ENGINE']) !== 'INNODB') {
        echo "STOP: A required table is not using InnoDB.\n";
        exit(1);
    }
}

echo "PASS: All required tables use InnoDB.\n";
