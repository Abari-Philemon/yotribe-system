<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id = farm_id();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * LOAD SOURCE STOCK (BETTER THAN MANUAL BATCH INPUT)
 */
$stmt = $pdo->prepare("
    SELECT ps.id, ps.pond_id, ps.batch_id, ps.current_count,
           p.pond_code, fb.batch_code
    FROM pond_stocking ps
    JOIN ponds_tanks p ON p.id = ps.pond_id
    JOIN fish_batches fb ON fb.id = ps.batch_id
    WHERE ps.farm_id = ?
    AND ps.status = 'active'
    AND ps.current_count > 0
");
$stmt->execute([$farm_id]);
$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * LOAD DESTINATION PONDS
 */
$stmt = $pdo->prepare("
    SELECT id, pond_code 
    FROM ponds_tanks
    WHERE farm_id = ? AND status = 'active'
    ORDER BY pond_code
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);


/**
 * HANDLE FORM
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF');
    }

    $stock_id = (int)$_POST['stock_id'];
    $to_pond  = (int)$_POST['to_pond'];
    $qty      = (int)$_POST['quantity'];

    if ($qty <= 0) {
        die("Invalid quantity");
    }

    $pdo->beginTransaction();

    try {

        /**
         * LOCK SOURCE ROW (IMPORTANT)
         */
        $stmt = $pdo->prepare("
            SELECT * FROM pond_stocking
            WHERE id = ? AND farm_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$stock_id, $farm_id]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$source) {
            throw new Exception("Invalid source");
        }

        if ($qty > $source['current_count']) {
            throw new Exception("Not enough fish in source pond");
        }

        if ($source['pond_id'] == $to_pond) {
            throw new Exception("Cannot transfer to same pond");
        }

        /**
         * LOCK DESTINATION
         */
        $stmt = $pdo->prepare("
            SELECT id FROM ponds_tanks
            WHERE id = ? AND farm_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$to_pond, $farm_id]);

        if (!$stmt->fetch()) {
            throw new Exception("Invalid destination pond");
        }

        /**
         * VALIDATE DESTINATION CAPACITY (CRITICAL)
         */
        validateStocking($pdo, $to_pond, $farm_id, $qty);

        /**
         * REDUCE SOURCE
         */
        $pdo->prepare("
            UPDATE pond_stocking 
            SET current_count = current_count - ?
            WHERE id = ?
        ")->execute([$qty, $stock_id]);

        /**
         * CLOSE SOURCE IF EMPTY
         */
        $pdo->prepare("
            UPDATE pond_stocking
            SET status = 'moved'
            WHERE id = ?
            AND current_count <= 0
        ")->execute([$stock_id]);

        /**
         * CHECK DESTINATION EXISTING BATCH
         */
        $stmt = $pdo->prepare("
            SELECT id FROM pond_stocking
            WHERE pond_id = ? AND batch_id = ? AND status='active'
        ");
        $stmt->execute([$to_pond, $source['batch_id']]);
        $dest = $stmt->fetch();

        if ($dest) {
            /**
             * UPDATE EXISTING
             */
            $pdo->prepare("
                UPDATE pond_stocking
                SET current_count = current_count + ?
                WHERE id = ?
            ")->execute([$qty, $dest['id']]);

        } else {
            /**
             * INSERT NEW
             */
            $pdo->prepare("
                INSERT INTO pond_stocking
                (farm_id, pond_id, batch_id, stocked_count, current_count, avg_weight_g, stocking_date, status)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'active')
            ")->execute([
                $farm_id,
                $to_pond,
                $source['batch_id'],
                $qty,
                $qty,
                $source['avg_weight_g']
            ]);
        }

        /**
         * LOG MOVEMENT
         */
        $pdo->prepare("
            INSERT INTO stock_movements
            (farm_id, type, from_pond_id, to_pond_id, batch_id, quantity, movement_date)
            VALUES (?, 'transfer', ?, ?, ?, ?, CURDATE())
        ")->execute([
            $farm_id,
            $source['pond_id'],
            $to_pond,
            $source['batch_id'],
            $qty
        ]);

        $pdo->commit();

        header("Location: index.php?success=transfer");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Transfer Fish</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">

<h4>Transfer Fish</h4>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" class="card p-3">
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

<div class="mb-3">
<label>Source (Pond + Batch)</label>
<select name="stock_id" class="form-select" required>
<option value="">Select Source</option>
<?php foreach ($stocks as $s): ?>
<option value="<?= $s['id'] ?>">
<?= $s['pond_code'] ?> | <?= $s['batch_code'] ?> (<?= $s['current_count'] ?>)
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Destination Pond</label>
<select name="to_pond" class="form-select" required>
<option value="">Select Pond</option>
<?php foreach ($ponds as $p): ?>
<option value="<?= $p['id'] ?>"><?= $p['pond_code'] ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Quantity</label>
<input type="number" name="quantity" class="form-control" required>
</div>

<button class="btn btn-primary">Transfer</button>

</form>

</body>
</html>