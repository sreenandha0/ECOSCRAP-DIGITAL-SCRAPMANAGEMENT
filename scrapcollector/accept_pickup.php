<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/phpqrcode/lib/qrlib.php";

// 1. Authorization Check
if (!isset($_SESSION['collector_id']) || ($_SESSION['role'] ?? '') !== "Collector") {
    redirect("../login.php");
}

// 2. Request Method & CSRF Verification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: assigned_requests.php");
    exit();
}

verifyCsrfToken();

$activity_id  = (int) ($_POST['id'] ?? 0);
$collector_id = (int) $_SESSION['collector_id'];

if ($activity_id <= 0 || $collector_id <= 0) {
    $_SESSION['error'] = "Invalid pickup request payload.";
    header("Location: assigned_requests.php");
    exit();
}

$qrFile = "";

try {
    // Enable mysqli exception mode to catch SQL errors in the catch block
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn->begin_transaction();

    /*
    ==========================================================
    STEP 1: Verify & Lock Pickup Request
    ==========================================================
    */
    $stmt = $conn->prepare("
        SELECT activity_id
        FROM activity
        WHERE activity_id = ?
        AND collector_id = ?
        AND status = 'Assigned'
        FOR UPDATE
    ");
    $stmt->bind_param("ii", $activity_id, $collector_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Pickup request not found or no longer available for acceptance.");
    }
    $stmt->close();

    /*
    ==========================================================
    STEP 2: Generate Physical QR Code Image in uploads/qr/
    ==========================================================
    */
    // Use __DIR__ to guarantee path resolution regardless of working directory
    $qrFolder = __DIR__ . "/../uploads/qr/";
    
    if (!file_exists($qrFolder)) {
        if (!mkdir($qrFolder, 0755, true) && !is_dir($qrFolder)) {
            throw new Exception("Failed to create directory for QR codes: " . $qrFolder);
        }
    }

    $filename = "pickup_" . $activity_id . "_" . time() . ".png";
    $qrFile   = $qrFolder . $filename;

    // Secure payload stored inside the QR code
    $token  = bin2hex(random_bytes(16));
    $qrData = json_encode([
        "activity_id"  => $activity_id,
        "collector_id" => $collector_id,
        "token"        => $token,
        "generated_at" => date("Y-m-d H:i:s")
    ]);

    // Render image directly into target directory
    QRcode::png($qrData, $qrFile, QR_ECLEVEL_L, 5);

    if (!file_exists($qrFile) || filesize($qrFile) === 0) {
        throw new Exception("QR code generation failed on disk.");
    }

    /*
    ==========================================================
    STEP 3: Update Activity Status & Attach QR
    ==========================================================
    */
    $stmt = $conn->prepare("
        UPDATE activity
        SET status = 'In Progress',
            qr_code = ?,
            qr_status = 'Unused'
        WHERE activity_id = ?
        AND collector_id = ?
    ");
    $stmt->bind_param("sii", $filename, $activity_id, $collector_id);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception("Failed to update activity status.");
    }
    $stmt->close();

    /*
    ==========================================================
    STEP 4: Update Collector Availability Status
    ==========================================================
    */
    $stmt = $conn->prepare("
        UPDATE scrapcollector
        SET availability_status = 'Busy'
        WHERE collector_id = ?
    ");
    $stmt->bind_param("i", $collector_id);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();

    $_SESSION['msg'] = "Pickup accepted! QR code generated for the user.";
    header("Location: assigned_requests.php?success=started");
    exit();

} catch (Exception $e) {
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }

    // Remove orphan QR image file if database transaction fails
    if (!empty($qrFile) && file_exists($qrFile)) {
        unlink($qrFile);
    }

    $_SESSION['error'] = "Failed to accept pickup: " . $e->getMessage();
    header("Location: assigned_requests.php");
    exit();
}