<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Allow POST only
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: create_request.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --------------------
// Get Form Data
// --------------------

$scrap_type = trim($_POST['scrap_type']);
$scrap_weight = trim($_POST['scrap_weight']);
$pickup_address = trim($_POST['pickup_address']);
$pickup_pincode = trim($_POST['pickup_pincode']);
$preferred_pickup_date = $_POST['preferred_pickup_date'];
$pickup_time = $_POST['pickup_time'];
$remarks = trim($_POST['remarks']);

// --------------------
// Validation
// --------------------

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

if ($scrap_weight <= 0) {
    setMessage("danger", "Invalid scrap weight.");
    header("Location: create_request.php");
    exit();
}

if ($preferred_pickup_date < date("Y-m-d")) {
    setMessage("danger", "Pickup date cannot be in the past.");
    header("Location: create_request.php");
    exit();
}

// --------------------
// Upload Image
// --------------------

$image = NULL;

if (isset($_FILES['scrap_image']) && $_FILES['scrap_image']['error'] == 0) {

    $allowed = ['jpg','jpeg','png'];

    $extension = strtolower(pathinfo($_FILES['scrap_image']['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed)) {

        setMessage("danger","Only JPG, JPEG and PNG files are allowed.");
        header("Location: create_request.php");
        exit();

    }

    if ($_FILES['scrap_image']['size'] > 5 * 1024 * 1024) {

        setMessage("danger","Image size should be below 5MB.");
        header("Location: create_request.php");
        exit();

    }

    $image = uniqid("scrap_") . "." . $extension;

    move_uploaded_file(
        $_FILES['scrap_image']['tmp_name'],
        "../uploads/scrap/" . $image
    );
}

// --------------------
// Insert Request
// --------------------

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
(
?,?,?,?,?,?,?,?,?,?
)
");

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

if($stmt->execute()){

    // Optional Audit Log
    $activity_id = $conn->insert_id;

    $role = "User";
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
    (?,?,?,?,?)
    ");

    $log->bind_param(
    "isiss",
    $activity_id,
    $role,
    $user_id,
    $action,
    $description
    );

    $log->execute();

    setMessage(
        "success",
        "Pickup request submitted successfully."
    );

    header("Location: dashboard.php");
    exit();

}else{

    echo "Database Error : " . $stmt->error;

}

$stmt->close();
$conn->close();
?>