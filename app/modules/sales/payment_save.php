<?php

declare(strict_types=1);

/**
 * ============================================================
 * YOTRIBE IFMS
 * Sales Management
 * Save Payment
 * ============================================================
 */

require_once __DIR__ . '/../../middleware/auth_guard.php';
require_once __DIR__ . '/../../middleware/farm_guard.php';
require_once __DIR__ . '/../../middleware/authorize.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../helpers/permission.php';

require_once __DIR__ . '/../../helpers/csrf_helper.php';

require_permission('sales.payment');

validate_csrf();

$farm_id = farm_id();

/*
|--------------------------------------------------------------------------
| Collect Data
|--------------------------------------------------------------------------
*/

$saleId = (int)($_POST['sale_id'] ?? 0);

$paymentDate = trim($_POST['payment_date'] ?? '');

$paymentMethod = trim($_POST['payment_method'] ?? '');

$amount = (float)($_POST['amount'] ?? 0);

$referenceNo = trim($_POST['reference_no'] ?? '');

$remarks = trim($_POST['remarks'] ?? '');

$staffId = (int)$_SESSION['staff_id'];

if ($saleId <= 0 || $amount <= 0) {

    $_SESSION['error'] = 'Invalid payment information.';

    header("Location: payment.php?id={$saleId}");

    exit;

}

try {

    $pdo->beginTransaction();

    /*
    ------------------------------------------------------------
    Lock Sale Record
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT *

        FROM sales

        WHERE id = ?

        AND farm_id = ?

        FOR UPDATE

    ");

    $stmt->execute([$saleId, $farm_id]);

    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {

        throw new Exception('Sale not found.');

    }

    if (in_array($sale['status'], ['cancelled', 'refunded'], true)) {

        throw new Exception('Payments cannot be added to this sale.');

    }

    /*
    ------------------------------------------------------------
    Recalculate Current Balance
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT COALESCE(SUM(amount),0)

        FROM sale_payments

        WHERE sale_id = ?

        AND payment_status='completed'

    ");

    $stmt->execute([$saleId]);

    $alreadyPaid = (float)$stmt->fetchColumn();

    $balance = (float)$sale['total_amount'] - $alreadyPaid;

    if ($amount > $balance) {

        throw new Exception(
            'Payment exceeds outstanding balance.'
        );

    }
        /*
    ------------------------------------------------------------
    Payment Number
    ------------------------------------------------------------
    */

    $paymentNo = sprintf(

        'PAY-%s-%05d',

        date('Ymd'),

        random_int(1, 99999)

    );
        /*
    ------------------------------------------------------------
    Save Payment
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        INSERT INTO sale_payments (

            uuid,

            sale_id,

            payment_no,

            payment_date,

            payment_method,

            reference_no,

            amount,

            remarks,

            payment_status,

            received_by,

            created_at

        )

        VALUES (

            ?,?,?,?,?,?,?,?,?,?,NOW()

        )

    ");

    $stmt->execute([

        generateUuid(),

        $saleId,

        $paymentNo,

        $paymentDate,

        $paymentMethod,

        $referenceNo,

        $amount,

        $remarks,

        'completed',

        $staffId

    ]);
        /*
    ------------------------------------------------------------
    Recalculate Totals
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        SELECT COALESCE(SUM(amount),0)

        FROM sale_payments

        WHERE sale_id = ?

        AND payment_status='completed'

    ");

    $stmt->execute([$saleId]);

    $totalPaid = (float)$stmt->fetchColumn();

    $newBalance = (float)$sale['total_amount'] - $totalPaid;

    if ($newBalance < 0) {

        $newBalance = 0;

    }

    $status = 'partially_paid';

    if ($newBalance == 0.0) {

        $status = 'paid';

    }
        /*
    ------------------------------------------------------------
    Update Sale
    ------------------------------------------------------------
    */

    $stmt = $pdo->prepare("

        UPDATE sales

        SET

            amount_paid = ?,

            balance = ?,

            payment_status = ?,

            updated_at = NOW()

        WHERE id = ?

    ");

    $stmt->execute([

        $totalPaid,

        $newBalance,

        $status,

        $saleId

    ]);
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

        'payment',

        sprintf(

            'Payment of ₦%s received via %s (%s)',

            number_format($amount, 2),

            ucfirst($paymentMethod),

            $paymentNo

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

            ?, 'payment', 'pending', 0, NOW()

        )

        ON DUPLICATE KEY UPDATE

            status = 'pending',

            updated_at = NOW()

    ");

    $stmt->execute([

        $sale['uuid']

    ]);

    /*
    ------------------------------------------------------------
    Notification (Optional)
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

        'Payment Received',

        sprintf(

            'Payment of ₦%s received for Sale %s.',

            number_format($amount, 2),

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

        'Payment recorded successfully.';

    header("Location:view.php?id={$saleId}");

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    error_log(
        '[PAYMENT ERROR] ' . $e->getMessage()
    );

    $_SESSION['error'] =

        'Unable to process payment. ' .
        $e->getMessage();

    header("Location:payment.php?id={$saleId}");

    exit;

}