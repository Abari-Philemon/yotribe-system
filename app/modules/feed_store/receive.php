<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
 

$farm_id      = farm_id();
$warehouse_id = 1;
$staff_id     = $_SESSION['staff_id'];

$message = '';
$alert   = 'success';

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * AUTO BATCH
 */
function makeBatchNo()
{
    return 'FB-' . date('YmdHis') . '-' . rand(100,999);
}

/**
 * HANDLE RECEIVE
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token');
    }

    $batch_no         = trim($_POST['batch_no']) ?: makeBatchNo();
    $received_date    = $_POST['received_date'] ?? date('Y-m-d');
    $manufacture_date = $_POST['manufacture_date'] ?: null;
    $expiry_date      = $_POST['expiry_date'] ?: null;
    $supplier_name    = trim($_POST['supplier_name'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');
    $low_stock_level  = (float)($_POST['low_stock_level'] ?? 50);

    $feed_types = $_POST['feed_type'] ?? [];
    $bag_counts = $_POST['bag_count'] ?? [];
    $bag_sizes  = $_POST['bag_weight_kg'] ?? [];
    $costs      = $_POST['cost_per_bag'] ?? [];

    if (empty($feed_types)) {
        $message = "Add at least one feed line.";
        $alert = "danger";
    } else {

        try {

            $pdo->beginTransaction();

            /**
             * CHECK UNIQUE BATCH
             */
            $stmt = $pdo->prepare("SELECT id FROM feed_batches WHERE batch_no=? LIMIT 1");
            $stmt->execute([$batch_no]);

            if ($stmt->fetch()) {
                throw new Exception("Batch already exists.");
            }

            /**
             * CREATE BATCH
             */
            $stmt = $pdo->prepare("
                INSERT INTO feed_batches
                (batch_no,farm_id,warehouse_id,supplier_name,received_date,manufacture_date,expiry_date,total_cost,notes)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $batch_no,
                $farm_id,
                $warehouse_id,
                $supplier_name,
                $received_date,
                $manufacture_date,
                $expiry_date,
                0,
                $notes
            ]);

            $batch_id = $pdo->lastInsertId();

            $batch_total_cost = 0;
            $valid_rows = 0;

            /**
             * LOOP FEEDS
             */
            for ($i = 0; $i < count($feed_types); $i++) {

                $feed_type = trim($feed_types[$i] ?? '');
                $bags      = (int)($bag_counts[$i] ?? 0);
                $size      = (float)($bag_sizes[$i] ?? 0);
                $cost_bag  = (float)($costs[$i] ?? 0);

                if (!$feed_type || $bags <= 0 || $size <= 0 || $cost_bag <= 0) {
                    throw new Exception("Invalid input at row " . ($i + 1));
                }

                $valid_rows++;

                $qty_kg  = $bags * $size;
                $cost_kg = $cost_bag / $size;
                $total   = $bags * $cost_bag;

                $batch_total_cost += $total;

                /**
                 * INSERT STORE
                 */
                $stmt = $pdo->prepare("
                    INSERT INTO feed_store
                    (
                        feed_type,farm_id,warehouse_id,batch_no,
                        received_date,manufacture_date,expiry_date,supplier_name,
                        quantity_kg,damaged_kg,reserved_kg,available_kg,initial_quantity_kg,
                        cost_per_kg,total_cost,low_stock_level,status,notes,
                        bag_count,bag_weight_kg,unit_cost_per_bag
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->execute([
                    $feed_type,$farm_id,$warehouse_id,$batch_no,
                    $received_date,$manufacture_date,$expiry_date,$supplier_name,
                    $qty_kg,0,0,$qty_kg,$qty_kg,
                    $cost_kg,$total,$low_stock_level,'active',$notes,
                    $bags,$size,$cost_bag
                ]);

                $stock_id = $pdo->lastInsertId();

                /**
                 * INSERT LOG
                 */
                $stmt = $pdo->prepare("
                    INSERT INTO feed_store_logs
                    (
                        idempotency_key,date,farm_id,stock_owner_farm_id,warehouse_id,
                        feed_store_id,feed_type,batch_no,
                        opening_stock,received,issued,closing_stock,balance_after,
                        issued_to,pond_id,fish_batch_id,batch_source_id,
                        unit_cost,total_cost,running_value,
                        movement_type,status,reference_no,
                        authorized_by,approved_at,requested_by,storekeeper,issued_at,remarks
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->execute([
                    hash('sha256', $batch_no.$i.microtime(true)),
                    date('Y-m-d'),
                    $farm_id,
                    $farm_id,
                    $warehouse_id,
                    $stock_id,
                    $feed_type,
                    $batch_no,
                    0,
                    $qty_kg,
                    0,
                    $qty_kg,
                    $qty_kg,
                    'MAIN STORE',
                    null,null,null,
                    $cost_kg,
                    $total,
                    $total,
                    'receive',
                    'posted',
                    'PO-'.date('YmdHis'),
                    $staff_id,
                    date('Y-m-d H:i:s'),
                    $staff_id,
                    $staff_id,
                    date('Y-m-d H:i:s'),
                    'Batch receive'
                ]);
            }

            if ($valid_rows === 0) {
                throw new Exception("No valid rows.");
            }

            /**
             * UPDATE BATCH COST
             */
            $stmt = $pdo->prepare("UPDATE feed_batches SET total_cost=? WHERE id=?");
            $stmt->execute([$batch_total_cost, $batch_id]);

            $pdo->commit();

            $message = "Batch received successfully.";
            $alert = "success";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $alert = "danger";
        }
    }
}
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>


<title>Receive Feed | YOTRIBE IFMS</title>
<style>
:root{
    --rf-bg:#f5f7fb;
    --rf-card:#ffffff;
    --rf-text:#172033;
    --rf-muted:#667085;
    --rf-border:#e4e8ef;
    --rf-primary:#173b63;
    --rf-primary-2:#245b91;
    --rf-success:#16845b;
    --rf-danger:#c63d4f;
    --rf-shadow:0 12px 30px rgba(15,23,42,.07);
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
    background:var(--rf-bg);
    color:var(--rf-text);
    font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;
    font-size:14px;
}
.receive-page{width:100%;max-width:1180px;margin:0 auto;padding:28px 20px 48px}
.hero{
    background:linear-gradient(135deg,#173b63 0%,#245b91 58%,#3478a9 100%);
    color:#fff;
    border-radius:22px;
    padding:26px 28px;
    box-shadow:var(--rf-shadow);
    margin-bottom:20px;
}
.hero-main{display:flex;align-items:center;justify-content:space-between;gap:20px}
.hero-icon{
    width:52px;height:52px;border-radius:15px;
    display:grid;place-items:center;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.18);
    font-size:25px;flex:0 0 auto;
}
.hero-title{display:flex;align-items:center;gap:15px;min-width:0}
.hero h1{font-size:24px;line-height:1.2;margin:0 0 5px;font-weight:800}
.hero p{margin:0;color:rgba(255,255,255,.78);font-size:12px}
.btn-back{
    display:inline-flex;align-items:center;justify-content:center;
    min-height:42px;padding:0 17px;border-radius:11px;
    background:#fff;color:var(--rf-primary);text-decoration:none;
    font-weight:800;font-size:12px;white-space:nowrap;
}
.notice{
    border:1px solid var(--rf-border);border-radius:14px;
    padding:13px 15px;margin-bottom:18px;background:#fff;
    box-shadow:0 5px 16px rgba(15,23,42,.04);
}
.notice.success{border-color:#bce8d5;background:#f2fcf7;color:#126b4b}
.notice.danger{border-color:#f1c4cb;background:#fff5f6;color:#a72d3e}
.card{
    background:var(--rf-card);
    border:1px solid var(--rf-border);
    border-radius:18px;
    box-shadow:var(--rf-shadow);
    margin-bottom:18px;
    overflow:hidden;
}
.card-head{
    padding:18px 20px;
    border-bottom:1px solid var(--rf-border);
    display:flex;align-items:center;justify-content:space-between;gap:15px;
}
.card-head h2{font-size:15px;margin:0;font-weight:800}
.card-head p{margin:4px 0 0;color:var(--rf-muted);font-size:11px}
.card-body{padding:20px}
.section-label{
    display:block;font-size:10px;font-weight:800;
    letter-spacing:.65px;text-transform:uppercase;color:#64748b;
    margin-bottom:7px;
}
.feed-row{
    display:grid;
    grid-template-columns:minmax(180px,1.7fr) minmax(100px,.8fr) minmax(120px,1fr) minmax(150px,1.2fr) 44px;
    gap:10px;
    align-items:end;
    padding:13px;
    border:1px solid var(--rf-border);
    background:#fafbfd;
    border-radius:14px;
    margin-bottom:10px;
}
.field label{display:block}
.form-control,.form-select{
    width:100%;height:43px;border:1px solid #d8dee8;border-radius:10px;
    padding:0 12px;background:#fff;color:var(--rf-text);
    font:inherit;font-size:13px;outline:none;
}
.form-control:focus,.form-select:focus{
    border-color:#6c98bf;box-shadow:0 0 0 3px rgba(36,91,145,.10)
}
.remove-row{
    width:43px;height:43px;border:0;border-radius:10px;
    background:#fff0f2;color:var(--rf-danger);font-weight:900;cursor:pointer;
}
.remove-row:hover{background:#ffe1e5}
.add-row{
    display:inline-flex;align-items:center;gap:7px;
    height:40px;padding:0 14px;border:1px solid #d7dee8;
    background:#fff;border-radius:10px;color:var(--rf-primary);
    font-weight:800;font-size:12px;cursor:pointer;
}
.add-row:hover{background:#f7faff}
.meta-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:14px;
}
.meta-grid .full{grid-column:1/-1}
.actions{
    margin-top:20px;padding-top:18px;border-top:1px solid var(--rf-border);
}
.receive-btn{
    width:100%;height:48px;border:0;border-radius:12px;
    background:linear-gradient(135deg,#16845b,#1c9b6c);
    color:#fff;font-weight:800;font-size:13px;cursor:pointer;
    box-shadow:0 8px 18px rgba(22,132,91,.18);
}
.receive-btn:hover{filter:brightness(.97)}
.helper{
    margin-top:9px;color:var(--rf-muted);font-size:11px;line-height:1.5;
}
@media (min-width:1200px){.receive-page{max-width:1240px}}
@media (max-width:900px){
    .feed-row{grid-template-columns:1.4fr .8fr 1fr 1fr 43px}
    .meta-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:700px){
    .receive-page{padding:18px 12px 35px}
    .hero{padding:20px;border-radius:17px}
    .hero-main{align-items:flex-start}
    .hero h1{font-size:20px}
    .hero-title{align-items:flex-start}
    .hero-icon{width:44px;height:44px;font-size:20px}
    .btn-back{min-height:38px;padding:0 12px}
    .card{border-radius:15px}
    .card-head,.card-body{padding:16px}
    .feed-row{
        grid-template-columns:1fr 1fr;
        gap:10px;
    }
    .feed-row .feed-name{grid-column:1/-1}
    .feed-row .remove-wrap{grid-column:2;justify-self:end}
    .meta-grid{grid-template-columns:1fr}
    .meta-grid .full{grid-column:auto}
}
@media (max-width:480px){
    .hero-main{display:block}
    .btn-back{margin-top:15px;width:100%}
    .feed-row{grid-template-columns:1fr}
    .feed-row .feed-name,
    .feed-row .remove-wrap{grid-column:auto}
    .feed-row .remove-wrap{justify-self:stretch}
    .remove-row{width:100%}
    .card-head{display:block}
    .card-head p{margin-top:5px}
    .form-control,.form-select{height:45px}
}
</style>
</head>
<body>
<main class="receive-page">

    <section class="hero">
        <div class="hero-main">
            <div class="hero-title">
                <div class="hero-icon">📦</div>
                <div>
                    <h1>Feed Receiving Center</h1>
                    <p>Universal Warehouse Intake • Cost Ownership Tracking</p>
                </div>
            </div>
            <a href="index.php" class="btn-back">← Back to Feed Store</a>
        </div>
    </section>

    <?php if($message): ?>
        <div class="notice <?= htmlspecialchars($alert) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Feed Lines</h2>
                    <p>Add each feed type, bag quantity, bag size and purchase cost.</p>
                </div>
            </div>

            <div class="card-body">
                <div id="feedRows">
                    <div class="feed-row">
                        <div class="field feed-name">
                            <label class="section-label">Feed Type</label>
                            <input name="feed_type[]" class="form-control" placeholder="e.g. Coppens 2mm" required>
                        </div>
                        <div class="field">
                            <label class="section-label">Bags</label>
                            <input name="bag_count[]" type="number" min="1" step="1" class="form-control" placeholder="0" required>
                        </div>
                        <div class="field">
                            <label class="section-label">Bag Size</label>
                            <select name="bag_weight_kg[]" class="form-select" required>
                                <option value="15">15 kg</option>
                                <option value="5">5 kg</option>
                                <option value="25">25 kg</option>
                            </select>
                        </div>
                        <div class="field">
                            <label class="section-label">Cost / Bag</label>
                            <input name="cost_per_bag[]" type="number" min="0.01" step="0.01" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="remove-wrap">
                            <label class="section-label">&nbsp;</label>
                            <button type="button" class="remove-row" title="Remove feed line">×</button>
                        </div>
                    </div>
                </div>

                <button type="button" id="addRow" class="add-row">＋ Add Feed Line</button>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Batch Information</h2>
                    <p>Record the receiving and ownership details for this stock intake.</p>
                </div>
            </div>

            <div class="card-body">
                <div class="meta-grid">
                    <div class="field">
                        <label class="section-label">Batch Number</label>
                        <input name="batch_no" class="form-control" placeholder="Auto-generated if blank">
                    </div>

                    <div class="field">
                        <label class="section-label">Supplier</label>
                        <input name="supplier_name" class="form-control" placeholder="Supplier name">
                    </div>

                    <div class="field">
                        <label class="section-label">Received Date</label>
                        <input type="date" name="received_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                    </div>

                    <div class="field">
                        <label class="section-label">Manufacture Date</label>
                        <input type="date" name="manufacture_date" class="form-control">
                    </div>

                    <div class="field">
                        <label class="section-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>

                    <div class="field">
                        <label class="section-label">Low Stock Level (kg)</label>
                        <input name="low_stock_level" type="number" min="0" step="0.01" value="50" class="form-control">
                    </div>

                    <div class="field full">
                        <label class="section-label">Notes</label>
                        <input name="notes" class="form-control" placeholder="Optional receiving notes">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="receive-btn">Receive Batch into Store</button>
                    <div class="helper">
                        The system will create the batch, add the feed quantities to the universal store,
                        calculate cost per kg and record the receiving movement.
                    </div>
                </div>
            </div>
        </section>
    </form>
</main>

<script>
document.getElementById('addRow').addEventListener('click', function () {
    const source = document.querySelector('.feed-row');
    const row = source.cloneNode(true);

    row.querySelectorAll('input').forEach(function (input) {
        input.value = '';
    });

    row.querySelectorAll('select').forEach(function (select) {
        select.selectedIndex = 0;
    });

    document.getElementById('feedRows').appendChild(row);
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('.remove-row');

    if (!button) {
        return;
    }

    const rows = document.querySelectorAll('.feed-row');

    if (rows.length > 1) {
        button.closest('.feed-row').remove();
    } else {
        button.closest('.feed-row').querySelectorAll('input').forEach(function (input) {
            input.value = '';
        });
    }
});
</script>
</body>
</html>
