<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

/**
 * LOAD SECTIONS (BY FARM)
 */
$stmt = $pdo->prepare("
    SELECT id, name 
    FROM sections 
    WHERE farm_id = ? 
    ORDER BY name
");
$stmt->execute([$farm_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * LOAD SUB-SECTIONS (BY FARM)
 */
$stmt = $pdo->prepare("
    SELECT id, section_id, name 
    FROM sub_sections 
    WHERE farm_id = ?
    ORDER BY name
");
$stmt->execute([$farm_id]);
$subsections = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Create Pond</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container mt-4">

<h4>Create Pond / Tank</h4>

<form method="POST" action="store.php" class="card p-3">

<!-- Pond Code -->
<div class="mb-3">
    <label>Pond Code</label>
    <input type="text" name="pond_code" class="form-control" required>
</div>

<!-- SECTION -->
<div class="mb-3">
    <label>Section</label>
    <select name="section_id" id="section" class="form-select" required>
        <option value="">Select Section</option>
        <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['id'] ?>">
                <?= htmlspecialchars($sec['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- SUB SECTION -->
<div class="mb-3">
    <label>Sub Section</label>
    <select name="sub_section_id" id="subsection" class="form-select" required>
        <option value="">Select Sub Section</option>
    </select>
</div>

<!-- TYPE -->
<div class="mb-3">
    <label>Pond Type</label>
    <select name="pond_type" class="form-select">
        <option value="tank">Tank</option>
        <option value="tarpaulin">Tarpaulin</option>
    </select>
</div>

<!-- SIZE -->
<div class="mb-3">
    <label>Size Label</label>
    <input type="text" name="size_label" class="form-control" placeholder="e.g 12x12">
</div>

<!-- DIMENSIONS -->
<div class="row">
    <div class="col-md-6 mb-3">
        <label>Length (ft)</label>
        <input type="number" name="length_ft" class="form-control">
    </div>

    <div class="col-md-6 mb-3">
        <label>Width (ft)</label>
        <input type="number" name="width_ft" class="form-control">
    </div>
</div>

<!-- VOLUME -->
<div class="mb-3">
    <label>Volume (Liters)</label>
    <input type="number" name="volume_liters" class="form-control">
</div>

<!-- CAPACITY -->
<div class="mb-3">
    <label>Fish Capacity</label>
    <input type="number" name="capacity" class="form-control" required>
</div>

<!-- STATUS -->
<div class="mb-3">
    <label>Status</label>
    <select name="status" class="form-select">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="maintenance">Maintenance</option>
    </select>
</div>

<button type="submit" class="btn btn-primary">Save Pond</button>

</form>

<script>
// ALL SUBSECTIONS FROM PHP
const subSections = <?= json_encode($subsections) ?>;

const sectionSelect = document.getElementById('section');
const subSelect = document.getElementById('subsection');

sectionSelect.addEventListener('change', function () {

    const sectionId = this.value;

    // RESET
    subSelect.innerHTML = '<option value="">Select Sub Section</option>';

    // FILTER + APPEND
    subSections.forEach(sub => {
        if (sub.section_id == sectionId) {
            const opt = document.createElement('option');
            opt.value = sub.id;
            opt.textContent = sub.name;
            subSelect.appendChild(opt);
        }
    });

});
</script>

</body>
</html>