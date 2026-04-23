<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

/**
 * LOAD PONDS
 */
$stmt = $pdo->prepare("
    SELECT id, pond_code 
    FROM ponds_tanks 
    WHERE farm_id = ?
    ORDER BY pond_code
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Growth Tracking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card { max-width: 700px; margin:auto; }
.info-box { font-size: 14px; }
</style>
</head>

<body class="container mt-4">

<h3 class="mb-3">Growth Tracking</h3>

<!-- ALERTS -->
<?php if (!empty($_GET['success'])): ?>
<div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="card p-4 shadow-sm">

<form method="POST" action="store.php" id="growthForm">

<div class="mb-3">
<label>Pond</label>
<select name="pond_id" id="pond" class="form-select" required>
<option value="">Select Pond</option>
<?php foreach($ponds as $p): ?>
<option value="<?= $p['id'] ?>"><?= $p['pond_code'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Batch</label>
<select name="batch_id" id="batch" class="form-select" required>
<option value="">Select Batch</option>
</select>
</div>

<div class="row">
<div class="col-md-6 mb-3">
<label>Sample Count</label>
<input type="number" name="sample_count" id="sample_count" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label>Average Weight (g)</label>
<input type="number" step="0.01" name="avg_weight_g" id="avg_weight" class="form-control" required>
</div>
</div>

<div class="mb-3">
<label>Total Fish Count</label>
<input type="number" name="total_count" id="total_count" class="form-control" readonly>
</div>

<div class="mb-3">
<label>Date</label>
<input type="date" name="recorded_at" class="form-control" value="<?= date('Y-m-d') ?>">
</div>

<!-- LIVE INFO PANEL -->
<div class="alert alert-info info-box d-none" id="infoBox">
<strong>Batch Info:</strong><br>
Current Weight: <span id="current_weight">-</span> g<br>
Predicted (7 days): <span id="predicted_weight">-</span> g<br>
Growth Status: <span id="growth_status">-</span>
</div>

<button class="btn btn-success w-100">Save Growth</button>

</form>
</div>

<script>
const pond = document.getElementById('pond');
const batch = document.getElementById('batch');
const totalCount = document.getElementById('total_count');

const infoBox = document.getElementById('infoBox');
const currentWeight = document.getElementById('current_weight');
const predictedWeight = document.getElementById('predicted_weight');
const growthStatus = document.getElementById('growth_status');

/**
 * LOAD BATCHES
 */
pond.addEventListener('change', async function(){

    batch.innerHTML = '<option>Loading...</option>';
    infoBox.classList.add('d-none');

    const res = await fetch('get_batches.php?pond_id=' + this.value);
    const data = await res.json();

    batch.innerHTML = '<option value="">Select Batch</option>';

    data.forEach(b => {
        let opt = document.createElement('option');
        opt.value = b.batch_id;
        opt.textContent = b.batch_code + ' (' + b.current_count + ')';
        opt.dataset.count = b.current_count;
        batch.appendChild(opt);
    });
});

/**
 * LOAD BATCH DETAILS (WEIGHT + PREDICTION)
 */
batch.addEventListener('change', async function(){

    const selected = this.selectedOptions[0];
    if (!selected) return;

    totalCount.value = selected.dataset.count || '';

    const res = await fetch(
        'get_growth_info.php?pond_id=' + pond.value + '&batch_id=' + this.value
    );

    const data = await res.json();

    if (!data) return;

    currentWeight.textContent = data.current_weight ?? '-';
    predictedWeight.textContent = data.predicted_weight ?? '-';

    if (data.alert) {
        growthStatus.innerHTML = '<span class="text-danger">' + data.alert + '</span>';
    } else {
        growthStatus.innerHTML = '<span class="text-success">Normal</span>';
    }

    infoBox.classList.remove('d-none');
});

/**
 * CLIENT VALIDATION
 */
document.getElementById('growthForm').addEventListener('submit', function(e){

    const sample = parseInt(document.getElementById('sample_count').value);
    const total  = parseInt(totalCount.value);

    if (sample > total) {
        e.preventDefault();
        alert("Sample count cannot exceed total fish count");
    }
});
</script>

</body>
</html>