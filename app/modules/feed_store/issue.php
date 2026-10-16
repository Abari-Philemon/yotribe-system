<?php
require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/permission.php';

/**
 * MODULE ACCESS
 */
require_permission('feed_store');

/**
 * FARM CONTEXT
 */
$farm_id = farm_id();

/**
 * PAGE TITLE
 */
$page_title = "Dashboard";

$staff_id = $_SESSION['staff_id'];

$message = '';
$alert   = 'success';

/**
 * CSRF
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * HANDLE ISSUE
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token');
    }

    $feed_type      = trim($_POST['feed_type'] ?? '');
    $pond_id        = (int)($_POST['pond_id'] ?? 0);
    $fish_batch_id  = !empty($_POST['fish_batch_id']) ? (int)$_POST['fish_batch_id'] : null;
    $qty            = (float)($_POST['quantity_kg'] ?? 0);
    $remarks        = trim($_POST['remarks'] ?? '');

    if ($feed_type === '' || $pond_id <= 0 || $qty <= 0) {
        $message = 'Please complete all required fields.';
        $alert   = 'danger';
    } else {

        try {

            $pdo->beginTransaction();

            /**
             * LOCK POND
             */
            $stmt = $pdo->prepare("
                SELECT id, pond_code
                FROM ponds_tanks
                WHERE id=? AND farm_id=?
                FOR UPDATE
            ");
            $stmt->execute([$pond_id, $farm_id]);
            $pond = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pond) {
                throw new Exception('Invalid pond.');
            }

            /**
             * FIFO STOCK (STRICT)
             */
            $stmt = $pdo->prepare("
                SELECT *
                FROM feed_store
                WHERE feed_type = ?
                AND status = 'active'
                AND available_kg > 0
                AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                ORDER BY received_date ASC, id ASC
                FOR UPDATE
            ");
            $stmt->execute([$feed_type]);
            $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$stocks) {
                throw new Exception('No available stock.');
            }

            $available = array_sum(array_column($stocks, 'available_kg'));

            if ($available < $qty) {
                throw new Exception("Only ".number_format($available,2)." kg available.");
            }

            $remaining = $qty;
            $usedRows  = 0;

            foreach ($stocks as $row) {

                if ($remaining <= 0) break;

                $take = min($remaining, $row['available_kg']);

                $opening = (float)$row['available_kg'];
                $closing = $opening - $take;
                $cost    = $take * $row['cost_per_kg'];

                $status = $closing <= 0 ? 'finished' : 'active';

                /**
                 * UPDATE STOCK
                 */
                $stmt = $pdo->prepare("
                    UPDATE feed_store
                    SET available_kg = ?,
                        status = ?,
                        last_issue_date = CURDATE(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $closing,
                    $status,
                    $row['id']
                ]);

                /**
                 * CONSUMPTION TRACKING
                 */
                $pdo->prepare("
                    INSERT INTO feed_consumption
                    (feed_store_id,batch_no,farm_id,pond_id,fish_batch_id,quantity_kg,unit_cost,total_cost)
                    VALUES (?,?,?,?,?,?,?,?)
                ")->execute([
                    $row['id'],
                    $row['batch_no'],
                    $farm_id,
                    $pond_id,
                    $fish_batch_id,
                    $take,
                    $row['cost_per_kg'],
                    $cost
                ]);

                /**
                 * LOG
                 */
                $stmt = $pdo->prepare("
                    INSERT INTO feed_store_logs
                    (
                        idempotency_key,date,farm_id,stock_owner_farm_id,warehouse_id,
                        feed_store_id,feed_type,batch_no,
                        opening_stock,received,issued,closing_stock,balance_after,
                        issued_to,pond_id,fish_batch_id,
                        unit_cost,total_cost,running_value,
                        movement_type,status,reference_no,
                        authorized_by,approved_at,requested_by,storekeeper,issued_at,remarks
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                $stmt->execute([
                    hash('sha256', $row['id'] . microtime(true)),
                    date('Y-m-d'),
                    $farm_id,
                    $row['farm_id'],
                    1,
                    $row['id'],
                    $feed_type,
                    $row['batch_no'],
                    $opening,
                    0,
                    $take,
                    $closing,
                    $closing,
                    $pond['pond_code'],
                    $pond_id,
                    $fish_batch_id,
                    $row['cost_per_kg'],
                    $cost,
                    $cost,
                    'issue',
                    'posted',
                    'ISS-' . date('YmdHis'),
                    $staff_id,
                    date('Y-m-d H:i:s'),
                    $staff_id,
                    $staff_id,
                    date('Y-m-d H:i:s'),
                    $remarks ?: 'Auto FIFO issue'
                ]);

                $remaining -= $take;
                $usedRows++;
            }

            $pdo->commit();

            $message = number_format($qty,2)." kg issued to {$pond['pond_code']} (FIFO: {$usedRows} batch(es)).";
            $alert   = 'success';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $alert   = 'danger';
        }
    }
}

/**
 * LOAD FEEDS
 */
$stmt = $pdo->query("
    SELECT DISTINCT feed_type
    FROM feed_store
    WHERE available_kg > 0 AND status='active'
    ORDER BY feed_type
");
$feeds = $stmt->fetchAll(PDO::FETCH_COLUMN);

/**
 * LOAD PONDS
 */
$stmt = $pdo->prepare("
    SELECT id, pond_code, pond_type
    FROM ponds_tanks
    WHERE farm_id=?
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * KPI
 */
$total_stock = $pdo->query("
    SELECT COALESCE(SUM(available_kg),0)
    FROM feed_store
    WHERE status='active'
")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(issued),0)
    FROM feed_store_logs
    WHERE farm_id=? AND movement_type='issue' AND date=CURDATE()
");
$stmt->execute([$farm_id]);
$today_issue = $stmt->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<title>Issue Feed | YOTRIBE IFMS</title>

<style>
:root{
    --if-bg:#f5f7fb;
    --if-card:#ffffff;
    --if-text:#172033;
    --if-muted:#667085;
    --if-border:#e4e8ef;
    --if-primary:#173b63;
    --if-primary-2:#245b91;
    --if-success:#16845b;
    --if-danger:#c63d4f;
    --if-warning:#c77a12;
    --if-shadow:0 12px 30px rgba(15,23,42,.07);
}

.issue-page{
    width:100%;
    max-width:1180px;
    margin:0 auto;
    padding:28px 20px 48px;
    color:var(--if-text);
    font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;
}

.issue-hero{
    background:linear-gradient(135deg,#173b63 0%,#245b91 58%,#3478a9 100%);
    color:#fff;
    border-radius:22px;
    padding:26px 28px;
    box-shadow:var(--if-shadow);
    margin-bottom:20px;
}

.issue-hero-main{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
}

.issue-hero-title{
    display:flex;
    align-items:center;
    gap:15px;
    min-width:0;
}

.issue-hero-icon{
    width:52px;
    height:52px;
    border-radius:15px;
    display:grid;
    place-items:center;
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.18);
    font-size:25px;
    flex:0 0 auto;
}

.issue-hero h1{
    font-size:24px;
    line-height:1.2;
    margin:0 0 5px;
    font-weight:800;
}

.issue-hero p{
    margin:0;
    color:rgba(255,255,255,.78);
    font-size:12px;
}

.issue-back{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:0 17px;
    border-radius:11px;
    background:#fff;
    color:var(--if-primary);
    text-decoration:none;
    font-weight:800;
    font-size:12px;
    white-space:nowrap;
}

.issue-back:hover{
    color:var(--if-primary-2);
    text-decoration:none;
}

.issue-notice{
    border:1px solid var(--if-border);
    border-radius:14px;
    padding:13px 15px;
    margin-bottom:18px;
    background:#fff;
    box-shadow:0 5px 16px rgba(15,23,42,.04);
}

.issue-notice.success{
    border-color:#bce8d5;
    background:#f2fcf7;
    color:#126b4b;
}

.issue-notice.danger{
    border-color:#f1c4cb;
    background:#fff5f6;
    color:#a72d3e;
}

.issue-kpis{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
    margin-bottom:18px;
}

.issue-kpi{
    background:var(--if-card);
    border:1px solid var(--if-border);
    border-radius:18px;
    padding:20px;
    box-shadow:var(--if-shadow);
}

.issue-kpi-label{
    color:var(--if-muted);
    font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.55px;
}

.issue-kpi-value{
    margin-top:8px;
    font-size:28px;
    line-height:1.1;
    font-weight:800;
    color:var(--if-primary);
}

.issue-kpi-value.success{
    color:var(--if-success);
}

.issue-card{
    background:var(--if-card);
    border:1px solid var(--if-border);
    border-radius:18px;
    box-shadow:var(--if-shadow);
    overflow:hidden;
}

.issue-card-head{
    padding:18px 20px;
    border-bottom:1px solid var(--if-border);
}

.issue-card-head h2{
    font-size:15px;
    margin:0;
    font-weight:800;
}

.issue-card-head p{
    margin:4px 0 0;
    color:var(--if-muted);
    font-size:11px;
}

.issue-card-body{
    padding:20px;
}

.issue-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}

.issue-field label{
    display:block;
    font-size:10px;
    font-weight:800;
    letter-spacing:.65px;
    text-transform:uppercase;
    color:#64748b;
    margin-bottom:7px;
}

.issue-control{
    width:100%;
    height:45px;
    border:1px solid #d8dee8;
    border-radius:10px;
    padding:0 12px;
    background:#fff;
    color:var(--if-text);
    font:inherit;
    font-size:13px;
    outline:none;
}

.issue-control:focus{
    border-color:#6c98bf;
    box-shadow:0 0 0 3px rgba(36,91,145,.10);
}

.issue-control::placeholder{
    color:#98a2b3;
}

.issue-submit-wrap{
    grid-column:1/-1;
    margin-top:4px;
    padding-top:18px;
    border-top:1px solid var(--if-border);
}

.issue-submit{
    width:100%;
    min-height:48px;
    border:0;
    border-radius:12px;
    background:linear-gradient(135deg,#173b63,#245b91);
    color:#fff;
    font-weight:800;
    font-size:13px;
    cursor:pointer;
    box-shadow:0 8px 18px rgba(23,59,99,.18);
}

.issue-submit:hover{
    filter:brightness(.97);
}

.issue-helper{
    margin-top:9px;
    color:var(--if-muted);
    font-size:11px;
    line-height:1.5;
}

@media (min-width:1200px){
    .issue-page{max-width:1240px}
}

@media (max-width:700px){
    .issue-page{
        padding:18px 12px 35px;
    }

    .issue-hero{
        padding:20px;
        border-radius:17px;
    }

    .issue-hero-main{
        align-items:flex-start;
    }

    .issue-hero-title{
        align-items:flex-start;
    }

    .issue-hero-icon{
        width:44px;
        height:44px;
        font-size:20px;
    }

    .issue-hero h1{
        font-size:20px;
    }

    .issue-kpis{
        grid-template-columns:1fr;
    }

    .issue-card{
        border-radius:15px;
    }

    .issue-card-head,
    .issue-card-body{
        padding:16px;
    }

    .issue-grid{
        grid-template-columns:1fr;
        gap:14px;
    }

    .issue-submit-wrap{
        grid-column:auto;
    }
}

@media (max-width:480px){
    .issue-hero-main{
        display:block;
    }

    .issue-back{
        margin-top:15px;
        width:100%;
    }

    .issue-kpi-value{
        font-size:24px;
    }
}
</style>

<main class="issue-page">

    <section class="issue-hero">
        <div class="issue-hero-main">
            <div class="issue-hero-title">
                <div class="issue-hero-icon">🚀</div>
                <div>
                    <h1>Automated Feed Issue Center</h1>
                    <p>Universal Store • FIFO Engine • Multi Farm</p>
                </div>
            </div>

            <a href="index.php" class="issue-back">← Back to Feed Store</a>
        </div>
    </section>

    <?php if($message): ?>
        <div class="issue-notice <?= htmlspecialchars($alert) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="issue-kpis">
        <div class="issue-kpi">
            <div class="issue-kpi-label">Total Store Stock</div>
            <div class="issue-kpi-value">
                <?= number_format((float)$total_stock,2) ?> kg
            </div>
        </div>

        <div class="issue-kpi">
            <div class="issue-kpi-label">Your Farm Used Today</div>
            <div class="issue-kpi-value success">
                <?= number_format((float)$today_issue,2) ?> kg
            </div>
        </div>
    </section>

    <section class="issue-card">
        <div class="issue-card-head">
            <h2>Issue Feed Automatically</h2>
            <p>Select the feed and destination pond. The system will issue stock using FIFO.</p>
        </div>

        <div class="issue-card-body">
            <form method="POST" autocomplete="off">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="issue-grid">

                    <div class="issue-field">
                        <label for="feed_type">Feed Type</label>
                        <select id="feed_type"
                                name="feed_type"
                                class="issue-control"
                                required>
                            <option value="">Select Feed</option>

                            <?php foreach($feeds as $f): ?>
                                <option value="<?= htmlspecialchars($f) ?>">
                                    <?= htmlspecialchars($f) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="issue-field">
                        <label for="pond_id">Destination Pond</label>
                        <select id="pond_id"
                                name="pond_id"
                                class="issue-control"
                                required>
                            <option value="">Select Pond</option>

                            <?php foreach($ponds as $p): ?>
                                <option value="<?= (int)$p['id'] ?>">
                                    <?= htmlspecialchars($p['pond_code']) ?>
                                    (<?= htmlspecialchars($p['pond_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="issue-field">
                        <label for="quantity_kg">Quantity (kg)</label>
                        <input id="quantity_kg"
                               type="number"
                               step="0.01"
                               min="0.01"
                               name="quantity_kg"
                               class="issue-control"
                               placeholder="Enter quantity"
                               required>
                    </div>

                    <div class="issue-field">
                        <label for="remarks">Remarks</label>
                        <input id="remarks"
                               type="text"
                               name="remarks"
                               class="issue-control"
                               placeholder="Optional note">
                    </div>

                    <div class="issue-submit-wrap">
                        <button type="submit" class="issue-submit">
                            🚀 Auto Issue Feed (FIFO)
                        </button>

                        <div class="issue-helper">
                            The system will select available stock in receiving-date order,
                            reduce the selected feed inventory and record the issue movement.
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </section>

</main>
