<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
 // or manual path

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 12);
$pdf->Write(0, 'TCPDF is working!');
$pdf->Output('test.pdf', 'I');


require '../../middleware/auth_guard.php';
require '../../config/database.php';
require '/../../vendor/autoload.php';

require_role(['storekeeper','manager','owner']);

$farm_id = $_SESSION['farm_id'];

$stmt = $pdo->prepare("
    SELECT f.date, p.pond_code, f.feed_type, f.quantity_kg, s.full_name AS fed_by, f.time, f.remarks
    FROM feeding_logs f
    JOIN ponds_tanks p ON f.pond_id = p.id
    JOIN staff s ON f.fed_by = s.id
    WHERE f.farm_id = ?
    ORDER BY f.id DESC
");
$stmt->execute([$farm_id]);
$logs = $stmt->fetchAll();

$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Feeding Logs - Yotribe Agro', 0, 1, 'C');

$html = '<table border="1" cellpadding="4">
<tr>
<th>Date</th><th>Pond</th><th>Feed Type</th><th>Quantity (kg)</th><th>Fed By</th><th>Time</th><th>Remarks</th>
</tr>';

foreach($logs as $log){
    $html .= '<tr>
    <td>'.$log['date'].'</td>
    <td>'.$log['pond_code'].'</td>
    <td>'.$log['feed_type'].'</td>
    <td>'.$log['quantity_kg'].'</td>
    <td>'.$log['fed_by'].'</td>
    <td>'.$log['time'].'</td>
    <td>'.$log['remarks'].'</td>
    </tr>';
}

$html .= '</table>';
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('Feeding_Logs.pdf', 'D');
exit;
