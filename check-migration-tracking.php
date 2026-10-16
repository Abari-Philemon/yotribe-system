<?php
require 'vendor/autoload.php';
require 'app/config/database.php';

$sql = "SHOW TABLES";
$tables = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

$matches = array_values(array_filter(
    $tables,
    static fn($table) => preg_match('/migration|schema_version/i', $table)
));

echo "Migration-related tables: " . count($matches) . PHP_EOL;
print_r($matches);
