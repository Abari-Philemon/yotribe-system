<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id   = farm_id();
$farm_name = farm_name();

/**
 * LOAD PONDS (ACTIVE ONLY)
 */
$stmt = $pdo->prepare("
    SELECT id, pond_code, volume_liters, capacity
    FROM ponds_tanks
    WHERE farm_id = ?
    AND status = 'active'
    ORDER BY pond_code
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * LOAD BATCHES
 */
$stmt = $pdo->prepare("
    SELECT id, batch_code, current_count
    FROM fish_batches
    WHERE farm_id = ?
    AND status = 'active'
    AND current_count > 0
    ORDER BY id DESC
");
$stmt->execute([$farm_id]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * PRELOAD CURRENT STOCK PER POND
 */
$stmt = $pdo->prepare("
    SELECT pond_id, SUM(current_count) as total
    FROM pond_stocking
    WHERE farm_id = ?
    AND status = 'active'
    GROUP BY pond_id
");
$stmt->execute([$farm_id]);
$pondStock = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/**
 * STOCKING RATIO
 */
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>



<div class="container mt-4">

<h4>Stock Fish into Pond</h4>
<small class="text-muted">Farm: <?= htmlspecialchars($farm_name) ?></small>

<form method="POST" action="store.php" class="card p-4 mt-3">

<!-- POND -->
<div class="mb-3">
    <label class="form-label">Select Pond</label>
    <select name="pond_id" id="pond" class="form-select" required>
        <option value="">Select Pond</option>
        <?php foreach ($ponds as $p): ?>
            <option 
                value="<?= $p['id'] ?>"
                data-volume="<?= $p['volume_liters'] ?>"
                data-capacity="<?= $p['capacity'] ?>"
                data-current="<?= $pondStock[$p['id']] ?? 0 ?>"
            >
                <?= htmlspecialchars($p['pond_code']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- BATCH -->
<div class="mb-3">
    <label class="form-label">Select Batch</label>
    <select name="batch_id" id="batch" class="form-select" required>
        <option value="">Select Batch</option>
        <?php foreach ($batches as $b): ?>
            <option 
                value="<?= $b['id'] ?>"
                data-available="<?= $b['current_count'] ?>"
            >
                <?= htmlspecialchars($b['batch_code']) ?> 
                (<?= number_format($b['current_count']) ?> available)
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- QUANTITY -->
<div class="mb-3">
    <label class="form-label">Quantity to Stock</label>
    <input type="number" name="quantity" id="qty" class="form-control" required>
</div>

<!-- INFO PANEL -->
<div class="alert alert-info small" id="infoBox">
    Select pond and batch to see limits
</div>

<button class="btn btn-primary w-100">Stock Fish</button>

</form>

</div>

<script>
const ratio = <?= $ratio ?>;

const pondEl  = document.getElementById('pond');
const batchEl = document.getElementById('batch');
const qtyEl   = document.getElementById('qty');
const infoBox = document.getElementById('infoBox');

function updateInfo() {

    const pond = pondEl.selectedOptions[0];
    const batch = batchEl.selectedOptions[0];

    if (!pond || !batch || !pond.value || !batch.value) {
        infoBox.innerHTML = "Select pond and batch to see limits";
        return;
    }

    const volume  = parseFloat(pond.dataset.volume);
    const capacity = parseInt(pond.dataset.capacity);
    const current = parseInt(pond.dataset.current);

    const availableBatch = parseInt(batch.dataset.available);

    const maxByVolume = Math.floor(volume / ratio);
    const maxAllowed  = Math.min(maxByVolume, capacity);

    const remainingSpace = maxAllowed - current;

    infoBox.innerHTML = `
        <strong>Pond Limit:</strong> ${maxAllowed} fish<br>
        <strong>Currently in Pond:</strong> ${current}<br>
        <strong>Remaining Space:</strong> ${remainingSpace}<br>
        <hr>
        <strong>Batch Available:</strong> ${availableBatch}
    `;
}

/**
 * LIVE VALIDATION
 */
qtyEl.addEventListener('input', function () {

    const pond = pondEl.selectedOptions[0];
    const batch = batchEl.selectedOptions[0];

    if (!pond || !batch || !pond.value || !batch.value) return;

    const volume  = parseFloat(pond.dataset.volume);
    const capacity = parseInt(pond.dataset.capacity);
    const current = parseInt(pond.dataset.current);
    const batchAvailable = parseInt(batch.dataset.available);

    const maxByVolume = Math.floor(volume / ratio);
    const maxAllowed  = Math.min(maxByVolume, capacity);

    const remaining = maxAllowed - current;

    let val = parseInt(this.value) || 0;

    if (val > remaining) {
        this.value = remaining;
    }

    if (val > batchAvailable) {
        this.value = batchAvailable;
    }
});

pondEl.addEventListener('change', updateInfo);
batchEl.addEventListener('change', updateInfo);
</script>

</body>
</html>