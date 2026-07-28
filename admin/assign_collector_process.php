<?php
// Enable strict error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/phpqrcode/lib/qrlib.php";

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

$qrFile = "";

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
    STEP 2: Verify Collector
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
    STEP 3: Single-Source QR Code Generation
    ==========================================================
    */
    $qrFolder = "../qr_codes/";

    if (!file_exists($qrFolder)) {
        if (!mkdir($qrFolder, 0755, true) && !is_dir($qrFolder)) {
            throw new Exception("Failed to create directory for QR codes: " . $qrFolder);
        }
    }

    $filename = "pickup_" . $activity_id . "_" . time() . ".png";
    $qrFile = $qrFolder . $filename;

    $token = bin2hex(random_bytes(16));
    $qrData = json_encode([
        "activity_id"  => $activity_id,
        "collector_id" => $collector_id,
        "token"        => $token,
        "generated_at" => date("Y-m-d H:i:s")
    ]);

    // Generate the QR file
    QRcode::png($qrData, $qrFile, QR_ECLEVEL_L, 5);

    // Verify filesystem output immediately
    if (!file_exists($qrFile) || filesize($qrFile) === 0) {
        throw new Exception("QR code generation failed. File was not created on the filesystem.");
    }

    /*
    ==========================================================
    STEP 4: Assign Collector & Record QR
    ==========================================================
    */
    $stmt = $conn->prepare("
        UPDATE activity
        SET
            collector_id = ?,
            status = 'Assigned',
            qr_code = ?
        WHERE activity_id = ?
    ");
    $stmt->bind_param("isi", $collector_id, $filename, $activity_id);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception("Failed to update pickup activity assignment.");
    }
    $stmt->close();

    /*
    ==========================================================
    STEP 5: Update Collector Status
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
    STEP 6: Commit Transaction
    ==========================================================
    */
    $conn->commit();
    $_SESSION['msg'] = "Collector assigned and QR code generated successfully.";

} catch (Exception $e) {
    // Rollback DB changes on any failure
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }

    // Clean up created orphan QR image file if DB update failed
    if (!empty($qrFile) && file_exists($qrFile)) {
        unlink($qrFile);
    }

    $_SESSION['error'] = "Assignment failed: " . $e->getMessage();
}

header("Location: manage.php");
exit();