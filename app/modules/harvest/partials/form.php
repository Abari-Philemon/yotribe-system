<form action="save.php" method="POST" id="harvestForm">

    <!-- ==========================================================
        SECURITY
    =========================================================== -->

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <input
        type="hidden"
        name="harvest_no"
        value="<?= htmlspecialchars($harvest_no ?? '') ?>">

    <!-- ==========================================================
        HARVEST INFORMATION
    =========================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-success text-white">

            <h5 class="mb-0">

                <i class="bi bi-box-seam"></i>

                Harvest Information

            </h5>

        </div>

        <div class="card-body">

            <div class="row">

                <!-- Harvest Number -->

                <div class="col-lg-4 mb-3">

                    <label class="form-label">

                        Harvest Number

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars($harvest_no ?? '') ?>"
                        readonly>

                </div>

                <!-- Harvest Date -->

                <div class="col-lg-4 mb-3">

                    <label class="form-label">

                        Harvest Date

                    </label>

                    <input

                        type="date"

                        name="harvest_date"

                        class="form-control"

                        value="<?= date('Y-m-d') ?>"

                        max="<?= date('Y-m-d') ?>"

                        required>

                </div>

                <!-- Batch -->

                <div class="col-lg-4 mb-3">

                    <label class="form-label">

                        Fish Batch

                    </label>

                    <select

                        name="fish_batch_id"

                        id="fish_batch_id"

                        class="form-select"

                        required>

                        <option value="">

                            Select Fish Batch

                        </option>

                        <?php foreach ($fish_batches ?? [] as $batch): ?>

                            <option

                                value="<?= $batch['id'] ?>"

                                data-batch="<?= htmlspecialchars($batch['batch_code']) ?>"

                                data-species="<?= htmlspecialchars($batch['species']) ?>"

                                data-source="<?= htmlspecialchars($batch['source']) ?>"

                                data-current="<?= $batch['current_count'] ?>"

                                data-initial="<?= $batch['initial_count'] ?>"

                                data-stocking="<?= $batch['stocking_date'] ?>">

                                <?= htmlspecialchars($batch['batch_code']) ?>

                                |

                                <?= number_format($batch['current_count']) ?>

                                Fish

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

        </div>

    </div>



    <!-- ==========================================================
        BATCH INFORMATION
    =========================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-info text-white">

            <h5 class="mb-0">

                <i class="bi bi-info-circle"></i>

                Batch Information

            </h5>

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-3 mb-3">

                    <label class="form-label">

                        Species

                    </label>

                    <input

                        type="text"

                        id="info_species"

                        class="form-control"

                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label">

                        Source

                    </label>

                    <input

                        type="text"

                        id="info_source"

                        class="form-control"

                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label">

                        Current Fish

                    </label>

                    <input

                        type="text"

                        id="info_current"

                        class="form-control"

                        readonly>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label">

                        Stocking Date

                    </label>

                    <input

                        type="text"

                        id="info_stocking"

                        class="form-control"

                        readonly>

                </div>

            </div>

        </div>

    </div>
        <!-- ==========================================================
        PARTICIPATING PONDS
    =========================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            <div class="d-flex justify-content-between align-items-center">

                <h5 class="mb-0">

                    <i class="bi bi-water"></i>

                    Participating Ponds

                </h5>

                <span class="badge bg-light text-dark">

                    Auto Loaded

                </span>

            </div>

        </div>

        <div class="card-body">

            <div class="alert alert-info">

                <i class="bi bi-info-circle"></i>

                Select a Fish Batch to automatically load all
                ponds currently containing that batch.

            </div>

            <div class="table-responsive">

                <table
                    class="table table-bordered table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th width="15%">

                                Pond

                            </th>

                            <th width="15%">

                                Current Fish

                            </th>

                            <th width="15%">

                                Harvest Start

                            </th>

                            <th width="15%">

                                Harvest End

                            </th>

                            <th width="15%">

                                Status

                            </th>

                            <th width="25%">

                                Remarks

                            </th>

                        </tr>

                    </thead>

                    <tbody id="pondTable">

                        <tr>

                            <td colspan="6"
                                class="text-center text-muted py-4">

                                <i class="bi bi-arrow-up-circle"></i>

                                <br>

                                Select a Fish Batch to load
                                participating ponds.

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>



    <!-- ==========================================================
        HIDDEN DATA

        Generated Automatically
        by harvest.js

    =========================================================== -->

    <div id="hiddenInputs">

        <!--

        Example

        <input
            type="hidden"
            name="pond_stocking_id[]">

        <input
            type="hidden"
            name="pond_id[]">

        <input
            type="hidden"
            name="batch_id[]">

        -->

    </div>
        <!-- ==========================================================
        GENERAL REMARKS
    =========================================================== -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <h5 class="mb-0">

                <i class="bi bi-chat-left-text"></i>

                General Remarks

            </h5>

        </div>

        <div class="card-body">

            <textarea

                name="remarks"

                class="form-control"

                rows="4"

                maxlength="500"

                placeholder="Enter any additional remarks about this harvest (optional)..."></textarea>

            <div class="form-text">

                Maximum 500 characters.

            </div>

        </div>

    </div>



    <!-- ==========================================================
        ACTION BUTTONS
    =========================================================== -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <small class="text-muted">

                        Opening a harvest makes the selected batch
                        available for sales and other harvest movements.

                    </small>

                </div>

                <div>

                    <a href="history.php"
                       class="btn btn-outline-secondary me-2">

                        <i class="bi bi-x-circle"></i>

                        Cancel

                    </a>

                    <button
                        type="reset"
                        class="btn btn-warning me-2">

                        <i class="bi bi-arrow-clockwise"></i>

                        Reset

                    </button>

                    <button
                        type="submit"
                        class="btn btn-success">

                        <i class="bi bi-unlock"></i>

                        Open Harvest


                    </button>

                </div>

            </div>

        </div>

    </div>

</form>
  