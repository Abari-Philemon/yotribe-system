<?php
require __DIR__ . '/app/config/database.php';

$accountId = 1;

echo "=== FOREIGN KEYS REFERENCING accounts(id) ===\n";

$sql = "
    SELECT
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME = 'accounts'
      AND REFERENCED_COLUMN_NAME = 'id'
    ORDER BY TABLE_NAME, COLUMN_NAME
";

$stmt = $pdo->query($sql);
$references = $stmt->fetchAll();

foreach ($references as $reference) {
    echo $reference['TABLE_NAME']
        . '.' . $reference['COLUMN_NAME']
        . ' [' . $reference['CONSTRAINT_NAME'] . "]\n";
}

echo "\n=== ROW COUNTS FOR ACCOUNT ID 1 IN REFERENCING TABLES ===\n";

foreach ($references as $reference) {
    $table = $reference['TABLE_NAME'];
    $column = $reference['COLUMN_NAME'];

    $stmt = $pdo->query(
        "SELECT COUNT(*) AS total
         FROM `{$table}`
         WHERE `{$column}` = 1"
    );

    $row = $stmt->fetch();

    echo $table . '.' . $column
        . ': ' . ($row['total'] ?? 0) . "\n";
}

