<?php
require '../../middleware/auth_guard.php';
require '../../middleware/farm_guard.php';
require '../../config/database.php';
require_once __DIR__ . '/../../helpers/growth_helper.php';

require_role(['manager','owner','staff','storekeeper']);

$farm_id = farm_id();
$staff_id = $_SESSION['staff_id'];

$message = '';
$alert = 'success';

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD ACTIVE STOCK
 */
$stmt = $pdo->prepare("
    SELECT 
        ps.id,
        ps.pond_id,
        ps.batch_id,
        ps.current_count,
        ps.avg_weight_g,
        p.pond_code,
        fb.batch_code
    FROM pond_stocking ps
    JOIN ponds_tanks p 
        ON p.id = ps.pond_id
    JOIN fish_batches fb 
        ON fb.id = ps.batch_id
    WHERE ps.farm_id = ?
    AND ps.status = 'active'
    ORDER BY p.pond_code ASC
");

$stmt->execute([$farm_id]);

$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * HANDLE SUBMIT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('CSRF validation failed');
    }

    $stock_id      = (int) ($_POST['stock_id'] ?? 0);
    $sample_count  = (int) ($_POST['sample_count'] ?? 0);
    $total_weight  = (float) ($_POST['total_weight_g'] ?? 0);
    $remarks       = trim($_POST['remarks'] ?? '');

    /**
     * VALIDATION
     */
    if ($stock_id <= 0) {

        $message = "Invalid stock selected";
        $alert = 'danger';

    } elseif ($sample_count <= 0) {

        $message = "Sample count must be greater than zero";
        $alert = 'danger';

    } elseif ($total_weight <= 0) {

        $message = "Total weight must be greater than zero";
        $alert = 'danger';

    } else {

        $avg_weight = $total_weight / $sample_count;

        try {

            $pdo->beginTransaction();

            /**
             * LOCK STOCK
             */
            $stmt = $pdo->prepare("
                SELECT *
                FROM pond_stocking
                WHERE id = ?
                AND farm_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$stock_id, $farm_id]);

            $stock = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stock) {
                throw new Exception("Invalid stock selected");
            }

            /**
             * INSERT GROWTH LOG
             * Uses recorded_at auto timestamp from DB
             */
            $stmt = $pdo->prepare("
                INSERT INTO growth_logs (
                    farm_id,
                    pond_id,
                    batch_id,
                    sample_count,
                    total_weight_g,
                    avg_weight_g,
                    recorded_by,
                    remarks,
                    recorded_at
                )
                VALUES (?,?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $farm_id,
                $stock['pond_id'],
                $stock['batch_id'],
                $sample_count,
                $total_weight,
                $avg_weight,
                $staff_id,
                $remarks
            ]);

            /**
             * UPDATE STOCK AVG WEIGHT
             */
            updateBatchWeight(
                $pdo,
                $farm_id,
                $stock['pond_id'],
                $stock['batch_id'],
                $avg_weight
            );

            $pdo->commit();

            $message = "Growth recorded successfully. Average weight: "
                     . number_format($avg_weight, 2) . " g";

            $alert = 'success';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $alert = 'danger';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
.cardx{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 30px rgba(0,0,0,.05);
}

.metric{
    font-size:30px;
    font-weight:700;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h3>📈 Growth Sampling</h3>

    <a href="index.php" class="btn btn-light">
        ← Back
    </a>

</div>

<?php if($message): ?>

<div class="alert alert-<?= $alert ?>">
    <?= htmlspecialchars($message) ?>
</div>

<?php endif; ?>

<div class="row g-4">

    <!-- FORM -->
    <div class="col-lg-8">

        <div class="cardx bg-white p-4">

            <form method="POST" id="growthForm">

                <input 
                    type="hidden"
                    name="csrf_token"
                    value="<?= $_SESSION['csrf_token'] ?>"
                >

                <!-- STOCK -->
                <div class="mb-3">

                    <label class="fw-semibold mb-2">
                        Pond + Batch
                    </label>

                    <select 
                        name="stock_id"
                        id="stock_id"
                        class="form-select"
                        required
                    >

                        <?php foreach($stocks as $s): ?>

                        <option
                            value="<?= $s['id'] ?>"
                            data-weight="<?= $s['avg_weight_g'] ?>"
                            data-count="<?= $s['current_count'] ?>"
                        >
                            <?= htmlspecialchars($s['pond_code']) ?>
                            |
                            <?= htmlspecialchars($s['batch_code']) ?>
                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="row">

                    <!-- SAMPLE -->
                    <div class="col-md-6 mb-3">

                        <label class="fw-semibold mb-2">
                            Sample Count
                        </label>

                        <input
                            type="number"
                            name="sample_count"
                            id="sample_count"
                            class="form-control"
                            min="1"
                            required
                        >

                    </div>

                    <!-- TOTAL WEIGHT -->
                    <div class="col-md-6 mb-3">

                        <label class="fw-semibold mb-2">
                            Total Weight (g)
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="total_weight_g"
                            id="total_weight_g"
                            class="form-control"
                            required
                        >

                    </div>

                </div>

                <!-- REMARKS -->
                <div class="mb-3">

                    <label class="fw-semibold mb-2">
                        Remarks
                    </label>

                    <textarea
                        name="remarks"
                        class="form-control"
                        rows="3"
                    ></textarea>

                </div>

                <button class="btn btn-primary w-100">
                    Save Growth Record
                </button>

            </form>

        </div>

    </div>

    <!-- KPI -->
    <div class="col-lg-4">

        <div class="cardx bg-white p-4 mb-3">

            <small class="text-muted">
                Current Avg Weight
            </small>

            <div class="metric" id="currentWeight">
                -- g
            </div>

        </div>

        <div class="cardx bg-white p-4 mb-3">

            <small class="text-muted">
                Estimated New Avg
            </small>

            <div class="metric text-primary" id="newAvg">
                -- g
            </div>

        </div>

        <div class="cardx bg-white p-4">

            <small class="text-muted">
                Estimated Biomass
            </small>

            <div class="metric text-success" id="biomass">
                -- kg
            </div>

        </div>

    </div>

</div>

<script>

function calculateGrowth(){

    let stock = document.getElementById('stock_id')
        .selectedOptions[0];

    let currentWeight = parseFloat(
        stock.dataset.weight || 0
    );

    let fishCount = parseFloat(
        stock.dataset.count || 0
    );

    let sample = parseFloat(
        document.getElementById('sample_count').value || 0
    );

    let total = parseFloat(
        document.getElementById('total_weight_g').value || 0
    );

    let avg = 0;

    if(sample > 0 && total > 0){
        avg = total / sample;
    }

    let biomass = (fishCount * avg) / 1000;

    document.getElementById('currentWeight').innerText =
        currentWeight.toFixed(2) + ' g';

    document.getElementById('newAvg').innerText =
        avg.toFixed(2) + ' g';

    document.getElementById('biomass').innerText =
        biomass.toFixed(2) + ' kg';
}

document.getElementById('stock_id')
    .addEventListener('change', calculateGrowth);

document.getElementById('sample_count')
    .addEventListener('input', calculateGrowth);

document.getElementById('total_weight_g')
    .addEventListener('input', calculateGrowth);

calculateGrowth();

</script>

</body>
</html>