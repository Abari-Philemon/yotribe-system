<?php
require '../../middleware/auth_guard.php';
require '../../middleware/farm_guard.php';
require '../../config/database.php';
require '../../helpers/growth_helper.php';

$farm_id = farm_id();
$message = '';

/**
 * LOAD ACTIVE STOCK
 */
$stmt = $pdo->prepare("
    SELECT ps.id, ps.pond_id, ps.batch_id,
           p.pond_code, fb.batch_code
    FROM pond_stocking ps
    JOIN ponds_tanks p ON p.id = ps.pond_id
    JOIN fish_batches fb ON fb.id = ps.batch_id
    WHERE ps.farm_id = ?
    AND ps.status = 'active'
");
$stmt->execute([$farm_id]);
$stocks = $stmt->fetchAll();

/**
 * HANDLE SUBMIT
 */
if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $stock_id = (int)$_POST['stock_id'];
    $sample = (int)$_POST['sample_count'];
    $total_weight = (float)$_POST['total_weight_g'];

    if ($sample <= 0 || $total_weight <= 0) {
        $message = "Invalid input";
    } else {

        $avg = $total_weight / $sample;

        $pdo->beginTransaction();

        try {

            $stmt = $pdo->prepare("
                SELECT pond_id, batch_id 
                FROM pond_stocking
                WHERE id = ? AND farm_id = ?
            ");
            $stmt->execute([$stock_id, $farm_id]);
            $stock = $stmt->fetch();

            if (!$stock) {
                throw new Exception("Invalid stock");
            }

            // Save growth log
            $stmt = $pdo->prepare("
                INSERT INTO growth_logs
                (farm_id, pond_id, batch_id, sample_count, total_weight_g, avg_weight_g, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $farm_id,
                $stock['pond_id'],
                $stock['batch_id'],
                $sample,
                $total_weight,
                $avg,
                $_SESSION['staff_id']
            ]);

            // Update weights
            updateBatchWeight($pdo, $farm_id, $stock['pond_id'], $stock['batch_id'], $avg);

            $pdo->commit();

            $message = "Growth recorded. Avg weight: " . round($avg,2) . "g";

        } catch(Exception $e) {
            $pdo->rollBack();
            $message = $e->getMessage();
        }
    }
}
?>

<form method="POST" class="card p-3">

<select name="stock_id" class="form-select mb-2">
<?php foreach($stocks as $s): ?>
<option value="<?= $s['id'] ?>">
<?= $s['pond_code'] ?> | <?= $s['batch_code'] ?>
</option>
<?php endforeach; ?>
</select>

<input type="number" name="sample_count" placeholder="Sample count" class="form-control mb-2">
<input type="number" name="total_weight_g" placeholder="Total weight (g)" class="form-control mb-2">

<button class="btn btn-primary">Record Growth</button>

</form>

<?= $message ?>