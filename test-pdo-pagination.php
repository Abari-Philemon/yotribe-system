<?php
require 'app/config/database.php';

$stmt = $pdo->prepare('SELECT id FROM accounts ORDER BY id LIMIT :limit OFFSET :offset');

try {
    $stmt->execute([
        'limit' => 3,
        'offset' => 0
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "PASS: LIMIT/OFFSET parameters executed successfully.\n";
    echo "Rows returned: " . count($rows) . "\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
