<?php

session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// ==========================================================
// 1. AUTHORIZATION CHECK
// ==========================================================
if (
    !isset($_SESSION['collector_id']) ||
    ($_SESSION['role'] ?? '') !== "Collector"
) {
    redirect("../login.php");
}

// ==========================================================
// 2. REQUEST METHOD & CSRF VERIFICATION
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: assigned_requests.php");
    exit();
}

verifyCsrfToken();

$activity_id  = (int) ($_POST['id'] ?? 0);
$collector_id = (int) $_SESSION['collector_id'];

if ($activity_id <= 0 || $collector_id <= 0) {
    $_SESSION['error'] = "Invalid pickup request.";
    header("Location: assigned_requests.php");
    exit();
}

try {

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn->begin_transaction();

    // ==========================================================
    // STEP 1: VERIFY ASSIGNED PICKUP & GET USER ID
    // ==========================================================
    $stmt = $conn->prepare("
        SELECT 
            activity_id,
            user_id
        FROM activity
        WHERE activity_id = ?
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
            "Pickup request not found or is no longer available for rejection."
        );
    }

    $pickup = $result->fetch_assoc();

    $user_id = (int) $pickup['user_id'];

    $stmt->close();


    // ==========================================================
    // STEP 2: GET SCRAP COLLECTOR NAME
    // ==========================================================
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
        throw new Exception("Scrap collector account not found.");
    }

    $collector = $collectorResult->fetch_assoc();

    $collector_name = $collector['name'];

    $stmt->close();


    // ==========================================================
    // STEP 3: UPDATE PICKUP STATUS
    // ==========================================================
    $stmt = $conn->prepare("
        UPDATE activity
        SET status = 'Rejected'
        WHERE activity_id = ?
          AND collector_id = ?
          AND status = 'Assigned'
    ");

    $stmt->bind_param(
        "ii",
        $activity_id,
        $collector_id
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception(
            "Failed to reject pickup request."
        );
    }

    $stmt->close();


    // ==========================================================
    // STEP 4: MAKE SCRAP COLLECTOR AVAILABLE
    // ==========================================================
    $stmt = $conn->prepare("
        UPDATE scrapcollector
        SET availability_status = 'Available'
        WHERE collector_id = ?
    ");

    $stmt->bind_param(
        "i",
        $collector_id
    );

    $stmt->execute();

    $stmt->close();


    // ==========================================================
    // STEP 5: NOTIFY USER
    // ==========================================================
    $user_title = "Pickup Request Rejected";

    $user_message =
        "Your pickup request #{$activity_id} has been rejected by the assigned scrap collector ({$collector_name}). The admin may reassign your request.";

    $notification_type = "pickup_rejected";
    $reference_type = "activity";
    $is_read = 0;

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


    // ==========================================================
    // STEP 6: NOTIFY ADMIN
    // ==========================================================
    $admin_id = 1;

    $admin_title = "Pickup Request Rejected";

    $admin_message =
        "Scrap collector {$collector_name} has rejected pickup request #{$activity_id}. The request may require reassignment.";

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

    $stmt->close();


    // ==========================================================
    // STEP 7: COMMIT
    // ==========================================================
    $conn->commit();

    $_SESSION['msg'] =
        "Pickup request rejected successfully. The user and admin have been notified.";

    header("Location: assigned_requests.php?success=rejected");
    exit();


} catch (Exception $e) {

    try {
        $conn->rollback();
    } catch (Exception $rollbackError) {
        // Ignore rollback error
    }

    $_SESSION['error'] =
        "Failed to reject pickup: " . $e->getMessage();

    header("Location: assigned_requests.php");
    exit();
}
?>