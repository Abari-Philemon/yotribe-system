/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * sales.js
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', () => {

    const harvestSelect    = document.getElementById('harvest_id');
    const addItemBtn       = document.getElementById('addItem');
    const saleItemsBody    = document.getElementById('saleItems');
    const inventoryBody    = document.getElementById('harvestInventory');

    const subtotalInput    = document.getElementById('subtotal');
    const discountInput    = document.getElementById('discount');
    const grandTotalInput  = document.getElementById('grand_total');
    const amountPaidInput  = document.getElementById('amount_paid');
    const balanceInput     = document.getElementById('balance');

    let inventory = [];

    if (addItemBtn) {
        addItemBtn.disabled = true;
    }

    /**
     * ------------------------------------------------------------
     * Harvest Changed
     * ------------------------------------------------------------
     */
    harvestSelect?.addEventListener('change', function () {

        const harvestId = this.value;

        saleItemsBody.innerHTML = '';

        inventory = [];

        calculateTotals();

        if (!harvestId) {

            inventoryBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        Select a harvest to load inventory.
                    </td>
                </tr>
            `;

            addItemBtn.disabled = true;
            return;
        }

        loadHarvestInventory(harvestId);

    });

    /**
     * ------------------------------------------------------------
     * Load Harvest Inventory
     * ------------------------------------------------------------
     */
    async function loadHarvestInventory(harvestId) {

        inventoryBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center">
                    Loading...
                </td>
            </tr>
        `;

        try {

            const response = await fetch(
                `ajax/get_harvest_inventory.php?harvest_id=${harvestId}`
            );

            const result = await response.json();

            if (!result.success) {

                inventoryBody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-danger">
                            ${result.message}
                        </td>
                    </tr>
                `;

                return;
            }

            inventory = result.data;

            renderInventory();

            addItemBtn.disabled = inventory.length === 0;

        } catch (e) {

            console.error(e);

            inventoryBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger">
                        Unable to load inventory.
                    </td>
                </tr>
            `;
        }

    }

    /**
     * ------------------------------------------------------------
     * Inventory Table
     * ------------------------------------------------------------
     */
    function renderInventory() {

        if (inventory.length === 0) {

            inventoryBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">
                        No inventory available.
                    </td>
                </tr>
            `;

            return;
        }

        inventoryBody.innerHTML = inventory.map(item => `

            <tr>

                <td>${item.pond_code}</td>

                <td class="text-end">${Number(item.harvested_fish).toLocaleString()}</td>

                <td class="text-end">${Number(item.available_fish).toLocaleString()}</td>

                <td class="text-end">${Number(item.harvest_weight).toFixed(2)}</td>

                <td class="text-end">${Number(item.available_weight).toFixed(2)}</td>

                <td>${item.status}</td>

            </tr>

        `).join('');

    }

    /**
     * ------------------------------------------------------------
     * Add Item
     * ------------------------------------------------------------
     */
    addItemBtn?.addEventListener('click', () => {

        if (inventory.length === 0) {
            return;
        }

        saleItemsBody.insertAdjacentHTML(
            'beforeend',
            buildRow()
        );

    });

    /**
     * ------------------------------------------------------------
     * Build Row
     * ------------------------------------------------------------
     */
    function buildRow() {

        const options = inventory.map(item => `
            <option value="${item.pond_stocking_id}"
                    data-available="${item.available_fish}">
                ${item.pond_code}
            </option>
        `).join('');

        return `

        <tr>

            <td>

                <select
                    name="pond_stocking_id[]"
                    class="form-select pond">

                    <option value="">Select Pond</option>

                    ${options}

                </select>

            </td>

            <td>

                <input
                    type="number"
                    class="form-control available"
                    readonly>

            </td>

            <td>

                <input
                    type="number"
                    name="fish_sold[]"
                    class="form-control sold">

            </td>

            <td>

                <input
                    type="number"
                    step="0.01"
                    name="weight_kg[]"
                    class="form-control weight">

            </td>

            <td>

                <input
                    type="number"
                    step="0.01"
                    name="unit_price[]"
                    class="form-control price">

            </td>

            <td>

                <input
                    type="number"
                    class="form-control total"
                    readonly>

            </td>

            <td class="text-center">

                <button
                    type="button"
                    class="btn btn-sm btn-danger remove">

                    ×

                </button>

            </td>

        </tr>

        `;

    }

    /**
     * ------------------------------------------------------------
     * Row Events
     * ------------------------------------------------------------
     */
    saleItemsBody.addEventListener('change', e => {

        const row = e.target.closest('tr');

        if (!row) return;

        if (e.target.classList.contains('pond')) {

            const option = e.target.selectedOptions[0];

            row.querySelector('.available').value =
                option.dataset.available || 0;

        }

        calculateRow(row);

    });

    saleItemsBody.addEventListener('input', e => {

        const row = e.target.closest('tr');

        if (row) {
            calculateRow(row);
        }

    });

    saleItemsBody.addEventListener('click', e => {

        if (!e.target.classList.contains('remove')) {
            return;
        }

        e.target.closest('tr').remove();

        calculateTotals();

    });

    /**
     * ------------------------------------------------------------
     * Row Total
     * ------------------------------------------------------------
     */
    function calculateRow(row) {

        const weight =
            parseFloat(row.querySelector('.weight').value) || 0;

        const price =
            parseFloat(row.querySelector('.price').value) || 0;

        row.querySelector('.total').value =
            (weight * price).toFixed(2);

        calculateTotals();

    }

    /**
     * ------------------------------------------------------------
     * Totals
     * ------------------------------------------------------------
     */
    function calculateTotals() {

        let subtotal = 0;

        document.querySelectorAll('.total').forEach(input => {

            subtotal += parseFloat(input.value) || 0;

        });

        const discount =
            parseFloat(discountInput.value) || 0;

        const grand =
            subtotal - discount;

        const paid =
            parseFloat(amountPaidInput.value) || 0;

        subtotalInput.value = subtotal.toFixed(2);

        grandTotalInput.value = grand.toFixed(2);

        balanceInput.value = (grand - paid).toFixed(2);

    }

    discountInput?.addEventListener('input', calculateTotals);

    amountPaidInput?.addEventListener('input', calculateTotals);

});