<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/stocking_helper.php';

$farm_id = (int) farm_id();
$farm_name = farm_name();

function stockingHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Load active ponds
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        farm_id,
        pond_code,
        pond_type,
        size_label,
        length_ft,
        width_ft,
        volume_liters,
        capacity,
        status
    FROM ponds_tanks
    WHERE farm_id = ?
      AND status = 'active'
    ORDER BY pond_code ASC
");
$stmt->execute([$farm_id]);
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Load batches with available fish
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        batch_code,
        current_count,
        avg_weight_g,
        species,
        created_at
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
| Load current active stock per pond
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        pond_id,
        COALESCE(SUM(current_count), 0) AS current_stock
    FROM pond_stocking
    WHERE farm_id = ?
      AND status = 'active'
    GROUP BY pond_id
");
$stmt->execute([$farm_id]);
$pondStock = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Stock Fish Into Pond</h3>
            <div class="text-muted">
                Farm: <?= stockingHtml($farm_name) ?>
            </div>
        </div>

        <a href="index.php" class="btn btn-outline-secondary">
            Back
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Fish stocking completed successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?= stockingHtml($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($ponds)): ?>
        <div class="alert alert-warning">
            No active ponds are available for this farm.
        </div>
    <?php endif; ?>

    <?php if (empty($batches)): ?>
        <div class="alert alert-warning">
            No active fish batches with available fish were found.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white py-3">
            <h5 class="mb-0">New Stocking Record</h5>
        </div>

        <div class="card-body p-4">

            <form method="POST" action="store.php" id="stockingForm">

                <!-- POND SELECTION -->
                <div class="mb-4">
                    <label for="pond" class="form-label fw-semibold">
                        Select Pond
                    </label>

                    <select
                        name="pond_id"
                        id="pond"
                        class="form-select"
                        required
                    >
                        <option value="">-- Select Pond --</option>

                        <?php foreach ($ponds as $p): ?>
                            <?php
                            $pondId = (int) $p['id'];
                            $current = (int) ($pondStock[$pondId] ?? 0);
                            $capacity = (int) ($p['capacity'] ?? 0);

                            if ($current === 0) {
                                $initialStatus = 'Empty';
                            } elseif (
                                $capacity > 0 &&
                                $current >= $capacity
                            ) {
                                $initialStatus = 'Filled';
                            } else {
                                $initialStatus = 'Partially Filled';
                            }

                            $pondType = strtolower(
                                trim((string) $p['pond_type'])
                            );
                            ?>

                            <option
                                value="<?= $pondId ?>"
                                data-code="<?= stockingHtml($p['pond_code']) ?>"
                                data-type="<?= stockingHtml($pondType) ?>"
                                data-size-label="<?= stockingHtml($p['size_label']) ?>"
                                data-length="<?= stockingHtml($p['length_ft']) ?>"
                                data-width="<?= stockingHtml($p['width_ft']) ?>"
                                data-volume="<?= stockingHtml($p['volume_liters']) ?>"
                                data-capacity="<?= $capacity ?>"
                                data-current="<?= $current ?>"
                                data-base-label="<?= stockingHtml(
                                    $p['pond_code'] . ' | ' . ucfirst($pondType)
                                ) ?>"
                            >
                                <?= stockingHtml($p['pond_code']) ?>
                                |
                                <?= stockingHtml(ucfirst($pondType)) ?>
                                |
                                <?= stockingHtml($initialStatus) ?>
                                |
                                Stock: <?= number_format($current) ?>
                                / <?= number_format($capacity) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text">
                        Pond status is updated for the selected fish stage.
                    </div>
                </div>

                <!-- BATCH SELECTION -->
                <div class="mb-4">
                    <label for="batch" class="form-label fw-semibold">
                        Select Fish Batch
                    </label>

                    <select
                        name="batch_id"
                        id="batch"
                        class="form-select"
                        required
                    >
                        <option value="">-- Select Fish Batch --</option>

                        <?php foreach ($batches as $b): ?>
                            <?php
                            $available = (int) $b['current_count'];
                            $weight = is_numeric($b['avg_weight_g'])
                                ? (float) $b['avg_weight_g']
                                : 0.0;

                            $biomass = ($available * $weight) / 1000;

                            if ($weight >= 10 && $weight < 100) {
                                $stageLabel = 'Juvenile';
                            } elseif ($weight >= 100) {
                                $stageLabel = 'Grow-out';
                            } else {
                                $stageLabel = 'Weight requires correction';
                            }
                            ?>

                            <option
                                value="<?= (int) $b['id'] ?>"
                                data-available="<?= $available ?>"
                                data-weight="<?= stockingHtml($weight) ?>"
                                data-species="<?= stockingHtml($b['species']) ?>"
                            >
                                <?= stockingHtml($b['batch_code']) ?>
                                |
                                <?= stockingHtml($b['species']) ?>
                                |
                                <?= number_format($available) ?> fish
                                |
                                <?= number_format($weight, 2) ?> g
                                |
                                <?= number_format($biomass, 2) ?> kg biomass
                                |
                                <?= stockingHtml($stageLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-text">
                        The batch's recorded average weight is loaded
                        automatically. Confirm or adjust it below.
                    </div>
                </div>

                <!-- AVERAGE FISH WEIGHT -->
                <div class="mb-4">
                    <label for="avg_weight_g" class="form-label fw-semibold">
                        Average Fish Weight (g)
                    </label>

                    <input
                        type="number"
                        name="avg_weight_g"
                        id="avg_weight_g"
                        class="form-control"
                        min="10"
                        step="0.01"
                        required
                        placeholder="Enter average weight in grams"
                    >

                    <div class="form-text" id="weightHelp">
                        Select a batch to load its recorded average weight.
                    </div>
                </div>

                <!-- QUANTITY -->
                <div class="mb-4">
                    <label for="qty" class="form-label fw-semibold">
                        Quantity to Stock
                    </label>

                    <input
                        type="number"
                        name="quantity"
                        id="qty"
                        class="form-control"
                        min="1"
                        step="1"
                        required
                        placeholder="Enter number of fish"
                    >

                    <div class="form-text" id="quantityHelp">
                        Select a pond and batch first.
                    </div>
                </div>

                <!-- CAPACITY AND STATUS INFORMATION -->
                <div
                    class="alert alert-info"
                    id="infoBox"
                    role="status"
                    aria-live="polite"
                >
                    Select a pond and fish batch to view capacity.
                </div>

                <!-- VALIDATION MESSAGE -->
                <div
                    class="alert alert-danger d-none"
                    id="validationBox"
                    role="alert"
                ></div>

                <div class="d-flex gap-2 flex-wrap">
                    <button
                        type="submit"
                        id="submitBtn"
                        class="btn btn-primary"
                        <?= (empty($ponds) || empty($batches))
                            ? 'disabled'
                            : '' ?>
                    >
                        Stock Fish
                    </button>

                    <a href="index.php" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
'use strict';

const pondEl = document.getElementById('pond');
const batchEl = document.getElementById('batch');
const avgWeightEl = document.getElementById('avg_weight_g');
const qtyEl = document.getElementById('qty');

const weightHelp = document.getElementById('weightHelp');
const quantityHelp = document.getElementById('quantityHelp');
const infoBox = document.getElementById('infoBox');
const validationBox = document.getElementById('validationBox');
const submitBtn = document.getElementById('submitBtn');
const stockingForm = document.getElementById('stockingForm');

function numberValue(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

function classifyStage(weight) {
    if (weight === null || weight < 10) {
        return null;
    }

    return weight < 100 ? 'juvenile' : 'grow_out';
}

function getTarpaulinGrowOutLimit(pond) {
    const length = numberValue(pond.dataset.length);
    const width = numberValue(pond.dataset.width);

    if (
        length !== null &&
        width !== null &&
        Math.abs(length - 20) < 0.01 &&
        Math.abs(width - 20) < 0.01
    ) {
        return 1200;
    }

    if (
        length !== null &&
        width !== null &&
        Math.abs(length - 12) < 0.01 &&
        Math.abs(width - 12) < 0.01
    ) {
        return 500;
    }

    let label = String(pond.dataset.sizeLabel || '')
        .toLowerCase()
        .replace(/[×*]/g, 'x')
        .replace(/\s+/g, '');

    if (/20(?:ft|feet)?x20(?:ft|feet)?/.test(label)) {
        return 1200;
    }

    if (/12(?:ft|feet)?x12(?:ft|feet)?/.test(label)) {
        return 500;
    }

    return null;
}

function getEffectiveCapacity(pond, stage) {
    const type = String(pond.dataset.type || '').toLowerCase();
    const databaseCapacity = numberValue(pond.dataset.capacity);

    if (databaseCapacity === null || databaseCapacity < 1) {
        return {
            max: null,
            stageLimit: null,
            error: 'This pond has no valid database capacity.'
        };
    }

    let stageLimit;

    if (type === 'tank') {
        stageLimit = stage === 'juvenile' ? 500 : 100;
    } else if (type === 'tarpaulin') {
        if (stage === 'juvenile') {
            stageLimit = 5000;
        } else {
            stageLimit = getTarpaulinGrowOutLimit(pond);

            if (stageLimit === null) {
                return {
                    max: null,
                    stageLimit: null,
                    error:
                        'Tarpaulin grow-out size is unknown. ' +
                        'Configure the pond as 20ft x 20ft or 12ft x 12ft.'
                };
            }
        }
    } else {
        return {
            max: null,
            stageLimit: null,
            error: 'Unsupported pond type.'
        };
    }

    return {
        max: Math.min(Math.floor(databaseCapacity), stageLimit),
        stageLimit: stageLimit,
        error: null
    };
}

function getMinimumQuantity(pond, stage) {
    if (stage !== 'juvenile') {
        return 1;
    }

    const type = String(pond.dataset.type || '').toLowerCase();

    if (type === 'tank') {
        return 200;
    }

    if (type === 'tarpaulin') {
        return 400;
    }

    return 1;
}

function getPondStatus(current, capacity) {
    if (current <= 0) {
        return {
            label: 'Empty',
            badge: 'bg-success'
        };
    }

    if (current >= capacity) {
        return {
            label: 'Filled',
            badge: 'bg-danger'
        };
    }

    return {
        label: 'Partially Filled',
        badge: 'bg-warning text-dark'
    };
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function(char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function showError(message) {
    validationBox.textContent = message;
    validationBox.classList.remove('d-none');
}

function clearError() {
    validationBox.textContent = '';
    validationBox.classList.add('d-none');
}

function resetQuantity() {
    qtyEl.value = '';
    qtyEl.removeAttribute('max');
    qtyEl.min = '1';
    qtyEl.setCustomValidity('');
    quantityHelp.textContent = 'Select a pond and batch first.';
}

function updatePondOptionLabels(stage) {
    Array.from(pondEl.options).forEach(function(option) {
        if (!option.value) {
            return;
        }

        const current = numberValue(option.dataset.current) ?? 0;
        let capacity = numberValue(option.dataset.capacity) ?? 0;
        let status;

        if (stage) {
            const result = getEffectiveCapacity(option, stage);

            if (result.max !== null) {
                capacity = result.max;
            } else {
                status = 'Capacity unknown';
            }
        }

        if (!status) {
            if (current === 0) {
                status = 'Empty';
            } else if (capacity > 0 && current >= capacity) {
                status = 'Filled';
            } else {
                status = 'Partially Filled';
            }
        }

        option.textContent =
            option.dataset.baseLabel +
            ' | ' + status +
            ' | Stock: ' + current.toLocaleString() +
            ' / ' + capacity.toLocaleString();
    });
}

function refreshInfo() {
    clearError();
    resetQuantity();

    const pond = pondEl.selectedOptions[0];
    const batch = batchEl.selectedOptions[0];
    const weight = numberValue(avgWeightEl.value);

    if (!pond || !pond.value || !batch || !batch.value) {
        infoBox.className = 'alert alert-info';
        infoBox.textContent =
            'Select a pond and fish batch to view stocking capacity.';

        updatePondOptionLabels(null);
        submitBtn.disabled =
            !pondEl.value || !batchEl.value;
        return;
    }

    const stage = classifyStage(weight);
    const current = numberValue(pond.dataset.current);
    const available = numberValue(batch.dataset.available);

    if (stage === null) {
        infoBox.className = 'alert alert-warning';
        infoBox.textContent =
            'Enter a valid average fish weight of at least 10 g.';

        updatePondOptionLabels(null);
        submitBtn.disabled = true;
        return;
    }

    if (
        current === null ||
        current < 0 ||
        available === null ||
        available < 1
    ) {
        infoBox.className = 'alert alert-warning';
        infoBox.textContent =
            'Could not validate current pond stock or batch availability.';

        submitBtn.disabled = true;
        return;
    }

    const capacityResult = getEffectiveCapacity(pond, stage);

    if (capacityResult.max === null) {
        infoBox.className = 'alert alert-warning';
        infoBox.textContent = capacityResult.error;

        showError(capacityResult.error);
        updatePondOptionLabels(stage);
        submitBtn.disabled = true;
        return;
    }

    const effectiveCapacity = capacityResult.max;
    const remaining = Math.max(0, effectiveCapacity - current);
    const maximum = Math.min(remaining, available);
    const minimum = getMinimumQuantity(pond, stage);
    const pondStatus = getPondStatus(current, effectiveCapacity);

    const stageLabel = stage === 'juvenile'
        ? 'Juvenile'
        : 'Grow-out';

    const sizeLabel = pond.dataset.sizeLabel ||
        (
            pond.dataset.length && pond.dataset.width
                ? pond.dataset.length + 'ft × ' + pond.dataset.width + 'ft'
                : 'Not specified'
        );

    qtyEl.min = String(minimum);
    qtyEl.max = String(maximum);

    quantityHelp.textContent =
        'Allowed quantity: ' +
        minimum.toLocaleString() +
        ' to ' +
        maximum.toLocaleString() +
        ' fish.';

    let warning = '';

    if (maximum < minimum) {
        warning =
            '<div class="mt-3 text-danger">' +
            '<strong>Insufficient capacity or batch inventory.</strong> ' +
            'The available quantity is below the minimum of ' +
            minimum.toLocaleString() + ' fish.' +
            '</div>';
    }

    infoBox.className = 'alert alert-info';

    infoBox.innerHTML = `
        <div class="fw-bold mb-3">Pond Status and Stocking Capacity</div>

        <div class="row g-3">
            <div class="col-md-6">
                <strong>Pond:</strong>
                ${escapeHtml(pond.dataset.code)}
            </div>

            <div class="col-md-6">
                <strong>Pond Type:</strong>
                ${escapeHtml(pond.dataset.type)}
            </div>

            <div class="col-md-6">
                <strong>Pond Size:</strong>
                ${escapeHtml(sizeLabel)}
            </div>

            <div class="col-md-6">
                <strong>Pond Status:</strong>
                <span class="badge ${pondStatus.badge}">
                    ${pondStatus.label}
                </span>
            </div>

            <div class="col-md-6">
                <strong>Fish Stage:</strong>
                ${stageLabel}
            </div>

            <div class="col-md-6">
                <strong>Average Weight:</strong>
                ${weight.toLocaleString()} g
            </div>

            <div class="col-md-6">
                <strong>Stage Limit:</strong>
                ${capacityResult.stageLimit.toLocaleString()} fish
            </div>

            <div class="col-md-6">
                <strong>Effective Capacity:</strong>
                ${effectiveCapacity.toLocaleString()} fish
            </div>

            <div class="col-md-6">
                <strong>Current Stock:</strong>
                ${current.toLocaleString()} fish
            </div>

            <div class="col-md-6">
                <strong>Remaining Capacity:</strong>
                ${remaining.toLocaleString()} fish
            </div>

            <div class="col-md-6">
                <strong>Batch Available:</strong>
                ${available.toLocaleString()} fish
            </div>

            <div class="col-md-6">
                <strong>Maximum Stockable Now:</strong>
                ${maximum.toLocaleString()} fish
            </div>
        </div>

        ${warning}
    `;

    updatePondOptionLabels(stage);

    if (maximum < minimum) {
        showError(
            'This pond cannot accept the minimum stocking quantity for the selected batch.'
        );
        submitBtn.disabled = true;
        return;
    }

    submitBtn.disabled = false;
}

/*
|--------------------------------------------------------------------------
| Load the batch weight into the editable input
|--------------------------------------------------------------------------
*/
batchEl.addEventListener('change', function() {
    const batch = batchEl.selectedOptions[0];

    if (batch && batch.value) {
        const recordedWeight = numberValue(batch.dataset.weight);

        avgWeightEl.value =
            recordedWeight !== null && recordedWeight >= 10
                ? recordedWeight
                : '';

        weightHelp.textContent =
            'Recorded batch average: ' +
            (recordedWeight !== null ? recordedWeight + ' g' : 'unavailable') +
            '. Confirm or adjust the actual average weight.';
    } else {
        avgWeightEl.value = '';
        weightHelp.textContent =
            'Select a batch to load its recorded average weight.';
    }

    refreshInfo();
});

pondEl.addEventListener('change', refreshInfo);
avgWeightEl.addEventListener('input', refreshInfo);

/*
|--------------------------------------------------------------------------
| Quantity input validation
|--------------------------------------------------------------------------
*/
qtyEl.addEventListener('input', function() {
    clearError();

    const pond = pondEl.selectedOptions[0];
    const batch = batchEl.selectedOptions[0];
    const weight = numberValue(avgWeightEl.value);

    if (!pond || !pond.value || !batch || !batch.value) {
        return;
    }

    const stage = classifyStage(weight);
    const current = numberValue(pond.dataset.current);
    const available = numberValue(batch.dataset.available);

    if (stage === null || current === null || available === null) {
        return;
    }

    const result = getEffectiveCapacity(pond, stage);

    if (result.max === null) {
        return;
    }

    const maximum = Math.min(
        Math.max(0, result.max - current),
        available
    );

    const minimum = getMinimumQuantity(pond, stage);
    const quantity = Number(qtyEl.value);

    if (qtyEl.value !== '' && quantity > maximum) {
        qtyEl.value = String(maximum);

        showError(
            'Quantity adjusted to the maximum permitted amount of ' +
            maximum.toLocaleString() + ' fish.'
        );
    }

    if (qtyEl.value !== '' && quantity > 0 && quantity < minimum) {
        qtyEl.setCustomValidity(
            'Minimum stocking quantity is ' + minimum + ' fish.'
        );
    } else {
        qtyEl.setCustomValidity('');
    }
});

/*
|--------------------------------------------------------------------------
| Final client-side check
|--------------------------------------------------------------------------
*/
stockingForm.addEventListener('submit', function(event) {
    clearError();

    const pond = pondEl.selectedOptions[0];
    const batch = batchEl.selectedOptions[0];
    const weight = numberValue(avgWeightEl.value);
    const quantity = Number(qtyEl.value);

    if (!pond || !pond.value || !batch || !batch.value) {
        event.preventDefault();
        showError('Select both a pond and a fish batch.');
        return;
    }

    const stage = classifyStage(weight);

    if (stage === null) {
        event.preventDefault();
        showError('Average fish weight must be at least 10 g.');
        return;
    }

    const current = numberValue(pond.dataset.current);
    const available = numberValue(batch.dataset.available);
    const result = getEffectiveCapacity(pond, stage);

    if (result.max === null) {
        event.preventDefault();
        showError(result.error);
        return;
    }

    const maximum = Math.min(
        Math.max(0, result.max - current),
        available
    );

    const minimum = getMinimumQuantity(pond, stage);

    if (
        !Number.isInteger(quantity) ||
        quantity < minimum ||
        quantity > maximum
    ) {
        event.preventDefault();

        showError(
            'Quantity must be a whole number between ' +
            minimum.toLocaleString() + ' and ' +
            maximum.toLocaleString() + ' fish.'
        );
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';
});

/*
|--------------------------------------------------------------------------
| Initial state
|--------------------------------------------------------------------------
*/
refreshInfo();
</script>
