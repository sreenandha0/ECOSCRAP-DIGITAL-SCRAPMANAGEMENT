<?php

session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/phpqrcode/lib/qrlib.php";


// ==========================================================
// 1. AUTHORISATION CHECK
// ==========================================================

if (
    !isset($_SESSION['collector_id']) ||
    ($_SESSION['role'] ?? '') !== "Collector"
) {
    redirect("../login.php");
    exit();
}


// ==========================================================
// 2. REQUEST METHOD CHECK
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $_SESSION['error'] = "Invalid request method.";

    header("Location: assigned_requests.php");
    exit();
}


// ==========================================================
// 3. CSRF TOKEN VERIFICATION
// ==========================================================

// Check whether token exists in both POST and session

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken   = $_SESSION['csrf_token'] ?? '';

if (
    empty($submittedToken) ||
    empty($sessionToken) ||
    !hash_equals($sessionToken, $submittedToken)
) {

    $_SESSION['error'] =
        "Invalid request token. Please return to the previous page and try again.";

    header("Location: assigned_requests.php");
    exit();
}


// ==========================================================
// 4. GET REQUEST DATA
// ==========================================================
$activity_id = (int) ($_POST['activity_id'] ?? 0);
$collector_id = (int) ($_SESSION['collector_id'] ?? 0);


if ($activity_id <= 0 || $collector_id <= 0) {

    $_SESSION['error'] = "Invalid pickup request.";

    header("Location: assigned_requests.php");
    exit();
}


// ==========================================================
// 5. VARIABLES
// ==========================================================

$qrFile = "";
$transactionStarted = false;


try {

    mysqli_report(
        MYSQLI_REPORT_ERROR |
        MYSQLI_REPORT_STRICT
    );


    // ======================================================
    // START TRANSACTION
    // ======================================================

    $conn->begin_transaction();

    $transactionStarted = true;


    // ======================================================
    // STEP 1: VERIFY PICKUP REQUEST
    // ======================================================

    $stmt = $conn->prepare("
        SELECT
            activity_id,
            user_id
        FROM activity
        WHERE
            activity_id = ?
            AND collector_id = ?
            AND status = 'Assigned'
        FOR UPDATE
    ");

    $stmt->bind_param(
        "ii",
        $activity_id,
        $collector_id
    );

    $stmt->execute();

    $result = $stmt->get_result();


    if ($result->num_rows === 0) {

        throw new Exception(
            "Pickup request not found or is no longer available."
        );
    }


    $pickup = $result->fetch_assoc();

    $user_id = (int) $pickup['user_id'];

    $stmt->close();


    // ======================================================
    // STEP 2: GET SCRAP COLLECTOR NAME
    // ======================================================

    $stmt = $conn->prepare("
        SELECT name
        FROM scrapcollector
        WHERE collector_id = ?
    ");

    $stmt->bind_param(
        "i",
        $collector_id
    );

    $stmt->execute();

    $collectorResult = $stmt->get_result();


    if ($collectorResult->num_rows === 0) {

        throw new Exception(
            "Scrap collector account not found."
        );
    }


    $collector = $collectorResult->fetch_assoc();

    $collector_name = $collector['name'];

    $stmt->close();


    // ======================================================
    // STEP 3: CREATE QR DIRECTORY
    // ======================================================

    $qrFolder = __DIR__ . "/../uploads/qr/";


    if (!is_dir($qrFolder)) {

        if (!mkdir(
            $qrFolder,
            0755,
            true
        ) && !is_dir($qrFolder)) {

            throw new Exception(
                "Unable to create QR code directory."
            );
        }
    }


    // ======================================================
    // STEP 4: GENERATE SECURE QR CODE
    // ======================================================

    $filename =
        "pickup_" .
        $activity_id .
        "_" .
        time() .
        ".png";


    $qrFile = $qrFolder . $filename;


    // Generate secure random token

    $token = bin2hex(
        random_bytes(32)
    );


    // QR data

    $qrData = json_encode([
        "activity_id"  => $activity_id,
        "collector_id" => $collector_id,
        "token"        => $token,
        "generated_at" => date("Y-m-d H:i:s")
    ]);


    if ($qrData === false) {

        throw new Exception(
            "Failed to generate QR data."
        );
    }


    // Generate QR image

    QRcode::png(
        $qrData,
        $qrFile,
        QR_ECLEVEL_L,
        5
    );


    // Verify QR file

    if (
        !file_exists($qrFile) ||
        filesize($qrFile) === 0
    ) {

        throw new Exception(
            "QR code generation failed."
        );
    }


    // ======================================================
    // STEP 5: UPDATE ACTIVITY
    // ======================================================

    $stmt = $conn->prepare("
        UPDATE activity
        SET
            status = 'In Progress',
            qr_code = ?,
            qr_status = 'Unused'
        WHERE
            activity_id = ?
            AND collector_id = ?
            AND status = 'Assigned'
    ");


    $stmt->bind_param(
        "sii",
        $filename,
        $activity_id,
        $collector_id
    );


    $stmt->execute();


    if ($stmt->affected_rows !== 1) {

        throw new Exception(
            "Failed to update pickup status."
        );
    }


    $stmt->close();


    // ======================================================
    // STEP 6: UPDATE SCRAP COLLECTOR STATUS
    // ======================================================

    $stmt = $conn->prepare("
        UPDATE scrapcollector
        SET availability_status = 'Busy'
        WHERE collector_id = ?
    ");


    $stmt->bind_param(
        "i",
        $collector_id
    );


    $stmt->execute();

    $stmt->close();


    // ======================================================
    // STEP 7: NOTIFICATION SETTINGS
    // ======================================================

    $notification_type = "pickup_accepted";

    $reference_type = "activity";

    $is_read = 0;


    // ======================================================
    // STEP 8: NOTIFY USER
    // ======================================================

    $user_title = "Pickup Accepted";

    $user_message =
        "Your pickup request #" .
        $activity_id .
        " has been accepted by " .
        $collector_name .
        " and is now In Progress.";


    $stmt = $conn->prepare("
        INSERT INTO notifications
        (
            recipient_type,
            recipient_id,
            notification_type,
            title,
            message,
            reference_id,
            reference_type,
            is_read,
            created_at
        )
        VALUES
        (
            'User',
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW()
        )
    ");


    $stmt->bind_param(
        "isssisi",
        $user_id,
        $notification_type,
        $user_title,
        $user_message,
        $activity_id,
        $reference_type,
        $is_read
    );


    $stmt->execute();

    $stmt->close();


    // ======================================================
    // STEP 9: NOTIFY ADMINS
    // ======================================================

    $admin_title = "Pickup Accepted";

    $admin_message =
        "Pickup request #" .
        $activity_id .
        " has been accepted by " .
        $collector_name .
        ".";


    $adminQuery = $conn->query("
        SELECT admin_id
        FROM admin
    ");


    if ($adminQuery->num_rows > 0) {

        $stmt = $conn->prepare("
            INSERT INTO notifications
            (
                recipient_type,
                recipient_id,
                notification_type,
                title,
                message,
                reference_id,
                reference_type,
                is_read,
                created_at
            )
            VALUES
            (
                'Admin',
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
        ");


        while (
            $admin = $adminQuery->fetch_assoc()
        ) {

            $admin_id =
                (int) $admin['admin_id'];


            $stmt->bind_param(
                "isssisi",
                $admin_id,
                $notification_type,
                $admin_title,
                $admin_message,
                $activity_id,
                $reference_type,
                $is_read
            );


            $stmt->execute();
        }


        $stmt->close();
    }


    // ======================================================
    // STEP 10: COMMIT TRANSACTION
    // ======================================================

    $conn->commit();

    $transactionStarted = false;


    // Optional:
    // Regenerate CSRF token after successful sensitive action

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));


    $_SESSION['msg'] =
        "Pickup accepted successfully! QR code generated.";


    header(
        "Location: assigned_requests.php?success=started"
    );

    exit();


} catch (Throwable $e) {


    // ======================================================
    // ROLLBACK DATABASE
    // ======================================================

    if ($transactionStarted) {

        $conn->rollback();
    }


    // ======================================================
    // REMOVE QR FILE IF DATABASE FAILED
    // ======================================================

    if (
        !empty($qrFile) &&
        file_exists($qrFile)
    ) {

        unlink($qrFile);
    }


    // ======================================================
    // ERROR MESSAGE
    // ======================================================

    $_SESSION['error'] =
        "Failed to accept pickup: " .
        $e->getMessage();


    header(
        "Location: assigned_requests.php"
    );

    exit();
}