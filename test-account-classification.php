<?php
require __DIR__ . '/app/config/database.php';

$stmt = $pdo->query(
    "SELECT t.id AS type_id, t.type_code, t.normal_balance,
            c.id AS class_id, c.class_code
     FROM account_types t
     INNER JOIN account_classes c
       ON c.account_type_id = t.id
      AND c.company_id = t.company_id
     WHERE t.company_id = 1
       AND t.type_code = 'ASSET'
       AND c.class_code = 'CA'
       AND t.is_active = 1
       AND c.is_active = 1
     LIMIT 1"
);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "STOP: No active ASSET/CA classification found.\n";
    exit(1);
}

echo "Active current-asset classification:\n";
echo "Type ID: " . $row['type_id'] . "\n";
echo "Type code: " . $row['type_code'] . "\n";
echo "Class ID: " . $row['class_id'] . "\n";
echo "Class code: " . $row['class_code'] . "\n";
echo "Normal balance: " . $row['normal_balance'] . "\n";
