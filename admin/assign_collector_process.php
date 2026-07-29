<?php
// Enable strict error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// 1. Authorization & Request Verification
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Admin") {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage.php");
    exit();
}

$activity_id = (int)($_POST['activity_id'] ?? 0);
$collector_id = (int)($_POST['collector_id'] ?? 0);

if ($activity_id <= 0 || $collector_id <= 0) {
    $_SESSION['error'] = "Invalid request payload.";
    header("Location: manage.php");
    exit();
}

try {
    // Enable mysqli exception mode to catch standard SQL errors cleanly
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn->begin_transaction();

    /*
    ==========================================================
    STEP 1: Verify Pickup Request
    ==========================================================
    */
    $stmt = $conn->prepare("
        SELECT status
        FROM activity
        WHERE activity_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Pickup request not found.");
    }

    $activity = $result->fetch_assoc();
    if ($activity['status'] !== "Approved") {
        throw new Exception("Only approved pickup requests can be assigned.");
    }
    $stmt->close();

    /*
    ==========================================================
    STEP 2: Verify Collector Availability
    ==========================================================
    */
    $stmt = $conn->prepare("
        SELECT availability_status, verification_status
        FROM scrapcollector
        WHERE collector_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $collector_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Collector not found.");
    }

    $collector = $result->fetch_assoc();
    if ($collector['verification_status'] !== "Approved") {
        throw new Exception("Collector is not approved.");
    }

    if ($collector['availability_status'] !== "Available") {
        throw new Exception("Collector is currently busy.");
    }
    $stmt->close();

    /*
    ==========================================================
    STEP 3: Assign Collector (Status -> 'Assigned')
    ==========================================================
    */
    $stmt = $conn->prepare("
        UPDATE activity
        SET
            collector_id = ?,
            status = 'Assigned'
        WHERE activity_id = ?
    ");
    $stmt->bind_param("ii", $collector_id, $activity_id);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception("Failed to update pickup activity assignment.");
    }
    $stmt->close();

    /*
    ==========================================================
    STEP 4: Mark Collector as Reserved/Busy
    ==========================================================
    */
    $stmt = $conn->prepare("
        UPDATE scrapcollector
        SET availability_status = 'Busy'
        WHERE collector_id = ?
    ");
    $stmt->bind_param("i", $collector_id);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception("Failed to update collector availability status.");
    }
    $stmt->close();

    /*
    ==========================================================
    STEP 5: Commit Transaction
    ==========================================================
    */
    $conn->commit();
    $_SESSION['msg'] = "Collector assigned successfully. Waiting for collector acceptance.";

} catch (Exception $e) {
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }

    $_SESSION['error'] = "Assignment failed: " . $e->getMessage();
}

header("Location: manage.php");
exit();