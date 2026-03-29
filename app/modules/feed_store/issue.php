<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';

authorize('feed_store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feed_id = (int)$_POST['feed_id'];
    $issued = (float)$_POST['issued'];
    $issued_to = $_POST['issued_to'];

    $pdo->beginTransaction();

    // Get current stock
    $stmt = $pdo->prepare("SELECT quantity_kg FROM feed_store WHERE id=? FOR UPDATE");
    $stmt->execute([$feed_id]);
    $stock = $stmt->fetchColumn();

    if ($stock < $issued) {
        die("Insufficient stock");
    }

    // Update stock
    $pdo->prepare("UPDATE feed_store SET quantity_kg = quantity_kg - ? WHERE id=?")
        ->execute([$issued, $feed_id]);

    // Log
    $pdo->prepare("
        INSERT INTO feed_store_logs
        (date, feed_type, batch_no, issued, issued_to, storekeeper)
        SELECT CURDATE(), feed_type, batch_no, ?, ?, ?
        FROM feed_store WHERE id=?
    ")->execute([
        $issued,
        $issued_to,
        $_SESSION['staff_id'],
        $feed_id
    ]);

    $pdo->commit();
    header("Location: index.php");
    exit;
}

$feeds = $pdo->query("SELECT id, feed_type, batch_no, quantity_kg FROM feed_store")->fetchAll();
?>

<h2>Issue Feed</h2>
<form method="post">
    Feed:
    <select name="feed_id">
        <?php foreach ($feeds as $f): ?>
            <option value="<?= $f['id'] ?>">
                <?= $f['feed_type'] ?> (<?= $f['quantity_kg'] ?>kg)
            </option>
        <?php endforeach; ?>
    </select><br>
    Quantity Issued (kg): <input type="number" step="0.01" name="issued"><br>
    Issued To (pond / unit): <input name="issued_to"><br>
    <button type="submit">Issue</button>
</form>
