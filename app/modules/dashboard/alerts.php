<?php
$low_feed = $pdo->query("SELECT feed_type, quantity_kg FROM feed_store WHERE quantity_kg < 100")->fetchAll();
foreach ($low_feed as $f) {
    echo "<div style='color:red;'>Low feed alert: {$f['feed_type']} ({$f['quantity_kg']}kg)</div>";
}
?>
