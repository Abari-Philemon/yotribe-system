<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

/**
 * LOAD SECTIONS (WITH CODE)
 */
$stmt = $pdo->prepare("
    SELECT id, name, code 
    FROM sections 
    WHERE farm_id = ?
    ORDER BY name
");
$stmt->execute([$farm_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * LOAD SUB-SECTIONS (WITH CODE)
 */
$stmt = $pdo->prepare("
    SELECT id, section_id, name, code 
    FROM sub_sections 
    WHERE farm_id = ?
    ORDER BY name
");
$stmt->execute([$farm_id]);
$subsections = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * COUNT EXISTING PONDS PER SECTION
 */
$stmt = $pdo->prepare("
    SELECT section_id, COUNT(*) as total
    FROM ponds_tanks
    WHERE farm_id = ?
    GROUP BY section_id
");
$stmt->execute([$farm_id]);
$counts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
foreach ($counts_raw as $c) {
    $counts[$c['section_id']] = $c['total'];
}

/**
 * SAFE JSON
 */
$sections_json     = json_encode($sections, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$subsections_json  = json_encode($subsections, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$counts_json       = json_encode($counts);
?>

<!DOCTYPE html>
<html>
<head>
<title>Create Pond</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container mt-4">

<h4>Create Pond / Tank</h4>

<form method="POST" action="store.php" class="card p-3">

<!-- SECTION -->
<div class="mb-3">
    <label>Section</label>
    <select name="section_id" id="section" class="form-select" required>
        <option value="">Select Section</option>
        <?php foreach ($sections as $sec): ?>
            <option value="<?= (int)$sec['id'] ?>">
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

<!-- LIVE CODE PREVIEW -->
<div class="mb-3">
    <label>Generated Pond Code</label>
    <input type="text" id="pond_code_preview" class="form-control fw-bold" readonly>
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
document.addEventListener('DOMContentLoaded', function () {

    const subSections = <?= json_encode($subsections) ?>;

    const sectionSelect = document.getElementById('section');
    const subSelect     = document.getElementById('subsection');

    function loadSubSections(sectionId) {

        // RESET
        subSelect.innerHTML = '<option value="">Select Sub Section</option>';

        if (!sectionId) return;

        let matched = 0;

        subSections.forEach(sub => {

            // FORCE TYPE MATCH
            if (parseInt(sub.section_id) === parseInt(sectionId)) {

                matched++;

                const opt = document.createElement('option');
                opt.value = sub.id;
                opt.textContent = sub.name;

                subSelect.appendChild(opt);
            }
        });

        // DEBUG FEEDBACK
        if (matched === 0) {
            const opt = document.createElement('option');
            opt.textContent = 'No sub-sections available';
            opt.disabled = true;
            subSelect.appendChild(opt);
        }
    }

    // EVENT
    sectionSelect.addEventListener('change', function () {
        loadSubSections(this.value);
    });

});
</script>

</body>
</html>