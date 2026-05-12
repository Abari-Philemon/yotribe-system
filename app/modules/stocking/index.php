<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

requireModuleAccess('module_name');

$farm_id = farm_id();

$stmt = $pdo->prepare("
SELECT p.pond_code, SUM(ps.current_count) as fish
FROM ponds_tanks p
LEFT JOIN pond_stocking ps ON ps.pond_id = p.id AND ps.status='active'
WHERE p.farm_id = ?
GROUP BY p.id
ORDER BY p.pond_code
");
$stmt->execute([$farm_id]);
$data = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>



<h4>Live Stock Status</h4>

<a href="create.php" class="btn btn-primary mb-3">+ Stock Fish</a>

<table class="table table-bordered">
<tr><th>Pond</th><th>Fish Count</th></tr>

<?php foreach ($data as $d): ?>
<tr>
<td><?= $d['pond_code'] ?></td>
<td><?= number_format($d['fish']) ?></td>
</tr>
<?php endforeach; ?>

</table>

</body>
</html>