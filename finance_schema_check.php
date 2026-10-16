<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/config/database.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "ERROR: Project PDO connection is unavailable.\n");
    exit(1);
}

$stmt = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
          TABLE_NAME LIKE '%farm%'
          OR TABLE_NAME = 'journal_entries'
      )
    ORDER BY TABLE_NAME, ORDINAL_POSITION
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['TABLE_NAME'] . ' | '
        . $row['COLUMN_NAME'] . ' | '
        . $row['COLUMN_TYPE'] . PHP_EOL;
}
