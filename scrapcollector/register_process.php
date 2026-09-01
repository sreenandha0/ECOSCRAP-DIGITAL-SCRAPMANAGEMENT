<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// Allow POST only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("register.php");
}

// -----------------------------
// Get Data Safely
// -----------------------------

$name       = sanitize($_POST['name'] ?? '');
$email      = sanitize($_POST['email'] ?? '');
$phone      = sanitize($_POST['phone'] ?? '');
$vehicle_no = strtoupper(str_replace(' ', '', sanitize($_POST['vehicle_no'] ?? '')));
$pincode    = sanitize($_POST['pincode'] ?? '');

$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';


// -----------------------------
// Required Field Validation
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


// -----------------------------
// Name Validation
// -----------------------------

if (!preg_match('/^[a-zA-Z\s.]{2,100}$/', $name)) {
    setMessage("danger", "Please enter a valid name.");
    redirect("register.php");
}


// -----------------------------
// Email Validation
// -----------------------------

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setMessage("danger", "Please enter a valid email address.");
    redirect("register.php");
}


// -----------------------------
// Phone Validation
// -----------------------------

if (!preg_match('/^[0-9]{10}$/', $phone)) {
    setMessage("danger", "Phone number must be exactly 10 digits.");
    redirect("register.php");
}


// -----------------------------
// Pincode Validation
// -----------------------------

if (!preg_match('/^[0-9]{6}$/', $pincode)) {
    setMessage("danger", "Pincode must be exactly 6 digits.");
    redirect("register.php");
}


// -----------------------------
// Vehicle Number Validation
// -----------------------------
// Example: KL01AB1234

if (!preg_match('/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}$/', $vehicle_no)) {
    setMessage("danger", "Please enter a valid vehicle number.");
    redirect("register.php");
}


// -----------------------------
// Password Validation
// -----------------------------

if (strlen($password) < 8) {
    setMessage("danger", "Password must contain at least 8 characters.");
    redirect("register.php");
}


// -----------------------------
// Confirm Password
// -----------------------------

if ($password !== $confirm) {
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

    $collector_id = $stmt->insert_id;

    // -----------------------------
    // Create Admin Notification
    // -----------------------------

    $notification_type = "collector_registered";
    $notification_title = "New Collector Registration";

    $notification_message =
        $name .
        " has registered as a scrap collector and is waiting for approval.";

    $recipient_type = "Admin";
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

    // -----------------------------
    // Registration Success
    // -----------------------------

    $stmt->close();
    $conn->close();

    setMessage(
        "success",
        "Registration successful. Your account is waiting for administrator approval."
    );

    redirect("../login.php");

} else {

    // -----------------------------
    // Registration Failed
    // -----------------------------

    $stmt->close();
    $conn->close();

    setMessage(
        "danger",
        "Registration failed. Please try again."
    );

    redirect("register.php");
}
?>