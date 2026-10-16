<?php
require __DIR__ . '/app/config/database.php';

echo "=== ACCOUNT TYPES FOR COMPANY 1 ===\n";

$stmt = $pdo->prepare("
    SELECT
        id,
        uuid,
        company_id,
        type_code,
        type_name,
        normal_balance,
        display_order,
        description,
        is_system,
        is_active
    FROM account_types
    WHERE company_id = 1
    ORDER BY display_order, id
");

$stmt->execute();
$types = $stmt->fetchAll();

foreach ($types as $type) {
    echo "\n";
    echo "ID: " . $type['id'] . "\n";
    echo "Type Code: " . $type['type_code'] . "\n";
    echo "Type Name: " . $type['type_name'] . "\n";
    echo "Normal Balance: " . $type['normal_balance'] . "\n";
    echo "Display Order: " . $type['display_order'] . "\n";
    echo "System: " . $type['is_system'] . "\n";
    echo "Active: " . $type['is_active'] . "\n";
}

echo "\n=== ACCOUNT CLASSES FOR COMPANY 1 ===\n";

$stmt = $pdo->prepare("
    SELECT
        ac.id,
        ac.uuid,
        ac.company_id,
        ac.account_type_id,
        at.type_code,
        at.type_name,
        ac.class_code,
        ac.class_name,
        ac.display_order,
        ac.description,
        ac.is_system,
        ac.is_active
    FROM account_classes ac
    INNER JOIN account_types at
        ON at.id = ac.account_type_id
    WHERE ac.company_id = 1
    ORDER BY at.display_order, ac.display_order, ac.id
");

$stmt->execute();
$classes = $stmt->fetchAll();

foreach ($classes as $class) {
    echo "\n";
    echo "ID: " . $class['id'] . "\n";
    echo "Type ID: " . $class['account_type_id'] . "\n";
    echo "Type Code: " . $class['type_code'] . "\n";
    echo "Type Name: " . $class['type_name'] . "\n";
    echo "Class Code: " . $class['class_code'] . "\n";
    echo "Class Name: " . $class['class_name'] . "\n";
    echo "Display Order: " . $class['display_order'] . "\n";
    echo "System: " . $class['is_system'] . "\n";
    echo "Active: " . $class['is_active'] . "\n";
}

echo "\n=== TOTALS ===\n";
echo "Account Types: " . count($types) . "\n";
echo "Account Classes: " . count($classes) . "\n";
