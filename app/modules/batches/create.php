<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id   = farm_id();
$farm_name = farm_name();

/**
 * GENERATE NEXT BATCH CODE (SAFE)
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM fish_batches 
    WHERE farm_id = ?
");
$stmt->execute([$farm_id]);

$count = (int)$stmt->fetchColumn() + 1;
$batch_code = 'BATCH-' . str_pad($count, 3, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html>
<head>
<title>Create Fish Batch</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

<h4>Create Fish Batch</h4>
<small class="text-muted">Farm: <?= htmlspecialchars($farm_name) ?></small>

<form method="POST" action="store.php" class="card p-4 mt-3">

<!-- Batch Code -->
<div class="mb-3">
    <label class="form-label">Batch Code</label>
    <input type="text" name="batch_code" class="form-control"
           value="<?= $batch_code ?>" readonly>
</div>

<!-- Source -->
<div class="mb-3">
    <label class="form-label">Source</label>
    <select name="source" class="form-select">
        <option value="hatchery">Hatchery</option>
        <option value="purchase" selected>Purchase</option>
    </select>
</div>

<!-- Species -->
<div class="mb-3">
    <label class="form-label">Species</label>
    <input type="text" name="species" class="form-control"
           value="catfish">
</div>

<!-- Initial Count -->
<div class="mb-3">
    <label class="form-label">Initial Fish Count</label>
    <input type="number" name="initial_count" class="form-control" required>
</div>

<!-- Avg Weight -->
<div class="mb-3">
    <label class="form-label">Average Weight (g)</label>
    <input type="number" step="0.01" name="avg_weight_g" class="form-control" value="0">
</div>

<!-- Date -->
<div class="mb-3">
    <label class="form-label">Stocking Date</label>
    <input type="date" name="stocking_date" class="form-control"
           value="<?= date('Y-m-d') ?>" required>
</div>

<button class="btn btn-primary w-100">Create Batch</button>

</form>

</div>
</body>
</html>