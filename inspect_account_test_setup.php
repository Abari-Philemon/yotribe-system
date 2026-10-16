<?php
declare(strict_types=1);

$file = __DIR__ . '/test_account_update.php';
$source = file_get_contents($file);

if ($source === false) {
    exit("Could not read test_account_update.php\n");
}

echo "--- TEST BOOTSTRAP AND SERVICE SETUP ---\n";
echo substr($source, 0, min(6500, strlen($source)));

echo "\n--- TEST CLEANUP ---\n";
$position = stripos($source, 'finally');
if ($position !== false) {
    echo substr($source, max(0, $position - 250), 1000);
} else {
    echo "No finally block found in inspected section.\n";
}