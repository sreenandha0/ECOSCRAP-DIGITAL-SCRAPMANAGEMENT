<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// Allow POST only
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    redirect("register.php");
}

// Get Data
$name        = sanitize($_POST['name']);
$email       = sanitize($_POST['email']);
$phone       = sanitize($_POST['phone']);
$vehicle_no  = strtoupper(sanitize($_POST['vehicle_no']));
$pincode     = sanitize($_POST['pincode']);

$password    = $_POST['password'];
$confirm     = $_POST['confirm_password'];


// -----------------------------
// Validation
// -----------------------------

if (
    empty($name) ||
    empty($email) ||
    empty($phone) ||
    empty($vehicle_no) ||
    empty($pincode) ||
    empty($password) ||
    empty($confirm)
) {
    setMessage("danger", "Please fill all required fields.");
    redirect("register.php");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setMessage("danger", "Invalid email address.");
    redirect("register.php");
}

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    setMessage("danger", "Phone number must be 10 digits.");
    redirect("register.php");
}

if (!preg_match('/^[0-9]{6}$/', $pincode)) {
    setMessage("danger", "Pincode must be 6 digits.");
    redirect("register.php");
}

if (strlen($password) < 8) {
    setMessage("danger", "Password must contain at least 8 characters.");
    redirect("register.php");
}

if ($password != $confirm) {
    setMessage("danger", "Passwords do not match.");
    redirect("register.php");
}


// -----------------------------
// Duplicate Email
// -----------------------------

$stmt = $conn->prepare("
    SELECT collector_id
    FROM scrapcollector
    WHERE email = ?
");

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {

    $stmt->close();

    setMessage("danger", "Email already registered.");
    redirect("register.php");
}

$stmt->close();


// -----------------------------
// Duplicate Phone
// -----------------------------

$stmt = $conn->prepare("
    SELECT collector_id
    FROM scrapcollector
    WHERE phone = ?
");

$stmt->bind_param("s", $phone);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {

    $stmt->close();

    setMessage("danger", "Phone number already exists.");
    redirect("register.php");
}

$stmt->close();


// -----------------------------
// Duplicate Vehicle
// -----------------------------

$stmt = $conn->prepare("
    SELECT collector_id
    FROM scrapcollector
    WHERE vehicle_no = ?
");

$stmt->bind_param("s", $vehicle_no);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {

    $stmt->close();

    setMessage("danger", "Vehicle number already exists.");
    redirect("register.php");
}

$stmt->close();


// -----------------------------
// Hash Password
// -----------------------------

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);


// -----------------------------
// Insert Collector
// -----------------------------

$stmt = $conn->prepare("

    INSERT INTO scrapcollector
    (
        name,
        email,
        password,
        phone,
        vehicle_no,
        pincode,
        availability_status,
        verification_status,
        completed_pickups
    )

    VALUES
    (
        ?, ?, ?, ?, ?, ?,
        'Offline',
        'Pending',
        0
    )

");

$stmt->bind_param(
    "ssssss",
    $name,
    $email,
    $hashedPassword,
    $phone,
    $vehicle_no,
    $pincode
);


// -----------------------------
// Execute Registration
// -----------------------------

if ($stmt->execute()) {

    // Get newly created collector ID
    $collector_id = $stmt->insert_id;


    // -----------------------------------------
    // Create Admin Notification
    // -----------------------------------------

    $notification_type = "collector_registered";

    $notification_title = "New Collector Registration";

    $notification_message =
        $name .
        " has registered as a scrap collector and is waiting for approval.";

    $recipient_type = "Admin";

    // Your admin table currently has admin_id = 1
    $recipient_id = 1;

    $reference_type = "collector";


    $notification_stmt = $conn->prepare("

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
        (
            ?, ?, ?, ?, ?, ?, ?, 0
        )

    ");


    if ($notification_stmt) {

        $notification_stmt->bind_param(
            "sisssis",
            $recipient_type,
            $recipient_id,
            $notification_type,
            $notification_title,
            $notification_message,
            $collector_id,
            $reference_type
        );

        $notification_stmt->execute();

        $notification_stmt->close();
    }


    // -----------------------------------------
    // Registration Success
    // -----------------------------------------

    setMessage(
        "success",
        "Registration successful. Your account is waiting for administrator approval."
    );

    redirect("../login.php");


} else {

    // -----------------------------------------
    // Registration Failed
    // -----------------------------------------

    setMessage(
        "danger",
        "Registration failed. Please try again."
    );

    redirect("register.php");
}


// -----------------------------
// Close Connections
// -----------------------------

$stmt->close();
$conn->close();

?>