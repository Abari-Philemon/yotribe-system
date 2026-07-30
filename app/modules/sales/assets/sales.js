/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * sales.js
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', () => {

    /*
    |--------------------------------------------------------------------------
    | DOM Elements
    |--------------------------------------------------------------------------
    */

    const harvestSelect   = document.getElementById('harvest_id');
    const addItemBtn      = document.getElementById('addItem');

    const inventoryBody   = document.getElementById('harvestInventory');
    const saleItemsBody   = document.getElementById('saleItems');

    const subtotalInput   = document.getElementById('subtotal');
    const discountInput   = document.getElementById('discount');
    const grandTotalInput = document.getElementById('grand_total');
    const amountPaidInput = document.getElementById('amount_paid');
    const balanceInput    = document.getElementById('balance');

    /*
    |--------------------------------------------------------------------------
    | Global Variables
    |--------------------------------------------------------------------------
    */

    let inventory = [];

    if (addItemBtn) {
        addItemBtn.disabled = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Harvest Changed
    |--------------------------------------------------------------------------
    */

    harvestSelect?.addEventListener('change', function () {

        console.log("Harvest changed:", this.value);

        const harvestId = this.value;

        saleItemsBody.innerHTML = '';
        inventory = [];
        calculateTotals();

        if (!harvestId) {
            return;
        }

        loadHarvestInventory(harvestId);
    });

    /*
    |--------------------------------------------------------------------------
    | Load Harvest Inventory
    |--------------------------------------------------------------------------
    */

    async function loadHarvestInventory(harvestId) {

        inventoryBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center">
                    Loading inventory...
                </td>
            </tr>
        `;

        addItemBtn.disabled = true;

        try {

            const response = await fetch(`/yotribe-system/app/modules/sales/ajax/get_harvest_inventory.php?harvest_id=${harvestId}`
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (!result.success) {

                inventoryBody.innerHTML = `
                    <tr>
                        <td colspan="6"
                            class="text-center text-danger">
                            ${result.message}
                        </td>
                    </tr>
                `;

                inventory = [];

                addItemBtn.disabled = true;

                return;

            }

            inventory = result.data || [];

            renderInventory();

            addItemBtn.disabled = inventory.length === 0;

        } catch (error) {

            console.error(
                "Inventory Load Error:",
                error
            );

            inventoryBody.innerHTML = `
                <tr>
                    <td colspan="6"
                        class="text-center text-danger">

                        Unable to load harvest inventory.

                    </td>
                </tr>
            `;

            inventory = [];

            addItemBtn.disabled = true;

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Render Harvest Inventory
    |--------------------------------------------------------------------------
    */

    function renderInventory() {

        if (inventory.length === 0) {

            inventoryBody.innerHTML = `
                <tr>
                    <td colspan="6"
                        class="text-center">

                        No inventory available.

                    </td>
                </tr>
            `;

            return;

        }

        inventoryBody.innerHTML = inventory.map(item => `

            <tr>

                <td>${item.pond_code}</td>

                <td class="text-end">

                    ${Number(item.harvested_fish).toLocaleString()}

                </td>

                <td class="text-end">

                    ${Number(item.available_fish).toLocaleString()}

                </td>

                <td class="text-end">

                    ${Number(item.harvest_weight).toFixed(2)}

                </td>

                <td class="text-end">

                    ${Number(item.available_weight).toFixed(2)}

                </td>

                <td>

                    ${item.status}

                </td>

            </tr>

        `).join('');

    }

    /*
    |--------------------------------------------------------------------------
    | Add Sale Item
    |--------------------------------------------------------------------------
    */

    addItemBtn?.addEventListener('click', () => {

        if (inventory.length === 0) {

            alert("No harvest inventory available.");

            return;

        }

        saleItemsBody.insertAdjacentHTML(
            'beforeend',
            buildRow()
        );

    });

    /*
     * ===== Part 2 continues here =====
     */
        /*
    |--------------------------------------------------------------------------
    | Build Sale Row
    |--------------------------------------------------------------------------
    */

    function buildRow() {

        const options = inventory.map(item => `

            <option
                value="${item.harvest_pond_id}"
                data-pond-stocking-id="${item.pond_stocking_id}"
                data-available="${item.available_fish}"
                data-weight="${item.available_weight}"
                data-average="${item.average_weight}">
                ${item.pond_code}
            </option>

        `).join('');

        return `

        <tr>

            <td>

                <select
                    name="harvest_pond_id[]"
                    class="form-select pond"
                    required>

                    <option value="">
                        Select Pond
                    </option>

                    ${options}

                </select>

            </td>

            <td>

                <input
                    type="number"
                    class="form-control available text-end"
                    value="0"
                    readonly>

            </td>

            <td>

                <input
                    type="number"
                    name="quantity_fish[]"
                    class="form-control sold text-end"
                    min="0">

            </td>

            <td>

                <input
                    type="number"
                    step="0.01"
                    name="quantity_kg[]"
                    class="form-control weight text-end"
                    min="0">

            </td>

            <td>

                <input
                    type="number"
                    step="0.01"
                    name="unit_price[]"
                    class="form-control price text-end"
                    min="0">

            </td>

            <td>

                <input
                    type="number"
                    class="form-control total text-end"
                    value="0.00"
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

    /*
    |--------------------------------------------------------------------------
    | Sale Item Events
    |--------------------------------------------------------------------------
    */

    saleItemsBody.addEventListener('change', e => {

        const row = e.target.closest('tr');

        if (!row) return;

        /*
        ------------------------------------------------------------
        Pond Selected
        ------------------------------------------------------------
        */

        if (e.target.classList.contains('pond')) {

            const selectedValue = e.target.value;

            /*
            --------------------------------------------------------
            Prevent duplicate pond selection
            --------------------------------------------------------
            */

            const ponds = Array.from(
                document.querySelectorAll('.pond')
            );

            const duplicates = ponds.filter(
                p => p.value === selectedValue && selectedValue !== ''
            );

            if (duplicates.length > 1) {

                alert(
                    "This pond has already been selected."
                );

                e.target.value = '';

                row.querySelector('.available').value = 0;

                return;

            }

            const option = e.target.selectedOptions[0];

            row.querySelector('.available').value =
                option.dataset.available || 0;

        }

        calculateRow(row);

    });

    /*
    |--------------------------------------------------------------------------
    | Input Events
    |--------------------------------------------------------------------------
    */

    saleItemsBody.addEventListener('input', e => {

        const row = e.target.closest('tr');

        if (!row) return;

        /*
        ------------------------------------------------------------
        Validate Fish Sold
        ------------------------------------------------------------
        */

        if (e.target.classList.contains('sold')) {

            const sold = parseFloat(
                row.querySelector('.sold').value
            ) || 0;

            const available = parseFloat(
                row.querySelector('.available').value
            ) || 0;

            if (sold > available) {

                alert(
                    "Fish sold cannot exceed available fish."
                );

                row.querySelector('.sold').value = available;

            }

            /*
            --------------------------------------------------------
            Auto Weight (optional)
            --------------------------------------------------------
            */

            const pond = row.querySelector('.pond');

            const option =
                pond.selectedOptions[0];

            const average =
                parseFloat(option.dataset.average) || 0;

            if (average > 0) {

                row.querySelector('.weight').value =
                    ((available === 0)
                        ? 0
                        : (sold * average)).toFixed(2);

            }

        }

        calculateRow(row);

    });

    /*
    |--------------------------------------------------------------------------
    | Remove Row
    |--------------------------------------------------------------------------
    */

    saleItemsBody.addEventListener('click', e => {

        if (!e.target.classList.contains('remove')) {

            return;

        }

        e.target.closest('tr').remove();

        calculateTotals();

    });

    /*
    |--------------------------------------------------------------------------
    | Calculate Row
    |--------------------------------------------------------------------------
    */

    function calculateRow(row) {

        const weight =
            parseFloat(
                row.querySelector('.weight').value
            ) || 0;

        const price =
            parseFloat(
                row.querySelector('.price').value
            ) || 0;

        const total = weight * price;

        row.querySelector('.total').value =
            total.toFixed(2);

        calculateTotals();

    }

    /*
     * ===== Part 3 continues here =====
     */
        /*
    |--------------------------------------------------------------------------
    | Calculate Totals
    |--------------------------------------------------------------------------
    */

    function calculateTotals() {

        let subtotal = 0;

        document.querySelectorAll('.total').forEach(input => {

            subtotal += parseFloat(input.value) || 0;

        });

        const discount =
            parseFloat(discountInput?.value) || 0;

        const grandTotal =
            Math.max(subtotal - discount, 0);

        const amountPaid =
            parseFloat(amountPaidInput?.value) || 0;

        const balance =
            grandTotal - amountPaid;

        if (subtotalInput) {
            subtotalInput.value = subtotal.toFixed(2);
        }

        if (grandTotalInput) {
            grandTotalInput.value = grandTotal.toFixed(2);
        }

        if (balanceInput) {
            balanceInput.value = balance.toFixed(2);
        }

    }

    /*
    |--------------------------------------------------------------------------
    | Payment Events
    |--------------------------------------------------------------------------
    */

    discountInput?.addEventListener('input', () => {

        calculateTotals();

    });

    amountPaidInput?.addEventListener('input', () => {

        calculateTotals();

    });

    /*
    |--------------------------------------------------------------------------
    | Form Validation
    |--------------------------------------------------------------------------
    */

    const salesForm = document.getElementById('salesForm');

    salesForm?.addEventListener('submit', function (e) {

        if (saleItemsBody.children.length === 0) {

            e.preventDefault();

            alert('Please add at least one sale item.');

            return;

        }

        let valid = true;

        document.querySelectorAll('#saleItems tr').forEach(row => {

            const pond = row.querySelector('.pond')?.value || '';

            const sold = parseFloat(
                row.querySelector('.sold')?.value
            ) || 0;

            const weight = parseFloat(
                row.querySelector('.weight')?.value
            ) || 0;

            const price = parseFloat(
                row.querySelector('.price')?.value
            ) || 0;

            if (
                pond === '' ||
                sold <= 0 ||
                weight <= 0 ||
                price <= 0
            ) {
                valid = false;
            }

        });

        if (!valid) {

            e.preventDefault();

            alert(
                'Please complete every sale item before saving.'
            );

        }

    });

    /*
    |--------------------------------------------------------------------------
    | Reset Form
    |--------------------------------------------------------------------------
    */

    function resetSaleItems() {

        saleItemsBody.innerHTML = '';

        calculateTotals();

    }

    /*
    |--------------------------------------------------------------------------
    | Initialize Page
    |--------------------------------------------------------------------------
    */

    calculateTotals();

    if (!harvestSelect?.value) {

        addItemBtn.disabled = true;

    }

});