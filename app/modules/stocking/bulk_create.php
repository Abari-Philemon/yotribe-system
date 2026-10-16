<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id = (int) farm_id();
$farm_name = function_exists('farm_name') ? farm_name() : 'Current Farm';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Load active ponds
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.pond_code,
        p.pond_type,
        p.size_label,
        p.capacity,
        p.length_ft,
        p.width_ft,
        COALESCE((
            SELECT SUM(ps.current_count)
            FROM pond_stocking ps
            WHERE ps.pond_id = p.id
              AND ps.farm_id = p.farm_id
              AND ps.status = 'active'
        ), 0) AS current_stock
    FROM ponds_tanks p
    WHERE p.farm_id = ?
      AND p.status = 'active'
    ORDER BY p.pond_code
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load active fish batches with available fish
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        batch_code,
        species,
        current_count,
        avg_weight_g
    FROM fish_batches
    WHERE farm_id = ?
      AND status = 'active'
      AND current_count > 0
    ORDER BY id DESC
");
$stmt->execute([$farm_id]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Flash messages
|--------------------------------------------------------------------------
*/
$error = $_SESSION['error'] ?? ($_GET['error'] ?? '');
$success = $_SESSION['success'] ?? '';

unset($_SESSION['error'], $_SESSION['success']);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Bulk Fish Stocking</h3>
            <div class="text-muted">
                Farm: <?= htmlspecialchars((string)$farm_name, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <a href="index.php" class="btn btn-outline-secondary">Back</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars((string)$success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$ponds): ?>
        <div class="alert alert-warning">
            No active ponds or tanks are available for stocking.
        </div>
    <?php elseif (!$batches): ?>
        <div class="alert alert-warning">
            No active fish batch has fish available for stocking.
        </div>
    <?php else: ?>

    <form method="POST" action="bulk_store.php" id="bulkStockingForm">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">1. Select Fish Batch</h5>
            </div>

            <div class="card-body">
                <label for="batch_id" class="form-label fw-semibold">
                    Source batch
                </label>

                <select
                    name="batch_id"
                    id="batch_id"
                    class="form-select"
                    required
                >
                    <option value="">Select fish batch</option>

                    <?php foreach ($batches as $batch): ?>
                        <option
                            value="<?= (int)$batch['id'] ?>"
                            data-available="<?= (int)$batch['current_count'] ?>"
                            data-weight="<?= htmlspecialchars(
                                (string)$batch['avg_weight_g'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <?= htmlspecialchars($batch['batch_code'], ENT_QUOTES, 'UTF-8') ?>
                            |
                            <?= htmlspecialchars($batch['species'] ?? 'Fish', ENT_QUOTES, 'UTF-8') ?>
                            |
                            <?= number_format((int)$batch['current_count']) ?> fish available
                            |
                            <?= number_format((float)$batch['avg_weight_g'], 2) ?> g average
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Available fish</label>
                        <input
                            type="text"
                            id="available_display"
                            class="form-control"
                            readonly
                            value="Select a batch"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Recorded average weight (g)</label>
                        <input
                            type="text"
                            id="weight_display"
                            class="form-control"
                            readonly
                            value="Select a batch"
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fish stage</label>
                        <input
                            type="text"
                            id="stage_display"
                            class="form-control"
                            readonly
                            value="Select a batch"
                        >
                    </div>
                </div>

                <div class="form-text mt-2">
                    Average weight comes from the selected batch and cannot
                    be changed in this bulk operation.
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">2. Destination Ponds</h5>

                <button
                    type="button"
                    id="addPondBtn"
                    class="btn btn-outline-primary btn-sm"
                >
                    + Add pond
                </button>
            </div>

            <div class="card-body">

                <div class="alert alert-info">
                    Add each destination pond once and enter the number of
                    fish to allocate to it. The server will validate each
                    pond's capacity and the total batch balance.
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:220px;">Pond / Tank</th>
                                <th>Type / Size</th>
                                <th>Current fish</th>
                                <th style="min-width:150px;">Quantity to stock</th>
                                <th style="width:90px;">Action</th>
                            </tr>
                        </thead>

                        <tbody id="pondRows"></tbody>

                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">
                                    Total fish to stock
                                </th>
                                <th>
                                    <span id="totalQuantity">0</span>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div id="summaryBox" class="alert alert-secondary">
                    Select a batch and add destination ponds.
                </div>

                <button
                    type="submit"
                    id="submitBtn"
                    class="btn btn-primary btn-lg w-100"
                    disabled
                >
                    Stock Batch Into Selected Ponds
                </button>
            </div>
        </div>

    </form>

    <template id="pondRowTemplate">
        <tr class="pond-row">
            <td>
                <select
                    name="pond_id[]"
                    class="form-select pond-select"
                    required
                >
                    <option value="">Select pond</option>

                    <?php foreach ($ponds as $pond): ?>
                        <option
                            value="<?= (int)$pond['id'] ?>"
                            data-code="<?= htmlspecialchars($pond['pond_code'], ENT_QUOTES, 'UTF-8') ?>"
                            data-type="<?= htmlspecialchars($pond['pond_type'], ENT_QUOTES, 'UTF-8') ?>"
                            data-size="<?= htmlspecialchars($pond['size_label'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-capacity="<?= (int)$pond['capacity'] ?>"
                            data-current="<?= (int)$pond['current_stock'] ?>"
                        >
                            <?= htmlspecialchars($pond['pond_code'], ENT_QUOTES, 'UTF-8') ?>
                            — DB capacity <?= number_format((int)$pond['capacity']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>

            <td class="pond-size text-muted">—</td>
            <td class="pond-current">—</td>

            <td>
                <input
                    type="number"
                    name="quantity[]"
                    class="form-control quantity-input"
                    min="1"
                    step="1"
                    required
                    placeholder="Fish count"
                >
            </td>

            <td>
                <button
                    type="button"
                    class="btn btn-outline-danger btn-sm remove-row"
                >
                    Remove
                </button>
            </td>
        </tr>
    </template>

    <script>
    (() => {
        const form = document.getElementById('bulkStockingForm');
        const batchEl = document.getElementById('batch_id');
        const rowsEl = document.getElementById('pondRows');
        const template = document.getElementById('pondRowTemplate');
        const addBtn = document.getElementById('addPondBtn');
        const submitBtn = document.getElementById('submitBtn');

        const availableDisplay = document.getElementById('available_display');
        const weightDisplay = document.getElementById('weight_display');
        const stageDisplay = document.getElementById('stage_display');
        const totalDisplay = document.getElementById('totalQuantity');
        const summaryBox = document.getElementById('summaryBox');

        const formatNumber = value =>
            Number(value || 0).toLocaleString();

        function selectedBatch() {
            const option = batchEl.selectedOptions[0];

            if (!option || !option.value) {
                return null;
            }

            return {
                available: Number(option.dataset.available || 0),
                weight: Number(option.dataset.weight || 0)
            };
        }

        function updateBatchInfo() {
            const batch = selectedBatch();

            if (!batch) {
                availableDisplay.value = 'Select a batch';
                weightDisplay.value = 'Select a batch';
                stageDisplay.value = 'Select a batch';
                updateSummary();
                return;
            }

            availableDisplay.value = formatNumber(batch.available);
            weightDisplay.value = batch.weight.toFixed(2);

            if (batch.weight < 10) {
                stageDisplay.value = 'Invalid weight (< 10 g)';
            } else if (batch.weight < 100) {
                stageDisplay.value = 'Juvenile';
            } else {
                stageDisplay.value = 'Grow-out';
            }

            updateSummary();
        }

        function addRow() {
            const row = template.content.firstElementChild.cloneNode(true);

            row.querySelector('.remove-row').addEventListener('click', () => {
                row.remove();
                refreshRows();
            });

            row.querySelector('.pond-select').addEventListener('change', () => {
                refreshRows();
            });

            row.querySelector('.quantity-input').addEventListener('input', () => {
                updateSummary();
            });

            rowsEl.appendChild(row);
            refreshRows();
        }

        function refreshRows() {
            const selectedIds = [];

            rowsEl.querySelectorAll('.pond-row').forEach(row => {
                const select = row.querySelector('.pond-select');
                const option = select.selectedOptions[0];

                if (select.value) {
                    selectedIds.push(select.value);
                }

                if (option && option.value) {
                    const type = option.dataset.type || '';
                    const size = option.dataset.size || '';

                    row.querySelector('.pond-size').textContent =
                        type + (size ? ' / ' + size : '');

                    row.querySelector('.pond-current').textContent =
                        formatNumber(option.dataset.current);
                } else {
                    row.querySelector('.pond-size').textContent = '—';
                    row.querySelector('.pond-current').textContent = '—';
                }

                Array.from(select.options).forEach(opt => {
                    if (!opt.value) return;

                    opt.disabled =
                        selectedIds.includes(opt.value) &&
                        opt.value !== select.value;
                });
            });

            updateSummary();
        }

        function updateSummary() {
            const batch = selectedBatch();
            let total = 0;
            let validRows = 0;
            let allPondsSelected = true;

            rowsEl.querySelectorAll('.pond-row').forEach(row => {
                const pond = row.querySelector('.pond-select').value;
                const qty = Number(row.querySelector('.quantity-input').value);

                if (!pond) {
                    allPondsSelected = false;
                }

                if (pond && Number.isInteger(qty) && qty > 0) {
                    total += qty;
                    validRows++;
                }
            });

            totalDisplay.textContent = formatNumber(total);

            if (!batch) {
                summaryBox.className = 'alert alert-secondary';
                summaryBox.textContent = 'Select a batch and add destination ponds.';
                submitBtn.disabled = true;
                return;
            }

            if (batch.weight < 10) {
                summaryBox.className = 'alert alert-danger';
                summaryBox.textContent =
                    'The batch average weight must be at least 10 g.';
                submitBtn.disabled = true;
                return;
            }

            if (total > batch.available) {
                summaryBox.className = 'alert alert-danger';
                summaryBox.textContent =
                    'Total quantity exceeds available batch stock (' +
                    formatNumber(batch.available) + ' fish).';
                submitBtn.disabled = true;
                return;
            }

            if (total < 1 || validRows < 1 || !allPondsSelected) {
                summaryBox.className = 'alert alert-secondary';
                summaryBox.textContent =
                    'Select a pond and enter a positive quantity for every row.';
                submitBtn.disabled = true;
                return;
            }

            summaryBox.className = 'alert alert-info';
            summaryBox.textContent =
                formatNumber(total) + ' fish will be allocated across ' +
                rowsEl.querySelectorAll('.pond-row').length +
                ' pond row(s). Each pond will be validated on the server.';
            submitBtn.disabled = false;
        }

        addBtn.addEventListener('click', addRow);
        batchEl.addEventListener('change', updateBatchInfo);

        form.addEventListener('submit', event => {
            const batch = selectedBatch();

            if (!batch || batch.weight < 10) {
                event.preventDefault();
                alert('Select a valid batch with an average weight of at least 10 g.');
                return;
            }

            if (!window.confirm(
                'Submit bulk stocking for ' +
                totalDisplay.textContent +
                ' fish? The operation will be fully rolled back if any pond fails validation.'
            )) {
                event.preventDefault();
            }
        });

        addRow();
        updateBatchInfo();
    })();
    </script>

    <?php endif; ?>
</div>
