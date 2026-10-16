<?php
require __DIR__ . '/app/config/database.php';

$sql = "
    SELECT TABLE_NAME, ENGINE
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME IN (
          'journal_entries',
          'journal_entry_lines',
          'finance_document_sequences'
      )
    ORDER BY TABLE_NAME
";

foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['TABLE_NAME'] . ' | ' . $row['ENGINE'] . PHP_EOL;
}
