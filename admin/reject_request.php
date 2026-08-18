<?php

session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// --------------------------------------------------
// 1. AUTHORIZE ADMIN
// --------------------------------------------------

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== "Admin"
) {
    header("Location: ../login.php");
    exit();
}

// --------------------------------------------------
// 2. CHECK ACTIVITY ID
// --------------------------------------------------

if (!isset($_GET['id']) || empty($_GET['id'])) {

    $_SESSION['error'] = "Invalid or missing activity ID.";

    header("Location: manage.php");
    exit();
}

$activity_id = intval($_GET['id']);

if ($activity_id <= 0) {

    $_SESSION['error'] = "Invalid pickup request ID.";

    header("Location: manage.php");
    exit();
}

// --------------------------------------------------
// 3. GET USER ID + REQUEST DETAILS
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        user_id,
        scrap_type,
        status
    FROM activity
    WHERE activity_id = ?
    LIMIT 1
");

if (!$stmt) {

    $_SESSION['error'] =
        "Database preparation error: " . $conn->error;

    header("Location: manage.php");
    exit();
}

$stmt->bind_param("i", $activity_id);
$stmt->execute();

$result = $stmt->get_result();

$request = $result->fetch_assoc();

$stmt->close();

// --------------------------------------------------
// REQUEST NOT FOUND
// --------------------------------------------------

if (!$request) {

    $_SESSION['error'] = "Pickup request not found.";

    header("Location: manage.php");
    exit();
}

$user_id = (int) $request['user_id'];
$scrap_type = $request['scrap_type'];

// --------------------------------------------------
// 4. UPDATE REQUEST STATUS
// --------------------------------------------------

$stmt = $conn->prepare("
    UPDATE activity
    SET status = 'Rejected'
    WHERE activity_id = ?
");

if (!$stmt) {

    $_SESSION['error'] =
        "Database preparation error: " . $conn->error;

    header("Location: manage.php");
    exit();
}

$stmt->bind_param("i", $activity_id);

if (!$stmt->execute()) {

    $_SESSION['error'] =
        "Failed to update record: " . $stmt->error;

    $stmt->close();

    header("Location: manage.php");
    exit();
}

$stmt->close();

// --------------------------------------------------
// 5. CREATE USER NOTIFICATION
// --------------------------------------------------

$recipient_type = "User";
$recipient_id = $user_id;

$notification_type = "request_rejected";

$title = "Pickup Request Rejected";

$message =
    "Your " .
    $scrap_type .
    " pickup request #" .
    $activity_id .
    " has been rejected by the administrator.";

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

if ($notificationStmt) {

    $notificationStmt->bind_param(
        "sisssisi",
        $recipient_type,
        $recipient_id,
        $notification_type,
        $title,
        $message,
        $reference_id,
        $reference_type,
        $is_read
    );

    $notificationStmt->execute();

    $notificationStmt->close();
}

// --------------------------------------------------
// 6. SUCCESS MESSAGE
// --------------------------------------------------

$_SESSION['success'] =
    "Pickup Request #{$activity_id} has been rejected and the user has been notified.";

// --------------------------------------------------
// 7. REDIRECT
// --------------------------------------------------

header("Location: manage.php");
exit();

?>