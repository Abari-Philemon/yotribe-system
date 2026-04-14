<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$date = $_GET['date'] ?? date('Y-m-d');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Feeding Report");

// Header
$sheet->setCellValue('A1', 'Yotribe Agro Allied Services');
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A2', "Feeding Report - {$date}");
$sheet->mergeCells('A2:D2');

$sheet->setCellValue('A4','Pond');
$sheet->setCellValue('B4','Feed');
$sheet->setCellValue('C4','Qty (kg)');
$sheet->setCellValue('D4','Fed By');

// Fetch data
$feedings = $pdo->prepare("
    SELECT p.pond_code, f.feed_type, f.quantity_kg, s.full_name
    FROM feeding_logs f
    JOIN ponds_tanks p ON p.id = f.pond_id
    JOIN staff s ON s.id = f.fed_by
    WHERE f.date=?
");
$feedings->execute([$date]);
$data = $feedings->fetchAll();

$row = 5;
foreach($data as $d){
    $sheet->setCellValue("A{$row}", $d['pond_code']);
    $sheet->setCellValue("B{$row}", $d['feed_type']);
    $sheet->setCellValue("C{$row}", $d['quantity_kg']);
    $sheet->setCellValue("D{$row}", $d['full_name']);
    $row++;
}

// Output
$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"feeding_report_{$date}.xlsx\"");
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
