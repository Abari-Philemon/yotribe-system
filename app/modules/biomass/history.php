<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('biomass');

$rows = $pdo->query("
    SELECT m.date, p.pond_code, m.dead_count, m.suspected_cause
    FROM mortality_logs m
    JOIN ponds_tanks p ON p.id = m.pond_id
    ORDER BY m.date DESC
")->fetchAll();
?>

<h2>Mortality History</h2>

<table border="1" cellpadding="8">
<tr>
    <th>Date</th>
    <th>Pond</th>
    <th>Deaths</th>
    <th>Cause</th>
</tr>

<?php foreach ($rows as $r): ?>
<tr>
    <td><?= $r['date'] ?></td>
    <td><?= $r['pond_code'] ?></td>
    <td><?= $r['dead_count'] ?></td>
    <td><?= $r['suspected_cause'] ?></td>
</tr>
<?php endforeach; ?>
</table>
