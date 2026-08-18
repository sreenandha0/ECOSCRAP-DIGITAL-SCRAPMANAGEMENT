<?php

// --------------------------------------------------
// ERROR REPORTING
// --------------------------------------------------

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// --------------------------------------------------
// 1. ADMIN AUTHORIZATION
// --------------------------------------------------

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== "Admin"
) {
    header("Location: ../login.php");
    exit();
}

// --------------------------------------------------
// 2. POST ONLY
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage.php");
    exit();
}

// --------------------------------------------------
// 3. GET POST DATA
// --------------------------------------------------

$activity_id = (int)($_POST['activity_id'] ?? 0);
$collector_id = (int)($_POST['collector_id'] ?? 0);

if ($activity_id <= 0 || $collector_id <= 0) {

    $_SESSION['error'] = "Invalid request payload.";

    header("Location: manage.php");
    exit();
}

// --------------------------------------------------
// MYSQL EXCEPTION MODE
// --------------------------------------------------

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    // --------------------------------------------------
    // START TRANSACTION
    // --------------------------------------------------

    $conn->begin_transaction();

    // --------------------------------------------------
    // STEP 1:
    // GET PICKUP REQUEST
    // --------------------------------------------------

    $stmt = $conn->prepare("
        SELECT
            user_id,
            scrap_type,
            scrap_weight,
            pickup_address,
            pickup_pincode,
            preferred_pickup_date,
            pickup_time,
            status
        FROM activity
        WHERE activity_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param(
        "i",
        $activity_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        throw new Exception(
            "Pickup request not found."
        );
    }

    $activity = $result->fetch_assoc();

    $stmt->close();

    // --------------------------------------------------
    // CHECK REQUEST STATUS
    // --------------------------------------------------

    if ($activity['status'] !== "Approved") {

        throw new Exception(
            "Only approved pickup requests can be assigned."
        );
    }

    $user_id = (int)$activity['user_id'];
    $scrap_type = $activity['scrap_type'];

    // --------------------------------------------------
    // STEP 2:
    // GET COLLECTOR
    // --------------------------------------------------

    $stmt = $conn->prepare("
        SELECT
            collector_id,
            name,
            availability_status,
            verification_status
        FROM scrapcollector
        WHERE collector_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param(
        "i",
        $collector_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        throw new Exception(
            "Collector not found."
        );
    }

    $collector = $result->fetch_assoc();

    $stmt->close();

    $collector_name = $collector['name'];

    // --------------------------------------------------
    // CHECK COLLECTOR APPROVAL
    // --------------------------------------------------

    if (
        $collector['verification_status'] !== "Approved"
    ) {

        throw new Exception(
            "Collector is not approved."
        );
    }

    // --------------------------------------------------
    // CHECK COLLECTOR AVAILABILITY
    // --------------------------------------------------

    if (
        $collector['availability_status'] !== "Available"
    ) {

        throw new Exception(
            "Collector is currently busy."
        );
    }

    // --------------------------------------------------
    // STEP 3:
    // ASSIGN COLLECTOR
    // --------------------------------------------------

    $stmt = $conn->prepare("
        UPDATE activity
        SET
            collector_id = ?,
            status = 'Assigned'
        WHERE activity_id = ?
    ");

    $stmt->bind_param(
        "ii",
        $collector_id,
        $activity_id
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {

        throw new Exception(
            "Failed to update pickup activity assignment."
        );
    }

    $stmt->close();

    // --------------------------------------------------
    // STEP 4:
    // MARK COLLECTOR BUSY
    // --------------------------------------------------

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

    if ($stmt->affected_rows !== 1) {

        throw new Exception(
            "Failed to update collector availability status."
        );
    }

    $stmt->close();

    // ==================================================
    // STEP 5:
    // 🔔 NOTIFY COLLECTOR
    // ==================================================

    $recipient_type = "Collector";

    $notification_type = "pickup_assigned";

    $title = "New Pickup Assigned";

    $message =
        "You have been assigned pickup request #" .
        $activity_id .
        " for " .
        $scrap_type .
        ".";

    $reference_id = $activity_id;

    $reference_type = "activity";

    $is_read = 0;

    $notificationStmt = $conn->prepare("
        INSERT INTO notifications
        (
            recipient_type,
            recipient_id,
            notification_type,
            title,
            message,
            reference_id,
            reference_type,
            is_read
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $notificationStmt->bind_param(
        "sisssisi",
        $recipient_type,
        $collector_id,
        $notification_type,
        $title,
        $message,
        $reference_id,
        $reference_type,
        $is_read
    );

    $notificationStmt->execute();

    $notificationStmt->close();

    // ==================================================
    // STEP 6:
    // 🔔 NOTIFY USER
    // ==================================================

    $recipient_type = "User";

    $notification_type = "collector_assigned";

    $title = "Collector Assigned";

    $message =
        "A scrap collector (" .
        $collector_name .
        ") has been assigned to your " .
        $scrap_type .
        " pickup request #" .
        $activity_id .
        ".";

    $reference_id = $activity_id;

    $reference_type = "activity";

    $is_read = 0;

    $notificationStmt = $conn->prepare("
        INSERT INTO notifications
        (
            recipient_type,
            recipient_id,
            notification_type,
            title,
            message,
            reference_id,
            reference_type,
            is_read
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $notificationStmt->bind_param(
        "sisssisi",
        $recipient_type,
        $user_id,
        $notification_type,
        $title,
        $message,
        $reference_id,
        $reference_type,
        $is_read
    );

    $notificationStmt->execute();

    $notificationStmt->close();

    // --------------------------------------------------
    // STEP 7:
    // COMMIT EVERYTHING
    // --------------------------------------------------

    $conn->commit();

    $_SESSION['msg'] =
        "Collector assigned successfully. " .
        "Both the collector and user have been notified.";

} catch (Exception $e) {

    // --------------------------------------------------
    // ROLLBACK IF ANYTHING FAILS
    // --------------------------------------------------

    try {
        $conn->rollback();
    } catch (Exception $rollbackError) {
        // Ignore rollback error
    }

    $_SESSION['error'] =
        "Assignment failed: " .
        $e->getMessage();
}

// --------------------------------------------------
// REDIRECT
// --------------------------------------------------

header("Location: manage.php");
exit();

?>