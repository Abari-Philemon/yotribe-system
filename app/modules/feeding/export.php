<?php
declare(strict_types=1);

// --------------------------------------------------
// BOOTSTRAP
// --------------------------------------------------
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// --------------------------------------------------
// ACCESS CONTROL (MUST BE FIRST)
// --------------------------------------------------
require_role(['storekeeper', 'manager', 'owner', 'super_admin']);

// --------------------------------------------------
// INPUT
// --------------------------------------------------
$farm_id = $_SESSION['farm_id'] ?? null;

if (!$farm_id) {
    http_response_code(403);
    exit('Invalid farm context.');
}

// --------------------------------------------------
// FETCH FEEDING LOGS
// --------------------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        f.date,
        p.pond_code,
        f.feed_type,
        f.quantity_kg,
        s.full_name AS fed_by,
        f.time,
        f.remarks
    FROM feeding_logs f
    INNER JOIN ponds_tanks p ON f.pond_id = p.id
    INNER JOIN staff s ON f.fed_by = s.id
    WHERE f.farm_id = ?
    ORDER BY f.date DESC, f.time DESC
");

$stmt->execute([$farm_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// CREATE SPREADSHEET
// --------------------------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Feeding Logs');

// Header row
$headers = [
    'Date',
    'Pond Code',
    'Feed Type',
    'Quantity (kg)',
    'Fed By',
    'Time',
    'Remarks'
];

$sheet->fromArray($headers, null, 'A1');

// Style header
$sheet->getStyle('A1:G1')->getFont()->setBold(true);

// Data rows
$row = 2;
foreach ($logs as $log) {
    $sheet->fromArray([
        $log['date'],
        $log['pond_code'],
        $log['feed_type'],
        $log['quantity_kg'],
        $log['fed_by'],
        $log['time'],
        $log['remarks']
    ], null, 'A' . $row);

    $row++;
}

// Auto-size columns
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// --------------------------------------------------
// OUTPUT TO BROWSER
// --------------------------------------------------
ob_clean();
flush();

$filename = 'Feeding_Logs_' . date('Y-m-d_H-i-s') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;
