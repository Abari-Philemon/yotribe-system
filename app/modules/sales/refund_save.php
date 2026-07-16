<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales Management
 * Process Refund
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';
require_once __DIR__ . '/../../helpers/csrf_helper.php';

require_permission('sales.refund');

validate_csrf();

$farm_id = farm_id();

$saleId = (int)($_POST['sale_id'] ?? 0);

$refundDate = trim($_POST['refund_date'] ?? '');

$refundType = trim($_POST['refund_type'] ?? '');

$refundReason = trim($_POST['refund_reason'] ?? '');

$refundNotes = trim($_POST['refund_notes'] ?? '');

$staffId = (int)$_SESSION['staff_id'];

if ($saleId <= 0) {

    $_SESSION['error'] = 'Invalid sale selected.';

    header('Location: history.php');

    exit;

}

try {

    $pdo->beginTransaction();

    /*
    ------------------------------------------------------------
    Lock Sale
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT *

        FROM sales

        WHERE id=?

        AND farm_id=?

        FOR UPDATE

    ");

    $stmt->execute([

        $saleId,

        $farm_id

    ]);

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {

        throw new Exception('Sale not found.');

    }

    if ($sale['status'] !== 'completed') {

        throw new Exception(
            'Only completed sales can be refunded.'
        );

    }
        /*
    ------------------------------------------------------------
    Sale Items
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT *

        FROM sale_items

        WHERE sale_id=?

        ORDER BY id

    ");

    $stmt->execute([$saleId]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {

        throw new Exception(
            'Sale has no items.'
        );

    }
        /*
    ------------------------------------------------------------
    Refund Record
    ------------------------------------------------------------
    */

    $refundNo = sprintf(

        'REF-%s-%05d',

        date('Ymd'),

        random_int(1,99999)

    );

    $stmt = $pdo->prepare("

        INSERT INTO sale_refunds (

            uuid,

            refund_no,

            sale_id,

            refund_type,

            refund_reason,

            refund_notes,

            refund_date,

            refund_amount,

            refunded_by,

            created_at

        )

        VALUES (

            ?,?,?,?,?,?,?,?,?,NOW()

        )

    ");

    $stmt->execute([

        generateUuid(),

        $refundNo,

        $saleId,

        $refundType,

        $refundReason,

        $refundNotes,

        $refundDate,

        $sale['total_amount'],

        $staffId

    ]);
        /*
    ------------------------------------------------------------
    Update Sale
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        UPDATE sales

        SET

            status='refunded',

            updated_at=NOW()

        WHERE id=?

    ");

    $stmt->execute([

        $saleId

    ]);
        /*
    ------------------------------------------------------------
    Reverse Payments
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        UPDATE sale_payments

        SET

            payment_status='reversed',

            updated_at=NOW()

        WHERE sale_id=?

        AND payment_status='completed'

    ");

    $stmt->execute([

        $saleId

    ]);
        /*
    ------------------------------------------------------------
    Restore Inventory
    ------------------------------------------------------------
    */

    foreach ($items as $item) {

        /*
        --------------------------------------------------------
        Lock Harvest Item
        --------------------------------------------------------
        */

        $stmt = $pdo->prepare("

            SELECT *

            FROM harvest_ponds

            WHERE id = ?

            FOR UPDATE

        ");

        $stmt->execute([

            $item['harvest_pond_id']

        ]);

        $harvestPond = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$harvestPond) {

            throw new Exception(

                'Harvest pond not found.'

            );

        }

        /*
        --------------------------------------------------------
        Restore Fish Quantity
        --------------------------------------------------------
        */

        $stmt = $pdo->prepare("

            UPDATE harvest_ponds

            SET

                available_fish =
                    available_fish + ?,

                available_weight_kg =
                    available_weight_kg + ?,

                updated_at = NOW()

            WHERE id = ?

        ");

        $stmt->execute([

            $item['quantity_fish'],

            $item['quantity_kg'],

            $item['harvest_pond_id']

        ]);

        /*
        --------------------------------------------------------
        Inventory Movement
        --------------------------------------------------------
        */

        $stmt = $pdo->prepare("

            INSERT INTO inventory_movements (

                uuid,

                farm_id,

                movement_type,

                reference_type,

                reference_id,

                harvest_pond_id,

                fish_quantity,

                weight_kg,

                remarks,

                recorded_by,

                created_at

            )

            VALUES (

                ?,?,?,?,?,?,?,?,?,?,NOW()

            )

        ");

        $stmt->execute([

            generateUuid(),

            $farm_id,

            'refund',

            'sale',

            $saleId,

            $item['harvest_pond_id'],

            $item['quantity_fish'],

            $item['quantity_kg'],

            'Inventory restored after sale refund.',

            $staffId

        ]);

    }
        /*
    ------------------------------------------------------------
    Update Harvest Totals
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        UPDATE harvests h

        SET

            sold_fish = (

                SELECT COALESCE(

                    SUM(quantity_fish),

                    0

                )

                FROM sale_items si

                INNER JOIN sales s
                    ON s.id = si.sale_id

                WHERE

                    s.harvest_id = h.id

                AND s.status <> 'refunded'

            ),

            sold_weight_kg = (

                SELECT COALESCE(

                    SUM(quantity_kg),

                    0

                )

                FROM sale_items si

                INNER JOIN sales s
                    ON s.id = si.sale_id

                WHERE

                    s.harvest_id = h.id

                AND s.status <> 'refunded'

            ),

            updated_at = NOW()

        WHERE id = ?

    ");

    $stmt->execute([

        $sale['harvest_id']

    ]);
        /*
    ------------------------------------------------------------
    Verification
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT

            SUM(available_fish) fish,

            SUM(available_weight_kg) weight

        FROM harvest_ponds

        WHERE harvest_id = ?

    ");

    $stmt->execute([

        $sale['harvest_id']

    ]);

    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$verification) {

        throw new Exception(

            'Inventory verification failed.'

        );

    }
        /*
    ------------------------------------------------------------
    Audit Log
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        INSERT INTO sale_logs (

            uuid,

            sale_id,

            action,

            description,

            recorded_by,

            created_at

        )

        VALUES (

            ?,?,?,?,?,NOW()

        )

    ");

    $stmt->execute([

        generateUuid(),

        $saleId,

        'refund',

        sprintf(

            'Sale refunded. Refund No: %s. Amount: ₦%s. Reason: %s.',

            $refundNo,

            number_format((float)$sale['total_amount'], 2),

            ucfirst(str_replace('_', ' ', $refundReason))

        ),

        $staffId

    ]);

    /*
    ------------------------------------------------------------
    Queue Offline Synchronization
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        INSERT INTO sales_sync_queue (

            sale_uuid,

            sync_type,

            status,

            retry_count,

            created_at

        )

        VALUES (

            ?, 'refund', 'pending', 0, NOW()

        )

        ON DUPLICATE KEY UPDATE

            sync_type = VALUES(sync_type),

            status = 'pending',

            retry_count = 0,

            updated_at = NOW()

    ");

    $stmt->execute([

        $sale['uuid']

    ]);

    /*
    ------------------------------------------------------------
    Create Notification
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        INSERT INTO notifications (

            uuid,

            farm_id,

            category,

            title,

            message,

            reference_type,

            reference_id,

            created_by,

            created_at

        )

        VALUES (

            ?,?,?,?,?,?,?,?,NOW()

        )

    ");

    $stmt->execute([

        generateUuid(),

        $farm_id,

        'sales',

        'Sale Refunded',

        sprintf(

            'Sale %s has been refunded successfully.',

            $sale['sale_no']

        ),

        'sale',

        $saleId,

        $staffId

    ]);

    /*
    ------------------------------------------------------------
    Commit Transaction
    ------------------------------------------------------------
    */

    $pdo->commit();

    $_SESSION['success'] =

        sprintf(

            'Refund processed successfully. Refund Number: %s',

            $refundNo

        );

    header("Location:view.php?id={$saleId}");

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    error_log(

        '[REFUND ERROR] ' . $e->getMessage()

    );

    $_SESSION['error'] =

        'Refund processing failed. ' .
        $e->getMessage();

    header("Location:refund.php?id={$saleId}");

    exit;

}