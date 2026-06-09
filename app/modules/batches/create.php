<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id   = farm_id();
$farm_name = farm_name();

/**
 * SAFE BATCH CODE GENERATION (RACE-SAFE)
 */
try {
    $pdo->beginTransaction();

    // Lock table rows for this farm to avoid duplicates under concurrency
    $stmt = $pdo->prepare("
        SELECT MAX(id) AS last_id
        FROM fish_batches
        WHERE farm_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$farm_id]);

    $lastId = (int) $stmt->fetchColumn();
    $nextId = $lastId + 1;

    $batch_code = 'BATCH-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Batch code generation failed: " . $e->getMessage());
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container mt-4">

    <h4>Create Fish Batch</h4>
    <small class="text-muted">Farm: <?= htmlspecialchars($farm_name) ?></small>

    <!-- AJAX FORM -->
    <form id="batchForm" class="card p-4 mt-3">

        <!-- Batch Code -->
        <div class="mb-3">
            <label class="form-label">Batch Code</label>
            <input type="text" name="batch_code" class="form-control"
                   value="<?= htmlspecialchars($batch_code) ?>" readonly>
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
                   value="catfish" required>
        </div>

        <!-- Initial Count -->
        <div class="mb-3">
            <label class="form-label">Initial Fish Count</label>
            <input type="number" name="initial_count" class="form-control"
                   min="1" required>
        </div>

        <!-- Avg Weight -->
        <div class="mb-3">
            <label class="form-label">Average Weight (g)</label>
            <input type="number" step="0.01" name="avg_weight_g"
                   class="form-control" value="0">
        </div>

        <!-- Stocking Date -->
        <div class="mb-3">
            <label class="form-label">Stocking Date</label>
            <input type="date" name="stocking_date" class="form-control"
                   value="<?= date('Y-m-d') ?>" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Create Batch
        </button>

    </form>

</div>

<!-- UX: AJAX HANDLER -->
<script>
document.getElementById("batchForm").addEventListener("submit", async function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    try {
        const res = await fetch("store.php", {
            method: "POST",
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            alert(data.message || "Batch created successfully");
            window.location.href = "index.php";
        } else {
            alert(data.message || "Error occurred");
        }

    } catch (err) {
        alert("Network error. Please try again.");
    }
});
</script>

</body>
</html>