<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';


authorize('feeding');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();

    $pond_id = $_POST['pond_id'];
    $feed_id = $_POST['feed_id'];
    $qty = (float)$_POST['quantity'];

    // Lock feed row
    $stmt = $pdo->prepare("SELECT quantity_kg FROM feed_store WHERE id=? FOR UPDATE");
    $stmt->execute([$feed_id]);
    $stock = $stmt->fetchColumn();

    if ($stock < $qty) {
        die("Insufficient feed stock");
    }

    // Deduct feed stock
    $pdo->prepare("UPDATE feed_store SET quantity_kg = quantity_kg - ? WHERE id=?")
        ->execute([$qty, $feed_id]);

    // Feeding log
    $pdo->prepare("
        INSERT INTO feeding_logs
        (date, farm_id, pond_id, feed_type, quantity_kg, fed_by, time)
        SELECT CURDATE(), p.farm_id, ?, f.feed_type, ?, ?, CURTIME()
        FROM ponds_tanks p
        JOIN feed_store f ON f.id = ?
        WHERE p.id = ?
    ")->execute([
        $pond_id,
        $qty,
        $_SESSION['staff_id'],
        $feed_id,
        $pond_id
    ]);

    $pdo->commit();
    header("Location: logs.php");
    exit;
}

$ponds = $pdo->query("SELECT id, pond_code FROM ponds_tanks")->fetchAll();
$feeds = $pdo->query("SELECT id, feed_type, quantity_kg FROM feed_store")->fetchAll();
?>


<h2>Record Feeding</h2>
<table class="table table-striped" id="feedTable">
  <thead>
    <tr>
      <th>Feed Type</th>
      <th>Batch No</th>
      <th>Quantity (kg)</th>
      <th>Cost/kg ($)</th>
      <th>Updated</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($feeds as $feed): ?>
    <tr>
      <td><?= $feed['feed_type'] ?></td>
      <td><?= $feed['batch_no'] ?></td>
      <td><?= $feed['quantity_kg'] ?></td>
      <td><?= number_format($feed['cost_per_kg'],2) ?></td>
      <td><?= $feed['updated_at'] ?></td>
      <td>
        <button class="btn btn-sm btn-primary editFeed" data-id="<?= $feed['id'] ?>">Edit</button>
        <button class="btn btn-sm btn-danger deleteFeed" data-id="<?= $feed['id'] ?>">Delete</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function(){
    $('#feedTable').DataTable();
});
</script>

<form method="post">
    Pond:
    <select name="pond_id">
        <?php foreach ($ponds as $p): ?>
            <option value="<?= $p['id'] ?>"><?= $p['pond_code'] ?></option>
        <?php endforeach; ?>
    </select><br>

    Feed:
    <select name="feed_id">
        <?php foreach ($feeds as $f): ?>
            <option value="<?= $f['id'] ?>">
                <?= $f['feed_type'] ?> (<?= $f['quantity_kg'] ?>kg)
            </option>
        <?php endforeach; ?>
    </select><br>

    Quantity (kg): <input type="number" step="0.01" name="quantity"><br>
    <button>Save Feeding</button>
</form>
