<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/pond_helper.php';

$farm_id = farm_id();

/**
 * LOAD PONDS WITH CURRENT STOCK
 */
$stmt = $pdo->prepare("
    SELECT p.id, p.pond_code, p.capacity,
        COALESCE(SUM(s.current_count),0) AS current_stock
    FROM ponds_tanks p
    LEFT JOIN pond_stocking s 
        ON s.pond_id = p.id AND s.status='active'
    WHERE p.farm_id = ?
    GROUP BY p.id
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<form method="POST" action="store.php" class="card p-3">

<!-- Pond -->
<select name="pond_id" id="pond_id" class="form-select mb-3" required>
    <option value="">Select Pond</option>
    <?php foreach ($ponds as $p): ?>
        <option value="<?= $p['id'] ?>">
            <?= $p['pond_code'] ?> (<?= $p['current_stock'] ?>/<?= $p['capacity'] ?>)
        </option>
    <?php endforeach; ?>
</select>

<!-- Quantity -->
<input type="number" name="quantity" id="qty" class="form-control mb-3" placeholder="Quantity" required>

<!-- Warning -->
<div id="capacity_warning" class="alert d-none"></div>

<button class="btn btn-primary">Stock Fish</button>

</form>

<script>
const ponds = <?= json_encode($ponds) ?>;

const pondSelect = document.getElementById('pond_id');
const qtyInput   = document.getElementById('qty');
const warningBox = document.getElementById('capacity_warning');

function checkCapacity() {

    const pondId = parseInt(pondSelect.value);
    const qty    = parseInt(qtyInput.value) || 0;

    if (!pondId) return;

    const pond = ponds.find(p => p.id == pondId);
    if (!pond) return;

    const total = pond.current_stock + qty;
    const pct = (total / pond.capacity) * 100;

    warningBox.classList.remove('d-none');

    if (pct >= 100) {
        warningBox.className = 'alert alert-danger';
        warningBox.innerText = `Over capacity (${total}/${pond.capacity})`;
    } else if (pct >= 90) {
        warningBox.className = 'alert alert-warning';
        warningBox.innerText = `Almost full (${total}/${pond.capacity})`;
    } else {
        warningBox.className = 'alert alert-success';
        warningBox.innerText = `Safe (${total}/${pond.capacity})`;
    }
}

pondSelect.addEventListener('change', checkCapacity);
qtyInput.addEventListener('input', checkCapacity);
</script>