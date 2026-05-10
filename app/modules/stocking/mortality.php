<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';

$farm_id = farm_id();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD ACTIVE STOCKS
 */
$stmt = $pdo->prepare("
    SELECT ps.id, p.pond_code, ps.batch_id, ps.current_count
    FROM pond_stocking ps
    JOIN ponds_tanks p ON p.id = ps.pond_id
    WHERE ps.farm_id = ? AND ps.status='active'
");
$stmt->execute([$farm_id]);
$stocks = $stmt->fetchAll();

/**
 * HANDLE SUBMIT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF');
    }

    $stock_id = (int)$_POST['stock_id'];
    $dead     = (int)$_POST['quantity'];

    if ($dead <= 0) {
        die("Invalid quantity");
    }

    $pdo->beginTransaction();

    try {

        // LOCK row
        $stmt = $pdo->prepare("
            SELECT * FROM pond_stocking
            WHERE id = ? AND farm_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$stock_id, $farm_id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception("Stock not found");
        }

        if ($dead > $row['current_count']) {
            throw new Exception("Mortality exceeds available fish");
        }

        // UPDATE
        $pdo->prepare("
            UPDATE pond_stocking
            SET current_count = current_count - ?
            WHERE id = ?
        ")->execute([$dead, $stock_id]);

        // LOG
        $pdo->prepare("
            INSERT INTO stock_movements
            (farm_id, type, from_pond_id, batch_id, quantity, movement_date)
            VALUES (?, 'mortality', ?, ?, ?, CURDATE())
        ")->execute([
            $farm_id,
            $row['pond_id'],
            $row['batch_id'],
            $dead
        ]);

        $pdo->commit();

        header("Location: index.php?success=mortality");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>


<h4>Record Mortality</h4>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="post" class="card p-3">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
<label>Select Pond / Batch</label>
<select name="stock_id" class="form-select" required>
<?php foreach ($stocks as $s): ?>
<option value="<?= $s['id'] ?>">
<?= $s['pond_code'] ?> (Batch <?= $s['batch_id'] ?> | <?= $s['current_count'] ?> fish)
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Dead Count</label>
<input type="number" name="quantity" class="form-control" required>
</div>

<button class="btn btn-danger">Record Mortality</button>

</form>

</body>
</html>