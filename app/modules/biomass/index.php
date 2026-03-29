<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('biomass');

$stmt = $pdo->query("
    SELECT p.pond_code, p.pond_type,
           f.estimated_weight_kg, f.last_updated
    FROM ponds_tanks p
    LEFT JOIN fish_inventory f ON f.pond_id = p.id
");
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Pond Biomass Overview</h2>

<table border="1" cellpadding="8">
<tr>
    <th>Pond</th>
    <th>Type</th>
    <th>Estimated Biomass (kg)</th>
    <th>Last Update</th>
</tr>

<?php foreach ($ponds as $p): ?>
<tr>
    <td><?= $p['pond_code'] ?></td>
    <td><?= $p['pond_type'] ?></td>
    <td><?= number_format($p['estimated_weight_kg'],2) ?></td>
    <td><?= $p['last_updated'] ?></td>
</tr>
<?php endforeach; ?>
</table>
