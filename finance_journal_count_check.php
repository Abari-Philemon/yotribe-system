<?php
require_once __DIR__ . '/app/config/database.php';

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_entries,
        MIN(journal_date) AS first_journal_date,
        MAX(journal_date) AS last_journal_date
    FROM journal_entries
");

$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Journal entries: " . ($result['total_entries'] ?? 0) . PHP_EOL;
echo "First journal date: " . ($result['first_journal_date'] ?? 'None') . PHP_EOL;
echo "Last journal date: " . ($result['last_journal_date'] ?? 'None') . PHP_EOL;
