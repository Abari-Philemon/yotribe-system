<?php
declare(strict_types=1);

$file = __DIR__ . '/app/modules/finance/Services/AccountService.php';
$source = file_get_contents($file);

if ($source === false) {
    exit("Could not read AccountService.php\n");
}

$start = strpos($source, '$references = [');
if ($start === false) {
    exit("Reference-check array not found\n");
}

$end = strpos($source, 'foreach ($references as $reference)', $start);
if ($end === false) {
    exit("Reference-check loop not found\n");
}

$end = strpos($source, "\n", $end);
echo substr($source, $start, ($end === false ? strlen($source) : $end) - $start + 1);

echo "\n--- REFERENCE CHECK IMPLEMENTATION ---\n";
echo substr($source, $start, min(6500, strlen($source) - $start));