<?php
require_once __DIR__ . '/app/config/database.php';

$sql = "
    SELECT TABLE_NAME
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
          TABLE_NAME LIKE '%migration%'
          OR TABLE_NAME LIKE '%schema_version%'
      )
    ORDER BY TABLE_NAME
";

$tables = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

if (!$tables) {
    echo "No migration-tracking table found." . PHP_EOL;
} else {
    echo "Possible migration-tracking tables:" . PHP_EOL;
    foreach ($tables as $table) {
        echo "- " . $table . PHP_EOL;
    }
}
