<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = $_SESSION['active_farm_id'] ?? 0;

$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    die('Invalid pond ID');
}

// Fetch pond (STRICT farm isolation)
$stmt = $pdo->prepare("
    SELECT * FROM ponds_tanks 
    WHERE id = ? AND farm_id = ?
");
$stmt->execute([$id, $farm_id]);
$pond = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pond) {
    die('Pond not found or unauthorized');
}

// Fetch sections
$sections = $pdo->prepare("
    SELECT id, name FROM sections WHERE farm_id = ?
");
$sections->execute([$farm_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Pond</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container mt-4">

<h4>Edit Pond</h4>

<form method="POST" action="update.php">

<input type="hidden" name="id" value="<?= $pond['id'] ?>">

<div class="mb-2">
<label>Section</label>
<select name="section_id" class="form-control" required>
<?php foreach ($sections as $sec): ?>
<option value="<?= $sec['id'] ?>"
<?= $sec['id'] == $pond['section_id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($sec['name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-2">
<label>Pond Code</label>
<input type="text" name="pond_code" class="form-control"
value="<?= htmlspecialchars($pond['pond_code']) ?>" required>
</div>

<div class="mb-2">
<label>Pond Type</label>
<input type="text" name="pond_type" class="form-control"
value="<?= htmlspecialchars($pond['pond_type']) ?>">
</div>

<div class="mb-2">
<label>Size Label</label>
<input type="text" name="size_label" class="form-control"
value="<?= htmlspecialchars($pond['size_label']) ?>">
</div>

<div class="mb-2">
<label>Length (ft)</label>
<input type="number" step="0.01" name="length_ft" class="form-control"
value="<?= $pond['length_ft'] ?>">
</div>

<div class="mb-2">
<label>Width (ft)</label>
<input type="number" step="0.01" name="width_ft" class="form-control"
value="<?= $pond['width_ft'] ?>">
</div>

<div class="mb-2">
<label>Volume (Liters)</label>
<input type="number" name="volume_liters" class="form-control"
value="<?= $pond['volume_liters'] ?>">
</div>

<div class="mb-2">
<label>Status</label>
<select name="status" class="form-control">
<option value="active" <?= $pond['status']=='active'?'selected':'' ?>>Active</option>
<option value="inactive" <?= $pond['status']=='inactive'?'selected':'' ?>>Inactive</option>
</select>
</div>

<button class="btn btn-primary">Update Pond</button>

</form>

</body>
</html>