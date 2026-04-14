<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('feeding');

$stmt = $pdo->query("
    SELECT p.pond_code,
           SUM(fl.quantity_kg) AS total_feed,
           MAX(fi.estimated_weight_kg) - MIN(fi.estimated_weight_kg) AS biomass_gain
    FROM feeding_logs fl
    JOIN ponds_tanks p ON p.id = fl.pond_id
    JOIN fish_inventory fi ON fi.pond_id = p.id
    GROUP BY p.id
");
$data = $stmt->fetchAll();
?>

<h2>Feed Conversion Ratio (FCR)</h2>

<table border="1" cellpadding="8">
<tr>
    <th>Pond</th>
    <th>Total Feed (kg)</th>
    <th>Biomass Gain (kg)</th>
    <th>FCR</th>
</tr>

<?php foreach ($data as $d): 
    $fcr = ($d['biomass_gain'] > 0)
        ? $d['total_feed'] / $d['biomass_gain']
        : 0;
?>
<tr>
    <td><?= $d['pond_code'] ?></td>
    <td><?= number_format($d['total_feed'],2) ?></td>
    <td><?= number_format($d['biomass_gain'],2) ?></td>
    <td><?= number_format($fcr,2) ?></td>
</tr>
<?php endforeach; ?>
</table>
