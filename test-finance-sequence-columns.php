<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("SHOW COLUMNS FROM finance_document_sequences");

foreach ($stmt->fetchAll() as $column) {
    echo $column['Field'] . ' | ' . $column['Type'] . PHP_EOL;
}
