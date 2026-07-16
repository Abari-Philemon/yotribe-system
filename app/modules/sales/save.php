<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales & Distribution Management
 * Save Sale
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

$farm_id  = farm_id();
$staff_id = $_SESSION['staff_id'];

$sale_no        = trim($_POST['sale_no'] ?? '');
$sale_date      = trim($_POST['sale_date'] ?? '');
$harvest_id     = (int)($_POST['harvest_id'] ?? 0);

$sale_type      = trim($_POST['sale_type'] ?? 'customer_sale');

$customer_name  = trim($_POST['customer_name'] ?? '');

$customer_phone = trim($_POST['customer_phone'] ?? '');

$customer_address = trim($_POST['customer_address'] ?? '');

$discount = (float)($_POST['discount'] ?? 0);

$remarks = trim($_POST['remarks'] ?? '');

$items = $_POST['items'] ?? [];

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

$errors = [];

if ($sale_no === '') {

    $errors[] = 'Sale number is required.';

}

if ($harvest_id <= 0) {

    $errors[] = 'Please select a harvest.';

}

if (empty($items)) {

    $errors[] = 'At least one sale item is required.';

}

if (!empty($errors)) {

    $_SESSION['error'] = implode('<br>', $errors);

    header('Location: create.php');

    exit;

}

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

id=?

AND farm_id=?

LIMIT 1

");

$stmt->execute([

    $harvest_id,

    $farm_id

]);

$harvest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$harvest) {

    $_SESSION['error'] = 'Harvest not found.';

    header('Location:create.php');

    exit;

}

if ((int)$harvest['is_open'] !== 1) {

    $_SESSION['error'] = 'Harvest has already been closed.';

    header('Location:create.php');

    exit;

}

/*
|--------------------------------------------------------------------------
| Begin Transaction
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();

try {

    /*
    ============================================================
    Continue in Part 2
    ============================================================
    */
        /*
    |--------------------------------------------------------------------------
    | Calculate Sale Totals
    |--------------------------------------------------------------------------
    */

    $subtotal = 0;
    $validatedItems = [];

    foreach ($items as $index => $item) {

        $harvest_pond_id = (int)($item['harvest_pond_id'] ?? 0);

        $quantity_fish = (int)($item['quantity_fish'] ?? 0);

        $quantity_kg = (float)($item['quantity_kg'] ?? 0);

        $unit_price = (float)($item['unit_price'] ?? 0);

        if (
            $harvest_pond_id <= 0 ||
            $quantity_kg <= 0 ||
            $unit_price < 0
        ) {
            throw new Exception(
                'Invalid sale item on row ' . ($index + 1)
            );
        }

        /*
        ------------------------------------------------------------
        Verify Harvest Inventory
        ------------------------------------------------------------
        */

        $stmt = $pdo->prepare("

            SELECT

                hp.id,

                hp.harvest_id,

                hp.pond_stocking_id,

                hp.quantity_fish,

                hp.quantity_kg,

                ps.current_count

            FROM harvest_ponds hp

            INNER JOIN pond_stocking ps

                ON ps.id = hp.pond_stocking_id

            WHERE

                hp.id = ?

            AND hp.harvest_id = ?

            LIMIT 1

        ");

        $stmt->execute([

            $harvest_pond_id,

            $harvest_id

        ]);

        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inventory) {

            throw new Exception(
                'Harvest inventory not found.'
            );

        }

        /*
        ------------------------------------------------------------
        Prevent Overselling
        ------------------------------------------------------------
        */

        if ($quantity_fish > (int)$inventory['current_count']) {

            throw new Exception(
                'Fish quantity exceeds available inventory.'
            );

        }

        /*
        ------------------------------------------------------------
        Calculate Line Total
        ------------------------------------------------------------
        */

        $lineTotal = round(

            $quantity_kg * $unit_price,

            2

        );

        $subtotal += $lineTotal;

        $validatedItems[] = [

            'harvest_pond_id' => $harvest_pond_id,

            'quantity_fish'   => $quantity_fish,

            'quantity_kg'     => $quantity_kg,

            'unit_price'      => $unit_price,

            'line_total'      => $lineTotal

        ];

    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Totals
    |--------------------------------------------------------------------------
    */

    $discount = max(0, $discount);

    $grandTotal = max(

        0,

        round(

            $subtotal - $discount,

            2

        )

    );

    $amountPaid = (float)($_POST['amount_paid'] ?? 0);

    $amountPaid = max(0, $amountPaid);

    $balance = round(

        $grandTotal - $amountPaid,

        2

    );

    /*
    |--------------------------------------------------------------------------
    | Generate UUID
    |--------------------------------------------------------------------------
    */

    $saleUuid = sprintf(

        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

        mt_rand(0, 0xffff),

        mt_rand(0, 0xffff),

        mt_rand(0, 0xffff),

        mt_rand(0, 0x0fff) | 0x4000,

        mt_rand(0, 0x3fff) | 0x8000,

        mt_rand(0, 0xffff),

        mt_rand(0, 0xffff),

        mt_rand(0, 0xffff)

    );

    /*
    |--------------------------------------------------------------------------
    | Save Sale Header
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

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

            ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?

        )

    ");

    $stmt->execute([

        $saleUuid,

        $farm_id,

        $harvest_id,

        $sale_no,

        $sale_date,

        $customer_name,

        $customer_phone,

        $customer_address,

        $sale_type,

        'completed',

        $subtotal,

        $discount,

        $grandTotal,

        $amountPaid,

        $balance,

        $remarks,

        $staff_id

    ]);

    $saleId = (int)$pdo->lastInsertId();

    /*
    ============================================================
    Continue in Part 3
    ============================================================
    */
        /*
    |--------------------------------------------------------------------------
    | Save Sale Items
    |--------------------------------------------------------------------------
    */

    $stmtSaleItem = $pdo->prepare("

        INSERT INTO sale_items (

            uuid,

            sale_id,

            harvest_pond_id,

            product_name,

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

    /*
    |--------------------------------------------------------------------------
    | Update Harvest Inventory
    |--------------------------------------------------------------------------
    |
    | Version 1:
    | Reduce harvest_ponds available inventory.
    | (Version 2 will use harvest_inventory ledger.)
    |--------------------------------------------------------------------------
    */

    $stmtInventory = $pdo->prepare("

        UPDATE harvest_ponds

        SET

            available_fish = available_fish - ?,

            available_weight_kg = available_weight_kg - ?

        WHERE id = ?

    ");

    /*
    |--------------------------------------------------------------------------
    | Update Pond Stocking
    |--------------------------------------------------------------------------
    */

    $stmtPond = $pdo->prepare("

        UPDATE pond_stocking

        SET

            current_count = current_count - ?

        WHERE id = ?

    ");

    foreach ($validatedItems as $item) {

        /*
        ------------------------------------------------------------
        Generate Item UUID
        ------------------------------------------------------------
        */

        $itemUuid = sprintf(

            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            mt_rand(0,0xffff),
            mt_rand(0,0xffff),

            mt_rand(0,0xffff),

            mt_rand(0,0x0fff)|0x4000,

            mt_rand(0,0x3fff)|0x8000,

            mt_rand(0,0xffff),
            mt_rand(0,0xffff),
            mt_rand(0,0xffff)

        );

        /*
        ------------------------------------------------------------
        Average Weight
        ------------------------------------------------------------
        */

        $averageWeight = 0;

        if ($item['quantity_fish'] > 0) {

            $averageWeight = round(

                $item['quantity_kg']
                /
                $item['quantity_fish'],

                3

            );

        }

        /*
        ------------------------------------------------------------
        Save Item
        ------------------------------------------------------------
        */

        $stmtSaleItem->execute([

            $itemUuid,

            $saleId,

            $item['harvest_pond_id'],

            'Table Fish',

            $item['quantity_fish'],

            $item['quantity_kg'],

            $averageWeight,

            $item['unit_price'],

            $item['line_total'],

            null

        ]);

        /*
        ------------------------------------------------------------
        Reduce Harvest Inventory
        ------------------------------------------------------------
        */

        $stmtInventory->execute([

            $item['quantity_fish'],

            $item['quantity_kg'],

            $item['harvest_pond_id']

        ]);

        /*
        ------------------------------------------------------------
        Find Pond Stocking Record
        ------------------------------------------------------------
        */

        $stmt = $pdo->prepare("

            SELECT pond_stocking_id

            FROM harvest_ponds

            WHERE id = ?

            LIMIT 1

        ");

        $stmt->execute([

            $item['harvest_pond_id']

        ]);

        $pondStock = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pondStock) {

            $stmtPond->execute([

                $item['quantity_fish'],

                $pondStock['pond_stocking_id']

            ]);

        }

    }

    /*
    ============================================================
    Continue in Part 4
    ============================================================
    */
        /*
    |--------------------------------------------------------------------------
    | Save Payment
    |--------------------------------------------------------------------------
    */

    $paymentMethod = trim($_POST['payment_method'] ?? 'cash');
    $referenceNo   = trim($_POST['reference_no'] ?? '');

    if ($amountPaid > 0) {

        $paymentNo = 'PAY-' . date('YmdHis');

        $paymentUuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0xffff),
            mt_rand(0,0x0fff) | 0x4000,
            mt_rand(0,0x3fff) | 0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
        );

        $stmt = $pdo->prepare("

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

            VALUES (?,?,?,?,?,?,?,?,?)

        ");

        $stmt->execute([

            $paymentUuid,

            $saleId,

            $paymentNo,

            $sale_date,

            $paymentMethod,

            $amountPaid,

            $referenceNo,

            'completed',

            $staff_id

        ]);

    }

    /*
    |--------------------------------------------------------------------------
    | Generate Receipt
    |--------------------------------------------------------------------------
    */

    $receiptUuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0xffff),
        mt_rand(0,0x0fff) | 0x4000,
        mt_rand(0,0x3fff) | 0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
    );

    $receiptNo = 'RCT-' . date('YmdHis');

    $stmt = $pdo->prepare("

        INSERT INTO sale_receipts (

            uuid,

            sale_id,

            receipt_no,

            receipt_date,

            receipt_status,

            print_count

        )

        VALUES (?,?,?,?,?,?)

    ");

    $stmt->execute([

        $receiptUuid,

        $saleId,

        $receiptNo,

        $sale_date,

        'pending',

        0

    ]);

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    $logUuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0xffff),
        mt_rand(0,0x0fff) | 0x4000,
        mt_rand(0,0x3fff) | 0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
    );

    $stmt = $pdo->prepare("

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

        VALUES (?,?,?,?,?,?,?,?)

    ");

    $stmt->execute([

        $logUuid,

        $saleId,

        'create',

        'Sale created successfully.',

        json_encode($_POST),

        $_SERVER['REMOTE_ADDR'] ?? null,

        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),

        $staff_id

    ]);

    /*
    ============================================================
    Continue in Part 5
    ============================================================
    */
        /*
    |--------------------------------------------------------------------------
    | Queue Offline Synchronization
    |--------------------------------------------------------------------------
    |
    | Every completed sale is queued for synchronization.
    | The background sync worker will process pending records.
    |--------------------------------------------------------------------------
    */

    $deviceUuid = $_SESSION['device_uuid'] ?? 'WEB-SERVER';

    $queueUuid = generateUuid();

    $payload = [

        'sale_id'      => $saleId,
        'sale_uuid'    => $saleUuid,
        'sale_no'      => $sale_no,
        'farm_id'      => $farm_id,
        'harvest_id'   => $harvest_id,
        'sale_date'    => $sale_date,
        'staff_id'     => $staff_id,
        'created_at'   => date('Y-m-d H:i:s')

    ];

    $stmt = $pdo->prepare("

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

    $stmt->execute([

        $queueUuid,

        $saleUuid,

        $deviceUuid,

        'insert',

        json_encode($payload, JSON_UNESCAPED_UNICODE),

        'pending'

    ]);

    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    $_SESSION['success'] =

        'Sale saved successfully.';

    header(

        'Location: view.php?id=' . $saleId

    );

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

    error_log(

        '[SALES SAVE ERROR] ' .

        $e->getMessage()

    );

    $_SESSION['error'] =

        'Unable to save sale. ' .

        $e->getMessage();

    header(

        'Location: create.php'

    );

    exit;

}