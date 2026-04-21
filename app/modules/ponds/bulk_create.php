<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

/**
 * LOAD DATA
 */
$sections = $pdo->prepare("SELECT id, name FROM sections WHERE farm_id=?");
$sections->execute([$farm_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);

$subsections = $pdo->prepare("SELECT id, section_id, name FROM sub_sections WHERE farm_id=?");
$subsections->execute([$farm_id]);
$subsections = $subsections->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
<h4>Bulk Pond Generator</h4>

<form method="POST" action="bulk_store.php" class="card p-3">

<select name="section_id" id="section" class="form-select mb-2" required>
<option value="">Select Section</option>
<?php foreach ($sections as $s): ?>
<option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
<?php endforeach; ?>
</select>

<select name="sub_section_id" id="subsection" class="form-select mb-2" required>
<option value="">Select Sub Section</option>
</select>

<input type="number" name="quantity" class="form-control mb-2" placeholder="Number of ponds" required>

<input type="number" name="capacity" class="form-control mb-2" placeholder="Capacity per pond" required>

<select name="pond_type" class="form-select mb-2">
<option value="tank">Tank</option>
<option value="tarpaulin">Tarpaulin</option>
</select>

<button class="btn btn-primary">Generate Ponds</button>

</form>
</div>

<script>
const subs = <?= json_encode($subsections) ?>;
const section = document.getElementById('section');
const sub = document.getElementById('subsection');

section.addEventListener('change', function () {

    sub.innerHTML = '<option value="">Select Sub Section</option>';

    subs.forEach(s => {
        if (parseInt(s.section_id) === parseInt(this.value)) {
            let opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            sub.appendChild(opt);
        }
    });

});
</script>