<?php
require 'app/config/database.php';

$checks = [
    'journal_count' => 'SELECT COUNT(*) FROM journal_entries WHERE company_id = 1',
    'journal_line_count' => 'SELECT COUNT(*) FROM journal_entry_lines',
    'journal_sequence' => "SELECT last_number FROM finance_document_sequences WHERE company_id = 1 AND document_type_id = 1 AND financial_year = 2026 AND is_active = 1"
];

foreach ($checks as $label => $sql) {
    $value = $pdo->query($sql)->fetchColumn();
    echo $label . ': ' . ($value === false ? 'NOT FOUND' : $value) . PHP_EOL;
}
