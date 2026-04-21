<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id   = farm_id();
$farm_name = farm_name();

/**
 * LOAD DATA
 */
$sections = $pdo->prepare("
    SELECT id, name 
    FROM sections 
    WHERE farm_id = ?
    ORDER BY name
");
$sections->execute([$farm_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);

$subsections = $pdo->prepare("
    SELECT id, section_id, name, code 
    FROM sub_sections 
    WHERE farm_id = ?
    ORDER BY name
");
$subsections->execute([$farm_id]);
$subsections = $subsections->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Bulk Pond Generator</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.preview-box {
    background: #f8f9fa;
    border: 1px dashed #ccc;
    padding: 10px;
    height: 140px;
    overflow-y: auto;
    font-size: 14px;
}
</style>
</head>

<body class="bg-light">

<div class="container mt-4">

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4>Bulk Pond Generator</h4>
        <small class="text-muted">Farm: <?= htmlspecialchars($farm_name) ?></small>
    </div>

    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        ← Back to Ponds
    </a>
</div>

<form method="POST" action="bulk_store.php">

<div class="row g-3">

<!-- LEFT PANEL -->
<div class="col-md-7">
<div class="card shadow-sm">
<div class="card-body">

<h6 class="mb-3">Configuration</h6>

<!-- SECTION -->
<div class="mb-3">
    <label class="form-label">Section</label>
    <select name="section_id" id="section" class="form-select" required>
        <option value="">Select Section</option>
        <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>">
                <?= htmlspecialchars($s['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- SUBSECTION -->
<div class="mb-3">
    <label class="form-label">Sub Section</label>
    <select name="sub_section_id" id="subsection" class="form-select" required disabled>
        <option value="">Select Sub Section</option>
    </select>
</div>

<div class="row">

<!-- QUANTITY -->
<div class="col-md-6 mb-3">
    <label class="form-label">Number of Ponds</label>
    <input type="number" name="quantity" id="quantity" class="form-control" min="1" max="500" required>
</div>

<!-- CAPACITY -->
<div class="col-md-6 mb-3">
    <label class="form-label">Capacity (Fish)</label>
    <input type="number" name="capacity" id="capacity" class="form-control" required>
</div>

</div>

<!-- TYPE -->
<div class="mb-3">
    <label class="form-label">Pond Type</label>
    <select name="pond_type" class="form-select">
        <option value="tank">Tank</option>
        <option value="tarpaulin">Tarpaulin</option>
    </select>
</div>

<hr>

<h6 class="mb-3">Physical Properties</h6>

<div class="row">

<div class="col-md-6 mb-3">
    <label class="form-label">Size Label</label>
    <input type="text" name="size_label" class="form-control" placeholder="e.g 20x20 or 1000L">
</div>

<div class="col-md-3 mb-3">
    <label class="form-label">Length (ft)</label>
    <input type="number" step="0.01" name="length_ft" id="length" class="form-control">
</div>

<div class="col-md-3 mb-3">
    <label class="form-label">Width (ft)</label>
    <input type="number" step="0.01" name="width_ft" id="width" class="form-control">
</div>

</div>

<div class="mb-3">
    <label class="form-label">Volume (Liters)</label>
    <input type="number" step="0.01" name="volume_liters" id="volume" class="form-control">
</div>

<button class="btn btn-primary w-100">
    Generate Ponds
</button>

</div>
</div>
</div>

<!-- RIGHT PANEL -->
<div class="col-md-5">

<div class="card shadow-sm">
<div class="card-body">

<h6>Preview</h6>

<div id="preview" class="preview-box text-muted">
    Select sub-section and quantity
</div>

<hr>

<div id="summary" class="small text-muted">
    No configuration yet
</div>

</div>
</div>

</div>

</div>

</form>

</div>

<script>
const subs = <?= json_encode($subsections) ?>;

const sectionEl = document.getElementById('section');
const subEl     = document.getElementById('subsection');
const qtyEl     = document.getElementById('quantity');
const lengthEl  = document.getElementById('length');
const widthEl   = document.getElementById('width');
const volumeEl  = document.getElementById('volume');

const previewBox = document.getElementById('preview');
const summaryBox = document.getElementById('summary');

/**
 * LOAD SUBSECTIONS
 */
sectionEl.addEventListener('change', function () {

    const sectionId = parseInt(this.value);

    subEl.innerHTML = '<option value="">Select Sub Section</option>';
    subEl.disabled = true;

    if (!sectionId) return;

    let found = false;

    subs.forEach(s => {
        if (parseInt(s.section_id) === sectionId) {

            found = true;

            let opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;

            subEl.appendChild(opt);
        }
    });

    subEl.disabled = !found;
});

/**
 * AUTO VOLUME CALC
 */
function autoVolume() {
    const l = parseFloat(lengthEl.value);
    const w = parseFloat(widthEl.value);

    if (l && w) {
        volumeEl.value = Math.round(l * w * 28.3);
    }
}

lengthEl.addEventListener('input', autoVolume);
widthEl.addEventListener('input', autoVolume);

/**
 * PREVIEW
 */
function updatePreview() {

    const subId = parseInt(subEl.value);
    const qty   = parseInt(qtyEl.value) || 0;

    if (!subId || qty <= 0) {
        previewBox.innerHTML = "Select valid inputs";
        summaryBox.innerHTML = "No configuration yet";
        return;
    }

    const sub = subs.find(s => s.id == subId);
    if (!sub) return;

    let html = '';

    for (let i = 1; i <= Math.min(qty, 20); i++) {
        let seq = String(i).padStart(2, '0');
        html += sub.code + '-' + seq + '<br>';
    }

    if (qty > 20) html += '...';

    previewBox.innerHTML = html;

    summaryBox.innerHTML = `
        <strong>${qty}</strong> ponds will be created under 
        <strong>${sub.name}</strong>
    `;
}

subEl.addEventListener('change', updatePreview);
qtyEl.addEventListener('input', updatePreview);

</script>

</body>
</html>