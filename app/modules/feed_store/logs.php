<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';


authorize('feed_store');

$stmt = $pdo->query("SELECT * FROM feed_store_logs ORDER BY date DESC, id DESC");
$logs = $stmt->fetchAll();
?>

<h2>Feed Store Logs</h2>

<table border="1" cellpadding="8">
<tr>
    <th>Date</th>
    <th>Feed</th>
    <th>Batch</th>
    <th>Issued (kg)</th>
    <th>Issued To</th>
    <th>Storekeeper</th>
</tr>

<?php foreach ($logs as $l): ?>
<tr>
    <td><?= $l['date'] ?></td>
    <td><?= $l['feed_type'] ?></td>
    <td><?= $l['batch_no'] ?></td>
    <td><?= $l['issued'] ?></td>
    <td><?= $l['issued_to'] ?></td>
    <td><?= $l['storekeeper'] ?></td>
</tr>
<?php endforeach; ?>
</table>

