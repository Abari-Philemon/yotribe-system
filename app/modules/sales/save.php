<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * Save Sale (Version 2)
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';
require_once __DIR__ . '/../../helpers/uuid_helper.php';

require_permission('sales');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: create.php');
    exit;

}

validate_csrf();

/*
|--------------------------------------------------------------------------
| Initialize
|--------------------------------------------------------------------------
*/

$farmId  = farm_id();
$staffId = $_SESSION['staff_id'];

/*
|--------------------------------------------------------------------------
| Sale Header
|--------------------------------------------------------------------------
*/

$saleNo          = trim($_POST['sale_no'] ?? '');
$saleDate        = trim($_POST['sale_date'] ?? '');
$harvestId       = (int)($_POST['harvest_id'] ?? 0);

$saleType        = trim($_POST['sale_type'] ?? 'customer_sale');

$customerName    = trim($_POST['customer_name'] ?? '');

$customerPhone   = trim($_POST['customer_phone'] ?? '');

$customerAddress = trim($_POST['customer_address'] ?? '');

$discount        = (float)($_POST['discount'] ?? 0);

$amountPaid      = (float)($_POST['amount_paid'] ?? 0);

$paymentMethod   = trim($_POST['payment_method'] ?? 'cash');

$referenceNo     = trim($_POST['reference_no'] ?? '');

$remarks         = trim($_POST['remarks'] ?? '');

/*
|--------------------------------------------------------------------------
| Sale Item Arrays
|--------------------------------------------------------------------------
|
| sales.js submits parallel arrays.
|
*/

$harvestPondIds = $_POST['harvest_pond_id'] ?? [];

$quantityFish   = $_POST['quantity_fish'] ?? [];

$quantityKg     = $_POST['quantity_kg'] ?? [];

$unitPrices     = $_POST['unit_price'] ?? [];

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

$errors = [];

if ($saleNo === '') {

    $errors[] = 'Sale number is required.';

}

if ($saleDate === '') {

    $errors[] = 'Sale date is required.';

}

if ($harvestId <= 0) {

    $errors[] = 'Please select a harvest.';

}

if (empty($harvestPondIds)) {

    $errors[] = 'Please add at least one sale item.';

}

if (

    count($harvestPondIds) !== count($quantityFish)

    ||

    count($quantityFish) !== count($quantityKg)

    ||

    count($quantityKg) !== count($unitPrices)

) {

    $errors[] = 'Sale item arrays are inconsistent.';

}

if (!empty($errors)) {

    $_SESSION['error'] = implode('<br>', $errors);

    header('Location: create.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Begin Transaction
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();

try {

    $validatedItems = [];

    $subtotal = 0;

    /*
     * ===== Part 2 continues here =====
     */
        /*
    |--------------------------------------------------------------------------
    | Verify Harvest
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            harvest_no,
            status,
            is_open
        FROM harvests
        WHERE
            id = ?
        AND farm_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $harvestId,
        $farmId
    ]);

    $harvest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$harvest) {

        throw new Exception('Harvest record not found.');

    }

    if ((int)$harvest['is_open'] !== 1) {

        throw new Exception(
            'This harvest has already been closed.'
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Validate Sale Items
    |--------------------------------------------------------------------------
    */

    foreach ($harvestPondIds as $index => $harvestPondId) {

        $harvestPondId = (int)$harvestPondId;

        $fish = (int)($quantityFish[$index] ?? 0);

        $weight = (float)($quantityKg[$index] ?? 0);

        $price = (float)($unitPrices[$index] ?? 0);

        if (
            $harvestPondId <= 0 ||
            $fish <= 0 ||
            $weight <= 0 ||
            $price < 0
        ) {

            throw new Exception(
                'Invalid sale item on row ' . ($index + 1)
            );

        }

                /*
                |--------------------------------------------------------------------------
                | Load Harvest Inventory
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("

                    SELECT

                        hp.id AS harvest_pond_id,

                        hp.pond_stocking_id,

                        hp.harvested_count,

                        hp.available_count,

                        hp.average_weight_g,

                        hp.harvested_weight_kg,

                        hp.available_weight_kg,

                        hp.inventory_status

                    FROM harvest_ponds hp

                    WHERE

                        hp.id = ?

                    AND hp.harvest_id = ?

                    LIMIT 1

                ");

                $stmt->execute([

                    $harvestPondId,

                    $harvestId

                ]);

                $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$inventory) {

                    throw new Exception(
                        'Harvest inventory not found.'
                    );

                }

                /*
                |--------------------------------------------------------------------------
                | Calculate Available Fish
                |--------------------------------------------------------------------------
                */



                $availableFish = (int)$inventory['available_count'];

                

                /*
                |--------------------------------------------------------------------------
                | Prevent Overselling
                |--------------------------------------------------------------------------
                */

                if ($fish > $availableFish) {

                    throw new Exception(

                        "Sale quantity exceeds available inventory on row "

                        . ($index + 1)

                    );

                }

                /*
                |--------------------------------------------------------------------------
                | Calculate Totals
                |--------------------------------------------------------------------------
                */

                $lineTotal = round(

                    $weight * $price,

                    2

                );

                $subtotal += $lineTotal;

                $validatedItems[] = [

                    'harvest_pond_id' => $harvestPondId,

                    'quantity_fish' => $fish,

                    'quantity_kg' => $weight,

                    'average_weight_kg' => round(

                        $weight / $fish,

                        3

                    ),

                    'unit_price' => $price,

                    'line_total' => $lineTotal

                ];

            }

            /*
            |--------------------------------------------------------------------------
            | Calculate Sale Totals
            |--------------------------------------------------------------------------
            */

            $discount = max(0, $discount);

            $subtotal = round($subtotal, 2);

            $grandTotal = max(

                0,

                round($subtotal - $discount, 2)

            );

            $amountPaid = max(0, $amountPaid);

            $balance = round(

                $grandTotal - $amountPaid,

                2

            );

            /*
            * ===== Part 3 continues here =====
            */
                /*
            |--------------------------------------------------------------------------
            | Generate Sale UUID
            |--------------------------------------------------------------------------
            */

            $saleUuid = generateUuid();

            /*
            |--------------------------------------------------------------------------
            | Sale Status
            |--------------------------------------------------------------------------
            */

            $saleStatus = ($balance <= 0)
                ? 'completed'
                : 'partial';

            /*
            |--------------------------------------------------------------------------
            | Insert Sale Header
            |--------------------------------------------------------------------------
            */

            $stmtSale = $pdo->prepare("

                INSERT INTO sales (

                    uuid,

                    farm_id,

                    harvest_id,

                    sale_no,

                    sale_date,

                    customer_name,

                    customer_phone,

                    customer_address,

                    sale_type,

                    status,

                    subtotal,

                    discount,

                    total_amount,

                    amount_paid,

                    balance,

                    remarks,

                    recorded_by

                )

                VALUES (

                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?

                )

            ");

            $stmtSale->execute([

                $saleUuid,

                $farmId,

                $harvestId,

                $saleNo,

                $saleDate,

                $customerName,

                $customerPhone,

                $customerAddress,

                $saleType,

                $saleStatus,

                $subtotal,

                $discount,

                $grandTotal,

                $amountPaid,

                $balance,

                $remarks,

                $staffId

            ]);

            /*
            |--------------------------------------------------------------------------
            | Sale ID
            |--------------------------------------------------------------------------
            */

            $saleId = (int)$pdo->lastInsertId();

            if ($saleId <= 0) {

                throw new Exception(
                    'Unable to create sale header.'
                );

            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Statements
            |--------------------------------------------------------------------------
            */

            $stmtSaleItem = $pdo->prepare("

                INSERT INTO sale_items (

                    uuid,

                    sale_id,

                    harvest_pond_id,

                    product_id,

                    quantity_fish,

                    quantity_kg,

                    average_weight_kg,

                    unit_price,

                    line_total,

                    remarks

                )

                VALUES (

                    ?,?,?,?,?,?,?,?,?,?

                )

            ");

            $stmtUpdateHarvestInventory = $pdo->prepare("
                UPDATE harvest_ponds
                SET
                    available_count = GREATEST(
                        0,
                        available_count - :fish1
                    ),

                    available_weight_kg = GREATEST(
                        0,
                        available_weight_kg - :weight1
                    ),

                    inventory_status = CASE

                        WHEN GREATEST(0, available_count - :fish2) = 0
                            THEN 'sold_out'

                        WHEN GREATEST(0, available_count - :fish3) < harvested_count
                            THEN 'partial'

                        ELSE 'available'

                    END

                WHERE
                    id = :harvest_pond_id
                AND harvest_id = :harvest_id
            ");
            /*
            * ===== Part 4 continues here =====
            */
                /*
            |--------------------------------------------------------------------------
            | Save Sale Items
            |--------------------------------------------------------------------------
            */

            foreach ($validatedItems as $item) {

                /*
                |--------------------------------------------------------------------------
                | Save Sale Item
                |--------------------------------------------------------------------------
                */

                $stmtSaleItem->execute([

                    generateUuid(),

                    $saleId,

                    $item['harvest_pond_id'],

                    'TABLE_FISH',

                    $item['quantity_fish'],

                    $item['quantity_kg'],

                    $item['average_weight_kg'],

                    $item['unit_price'],

                    $item['line_total'],

                    null

                ]);

                /*
                |--------------------------------------------------------------------------
                | Update Harvest Inventory
                |--------------------------------------------------------------------------
                |
                | Reduce available harvested inventory after a successful sale.
                |
                */

                $stmtUpdateHarvestInventory->execute([
                    ':fish1'           => $item['quantity_fish'],
                    ':weight1'         => $item['quantity_kg'],
                    ':fish2'           => $item['quantity_fish'],
                    ':fish3'           => $item['quantity_fish'],
                    ':harvest_pond_id' => $item['harvest_pond_id'],
                    ':harvest_id'      => $harvestId
                ]);

                /*
                |--------------------------------------------------------------------------
                | Update Harvest Inventory
                |--------------------------------------------------------------------------
                |
                | Harvest Inventory V2.0
                |
                | Every successful sale updates:
                |
                | - available_count
                | - available_weight_kg
                | - inventory_status
                |
                | harvested_count and harvested_weight_kg remain unchanged
                | as historical values.
                |
                */

                if ($stmtUpdateHarvestInventory->rowCount() !== 1) {

                    throw new Exception(
                        'Unable to update pond inventory.'
                    );

                }

            }
            /*
            |--------------------------------------------------------------------------
            | Harvest Inventory
            |--------------------------------------------------------------------------
            |
            | Harvest Inventory V2.0
            |
            | Each successful sale updates:
            |
            | - available_count
            | - available_weight_kg
            | - inventory_status
            |
            | harvested_count and harvested_weight_kg remain unchanged
            | to preserve the original harvest record.
            |
            */

            /*
            * ===== Part 5 continues here =====
            */
                /*
            |--------------------------------------------------------------------------
            | Save Payment
            |--------------------------------------------------------------------------
            */

            if ($amountPaid > 0) {

                $paymentNo = 'PAY-' . date('YmdHis');

                $stmtPayment = $pdo->prepare("

                    INSERT INTO sale_payments (

                        uuid,

                        sale_id,

                        payment_no,

                        payment_date,

                        payment_method,

                        amount,

                        reference_no,

                        payment_status,

                        received_by

                    )

                    VALUES (

                        ?,?,?,?,?,?,?,?,?

                    )

                ");

                $stmtPayment->execute([

                    generateUuid(),

                    $saleId,

                    $paymentNo,

                    $saleDate,

                    $paymentMethod,

                    $amountPaid,

                    $referenceNo,

                    'completed',

                    $staffId

                ]);

            }

            /*
            |--------------------------------------------------------------------------
            | Generate Receipt
            |--------------------------------------------------------------------------
            */

            $receiptNo = 'RCT-' . date('YmdHis');

            $stmtReceipt = $pdo->prepare("

                INSERT INTO sale_receipts (

                    uuid,

                    sale_id,

                    receipt_no,

                    receipt_date,

                    receipt_status,

                    print_count

                )

                VALUES (

                    ?,?,?,?,?,?

                )

            ");

            $stmtReceipt->execute([

                generateUuid(),

                $saleId,

                $receiptNo,

                $saleDate,

                'pending',

                0

            ]);

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            $stmtAudit = $pdo->prepare("

                INSERT INTO sale_logs (

                    uuid,

                    sale_id,

                    action,

                    description,

                    new_values,

                    ip_address,

                    user_agent,

                    recorded_by

                )

                VALUES (

                    ?,?,?,?,?,?,?,?

                )

            ");

            $stmtAudit->execute([

                generateUuid(),

                $saleId,

                'create',

                'Sale created successfully.',

                json_encode($_POST, JSON_UNESCAPED_UNICODE),

                $_SERVER['REMOTE_ADDR'] ?? null,

                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),

                $staffId

            ]);

            /*
            |--------------------------------------------------------------------------
            | Offline Synchronization Queue
            |--------------------------------------------------------------------------
            */

            $deviceUuid = $_SESSION['device_uuid'] ?? 'WEB-SERVER';

            $payload = [

                'sale_id'    => $saleId,
                'sale_uuid'  => $saleUuid,
                'sale_no'    => $saleNo,
                'farm_id'    => $farmId,
                'harvest_id' => $harvestId,
                'sale_date'  => $saleDate,
                'staff_id'   => $staffId,
                'created_at' => date('Y-m-d H:i:s')

            ];

            $stmtSync = $pdo->prepare("

                INSERT INTO sales_sync_queue (

                    uuid,

                    sale_uuid,

                    device_uuid,

                    operation,

                    payload_json,

                    status

                )

                VALUES (

                    ?,?,?,?,?,?

                )

            ");

            $stmtSync->execute([

                generateUuid(),

                $saleUuid,

                $deviceUuid,

                'insert',

                json_encode($payload, JSON_UNESCAPED_UNICODE),

                'pending'

            ]);

            /*
            * ===== Part 6 continues here =====
            */
                /*
            |--------------------------------------------------------------------------
            | Commit Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | Success Message
            |--------------------------------------------------------------------------
            */

            $_SESSION['success'] = sprintf(
                'Sale %s was saved successfully.',
                $saleNo
            );

            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header('Location: view.php?id=' . $saleId);

            exit;

        }
/*
|--------------------------------------------------------------------------
| Rollback
|--------------------------------------------------------------------------
*/
catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | Log Error
    |--------------------------------------------------------------------------
    */

    error_log(sprintf(
        '[SALES SAVE ERROR] %s | File: %s | Line: %d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    /*
    |--------------------------------------------------------------------------
    | User Message
    |--------------------------------------------------------------------------
    */

   $_SESSION['error'] =
    $e->getMessage()
    . '<br><strong>File:</strong> ' . $e->getFile()
    . '<br><strong>Line:</strong> ' . $e->getLine();
    /*
    |--------------------------------------------------------------------------
    | Redirect Back
    |--------------------------------------------------------------------------
    */

    header('Location: create.php');

    exit;

}