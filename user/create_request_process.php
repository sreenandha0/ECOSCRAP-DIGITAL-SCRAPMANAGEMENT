<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// --------------------------------------------------
// CHECK LOGIN
// --------------------------------------------------

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'User'
) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// --------------------------------------------------
// POST ONLY
// --------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: create_request.php");
    exit();
}

// --------------------------------------------------
// GET FORM DATA
// --------------------------------------------------

$scrap_type = trim($_POST['scrap_type'] ?? '');
$scrap_weight = trim($_POST['scrap_weight'] ?? '');
$pickup_address = trim($_POST['pickup_address'] ?? '');
$pickup_pincode = trim($_POST['pickup_pincode'] ?? '');
$preferred_pickup_date = $_POST['preferred_pickup_date'] ?? '';
$pickup_time = $_POST['pickup_time'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

// --------------------------------------------------
// VALIDATION
// --------------------------------------------------

if (
    empty($scrap_type) ||
    empty($scrap_weight) ||
    empty($pickup_address) ||
    empty($pickup_pincode) ||
    empty($preferred_pickup_date) ||
    empty($pickup_time)
) {
    setMessage("danger", "Please fill all required fields.");
    header("Location: create_request.php");
    exit();
}

if (!is_numeric($scrap_weight) || $scrap_weight <= 0) {
    setMessage("danger", "Invalid scrap weight.");
    header("Location: create_request.php");
    exit();
}

if (!preg_match('/^[0-9]{6}$/', $pickup_pincode)) {
    setMessage("danger", "Pincode must be 6 digits.");
    header("Location: create_request.php");
    exit();
}

if ($preferred_pickup_date < date("Y-m-d")) {
    setMessage("danger", "Pickup date cannot be in the past.");
    header("Location: create_request.php");
    exit();
}

// --------------------------------------------------
// UPLOAD SCRAP IMAGE
// --------------------------------------------------

$image = NULL;

if (
    isset($_FILES['scrap_image']) &&
    $_FILES['scrap_image']['error'] !== UPLOAD_ERR_NO_FILE
) {

    if ($_FILES['scrap_image']['error'] !== UPLOAD_ERR_OK) {
        setMessage("danger", "There was an error uploading the image.");
        header("Location: create_request.php");
        exit();
    }

    $allowed = ['jpg', 'jpeg', 'png'];

    $extension = strtolower(
        pathinfo($_FILES['scrap_image']['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($extension, $allowed, true)) {
        setMessage(
            "danger",
            "Only JPG, JPEG and PNG files are allowed."
        );
        header("Location: create_request.php");
        exit();
    }

    if ($_FILES['scrap_image']['size'] > 5 * 1024 * 1024) {
        setMessage(
            "danger",
            "Image size should be below 5MB."
        );
        header("Location: create_request.php");
        exit();
    }

    $uploadDirectory = "../uploads/scrap/";

    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0777, true);
    }

    $image = uniqid("scrap_", true) . "." . $extension;

    if (
        !move_uploaded_file(
            $_FILES['scrap_image']['tmp_name'],
            $uploadDirectory . $image
        )
    ) {
        setMessage(
            "danger",
            "Failed to upload scrap image."
        );
        header("Location: create_request.php");
        exit();
    }
}

// --------------------------------------------------
// INSERT PICKUP REQUEST
// --------------------------------------------------

$status = "Pending";

$stmt = $conn->prepare("
    INSERT INTO activity
    (
        user_id,
        scrap_type,
        scrap_weight,
        scrap_image,
        pickup_address,
        pickup_pincode,
        preferred_pickup_date,
        pickup_time,
        remarks,
        status
    )
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    setMessage(
        "danger",
        "Database error: " . $conn->error
    );
    header("Location: create_request.php");
    exit();
}

$stmt->bind_param(
    "isdsssssss",
    $user_id,
    $scrap_type,
    $scrap_weight,
    $image,
    $pickup_address,
    $pickup_pincode,
    $preferred_pickup_date,
    $pickup_time,
    $remarks,
    $status
);

if (!$stmt->execute()) {

    setMessage(
        "danger",
        "Failed to submit pickup request."
    );

    $stmt->close();
    header("Location: create_request.php");
    exit();
}

// Get newly created activity ID
$activity_id = $conn->insert_id;

$stmt->close();

// --------------------------------------------------
// GET USER NAME
// --------------------------------------------------

$user_name = "A user";

$userStmt = $conn->prepare("
    SELECT name
    FROM `user`
    WHERE user_id = ?
    LIMIT 1
");

if ($userStmt) {

    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();

    $userResult = $userStmt->get_result();

    if ($userRow = $userResult->fetch_assoc()) {
        $user_name = $userRow['name'];
    }

    $userStmt->close();
}

// --------------------------------------------------
// CREATE AUDIT LOG
// --------------------------------------------------

$actor_role = "User";
$action = "Created Pickup Request";
$description = "User submitted a new pickup request.";

$log = $conn->prepare("
    INSERT INTO audit_log
    (
        activity_id,
        actor_role,
        actor_id,
        action,
        description
    )
    VALUES
    (?, ?, ?, ?, ?)
");

if ($log) {

    $log->bind_param(
        "isiss",
        $activity_id,
        $actor_role,
        $user_id,
        $action,
        $description
    );

    $log->execute();
    $log->close();
}

// --------------------------------------------------
// 🔔 CREATE ADMIN NOTIFICATION
// --------------------------------------------------

$notification_type = "pickup_request";
$title = "New Pickup Request";

$message = $user_name .
    " has submitted a new " .
    $scrap_type .
    " pickup request.";

$recipient_type = "Admin";
$reference_type = "activity";
$is_read = 0;

// Get all admins
$adminQuery = $conn->query("
    SELECT admin_id
    FROM admin
");

if ($adminQuery) {

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

        while ($admin = $adminQuery->fetch_assoc()) {

            $admin_id = (int) $admin['admin_id'];

            $notificationStmt->bind_param(
                "sisssisi",
                $recipient_type,
                $admin_id,
                $notification_type,
                $title,
                $message,
                $activity_id,
                $reference_type,
                $is_read
            );

            $notificationStmt->execute();
        }

        $notificationStmt->close();
    }
}

// --------------------------------------------------
// SUCCESS
// --------------------------------------------------

setMessage(
    "success",
    "Pickup request submitted successfully."
);

header("Location: dashboard.php");
exit();

?>