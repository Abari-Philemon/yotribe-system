<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/farm_context.php';

$farm_id = farm_id();

authorize('feeding');

$stmt = $pdo->query("
    SELECT f.date, p.pond_code, f.feed_type, f.quantity_kg, f.time, s.full_name
    FROM feeding_logs f
    JOIN ponds_tanks p ON p.id = f.pond_id
    JOIN staff s ON s.id = f.fed_by
    ORDER BY f.date DESC, f.time DESC
");
$logs = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT * FROM feeding_logs WHERE farm_id = ? AND date = ?");
$stmt->execute([$_SESSION['farm_id'], $date]);

?>

<h2>Feeding Logs</h2>
<table border="1" cellpadding="8">
<tr>
    <th>Date</th>
    <th>Pond</th>
    <th>Feed</th>
    <th>Qty (kg)</th>
    <th>Time</th>
    <th>Fed By</th>
</tr>

<?php foreach ($logs as $l): ?>
<tr>
    <td><?= $l['date'] ?></td>
    <td><?= $l['pond_code'] ?></td>
    <td><?= $l['feed_type'] ?></td>
    <td><?= $l['quantity_kg'] ?></td>
    <td><?= $l['time'] ?></td>
    <td><?= $l['full_name'] ?></td>
</tr>
<?php endforeach; ?>
</table>
