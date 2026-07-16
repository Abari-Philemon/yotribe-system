/**
 * ==========================================================
 * YOTRIBE IFMS
 * Harvest Management
 * harvest.js
 * ==========================================================
 */

document.addEventListener('DOMContentLoaded', () => {

    const batchSelect = document.getElementById('fish_batch_id');

    const pondTable = document.getElementById('pondTable');

    const form = document.getElementById('harvestForm');

    /*
    ==========================================================
    Batch Information
    ==========================================================
    */

    const infoSpecies = document.getElementById('info_species');

    const infoSource = document.getElementById('info_source');

    const infoCurrent = document.getElementById('info_current');

    const infoStocking = document.getElementById('info_stocking');

    /*
    ==========================================================
    Batch Changed
    ==========================================================
    */

    batchSelect.addEventListener('change', function () {

        const option = this.options[this.selectedIndex];

        if (!this.value) {

            clearBatchInfo();

            clearPonds();

            return;

        }

        /*
        -----------------------------------------
        Display Batch Information
        -----------------------------------------
        */

        infoSpecies.value = option.dataset.species;

        infoSource.value = option.dataset.source;

        infoCurrent.value = Number(
            option.dataset.current
        ).toLocaleString();

        infoStocking.value = option.dataset.stocking;

        /*
        -----------------------------------------
        Load Participating Ponds
        -----------------------------------------
        */

        loadPonds(this.value);

    });

    /*
    ==========================================================
    AJAX
    ==========================================================
    */

    function loadPonds(batchId) {

        pondTable.innerHTML = `
            <tr>

                <td colspan="6" class="text-center">

                    Loading ponds...

                </td>

            </tr>
        `;

        const formData = new FormData();

        formData.append('fish_batch_id', batchId);

        fetch('ajax/get_batch_ponds.php', {

            method: 'POST',

            body: formData

        })

        .then(response => response.json())

        .then(response => {

            if (!response.success) {

                pondTable.innerHTML = `

                    <tr>

                        <td colspan="6"
                            class="text-danger text-center">

                            ${response.message}

                        </td>

                    </tr>

                `;

                return;

            }

            renderPonds(response.data);

        })

        .catch(error => {

            console.error(error);

            pondTable.innerHTML = `

                <tr>

                    <td colspan="6"
                        class="text-danger text-center">

                        Unable to load ponds.

                    </td>

                </tr>

            `;

        });

    }
        /*
    ==========================================================
    Render Participating Ponds
    ==========================================================
    */

    function renderPonds(ponds) {

        if (!ponds.length) {

            pondTable.innerHTML = `

                <tr>

                    <td colspan="6"
                        class="text-center text-warning">

                        No ponds found for this batch.

                    </td>

                </tr>

            `;

            return;

        }

        let html = '';

        ponds.forEach((pond, index) => {

            html += `

            <tr>

                <td>

                    ${pond.pond_code}

                    <input
                        type="hidden"
                        name="pond_stocking_id[]"
                        value="${pond.pond_stocking_id}">

                    <input
                        type="hidden"
                        name="pond_id[]"
                        value="${pond.pond_id}">

                    <input
                        type="hidden"
                        name="batch_id[]"
                        value="${pond.batch_id}">

                </td>

                <td class="text-end">

                    ${Number(pond.current_count).toLocaleString()}

                </td>

                <td>

                    <input

                        type="time"

                        name="harvest_start[]"

                        class="form-control"

                        required>

                </td>

                <td>

                    <input

                        type="time"

                        name="harvest_end[]"

                        class="form-control"

                        required>

                </td>

                <td>

                    <span class="badge bg-success">

                        Ready

                    </span>

                </td>

                <td>

                    <input

                        type="text"

                        name="pond_remarks[]"

                        class="form-control"

                        maxlength="255"

                        placeholder="Optional">

                </td>

            </tr>

            `;

        });

        pondTable.innerHTML = html;

    }



    /*
    ==========================================================
    Clear Batch Information
    ==========================================================
    */

    function clearBatchInfo() {

        infoSpecies.value = '';

        infoSource.value = '';

        infoCurrent.value = '';

        infoStocking.value = '';

    }



    /*
    ==========================================================
    Clear Pond Table
    ==========================================================
    */

    function clearPonds() {

        pondTable.innerHTML = `

            <tr>

                <td colspan="6"
                    class="text-center text-muted py-4">

                    Select a Fish Batch to load
                    participating ponds.

                </td>

            </tr>

        `;

    }
        /*
    ==========================================================
    Form Validation
    ==========================================================
    */

    form.addEventListener('submit', function (e) {

        /*
        -----------------------------------------
        Batch Selected?
        -----------------------------------------
        */

        if (!batchSelect.value) {

            e.preventDefault();

            alert('Please select a Fish Batch.');

            batchSelect.focus();

            return;

        }

        /*
        -----------------------------------------
        Ponds Loaded?
        -----------------------------------------
        */

        const pondRows = pondTable.querySelectorAll('tr');

        if (pondRows.length === 0 ||
            pondRows[0].querySelector('td[colspan]')) {

            e.preventDefault();

            alert('No participating ponds were loaded.');

            return;

        }

        /*
        -----------------------------------------
        Validate Harvest Times
        -----------------------------------------
        */

        const startTimes = document.querySelectorAll(
            'input[name="harvest_start[]"]'
        );

        const endTimes = document.querySelectorAll(
            'input[name="harvest_end[]"]'
        );

        for (let i = 0; i < startTimes.length; i++) {

            const start = startTimes[i].value;

            const end = endTimes[i].value;

            if (!start || !end) {

                e.preventDefault();

                alert(
                    'Please enter both Harvest Start and Harvest End times.'
                );

                return;

            }

            if (end <= start) {

                e.preventDefault();

                alert(
                    'Harvest End time must be later than Harvest Start time.'
                );

                endTimes[i].focus();

                return;

            }

        }

        /*
        -----------------------------------------
        Prevent Double Submission
        -----------------------------------------
        */

        const submitButton = form.querySelector(
            'button[type="submit"]'
        );

        submitButton.disabled = true;

        submitButton.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2"></span>
            Opening Harvest...
        `;

    });

});