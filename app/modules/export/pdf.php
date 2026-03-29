<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use TCPDF;

$report_type = $_GET['type'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');

$pdf = new TCPDF('L', 'mm', 'A3', true, 'UTF-8', false);
$pdf->SetCreator('Yotribe Agro Allied Services');
$pdf->SetAuthor('Yotribe IFMS');
$pdf->SetTitle("{$report_type} Report");
$pdf->SetMargins(10, 20, 10);
$pdf->AddPage();

// Header
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 15, 'Yotribe Agro Allied Services', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 14);
$pdf->Cell(0, 10, ucfirst($report_type) . " Report - {$date}", 0, 1, 'C');

// Table: Example daily feeding
$feedings = $pdo->prepare("
    SELECT p.pond_code, f.feed_type, f.quantity_kg, s.full_name
    FROM feeding_logs f
    JOIN ponds_tanks p ON p.id = f.pond_id
    JOIN staff s ON s.id = f.fed_by
    WHERE f.date=?
");
$feedings->execute([$date]);
$data = $feedings->fetchAll();

$html = '<table border="1" cellpadding="5">
<tr><th>Pond</th><th>Feed</th><th>Qty (kg)</th><th>Fed By</th></tr>';

foreach($data as $d){
    $html .= "<tr>
        <td>{$d['pond_code']}</td>
        <td>{$d['feed_type']}</td>
        <td>{$d['quantity_kg']}</td>
        <td>{$d['full_name']}</td>
    </tr>";
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output("report_{$report_type}_{$date}.pdf", 'I');
