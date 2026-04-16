<?php
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

$sections = $pdo->prepare("SELECT id, name FROM sections WHERE farm_id = ?");
$sections->execute([$farm_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);
?>

<form method="POST" action="store.php">

<input type="text" name="pond_code" placeholder="Pond Code" required>

<select name="section_id" required>
<?php foreach ($sections as $sec): ?>
<option value="<?= $sec['id'] ?>"><?= $sec['name'] ?></option>
<?php endforeach; ?>
</select>

<input type="text" name="pond_type" placeholder="Type (Tank, Tapolin)">
<input type="text" name="size_label" placeholder="e.g 12x12">

<input type="number" name="length_ft" placeholder="Length">
<input type="number" name="width_ft" placeholder="Width">
<input type="number" name="volume_liters" placeholder="Volume">

<input type="number" name="capacity" placeholder="Fish Capacity">

<select name="status">
<option value="active">Active</option>
<option value="inactive">Inactive</option>
</select>

<button type="submit">Save</button>
</form>