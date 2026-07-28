<?php
session_start();

require_once 'db.php';
require_once 'functions.php';

// Allow only POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("../login.php");
}

// Get form data
$email = sanitize(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

// Validate
if (empty($email) || empty($password)) {
    setMessage("danger", "Please enter your email and password.");
    redirect("../login.php");
}

/* ======================================================
   1. ADMIN LOGIN
====================================================== */

$stmt = $conn->prepare("SELECT * FROM admin WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {

    $admin = $result->fetch_assoc();

    if (password_verify($password, $admin['password'])) {

        session_regenerate_id(true);

        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['name'] = $admin['name'];
        $_SESSION['email'] = $admin['email'];
        $_SESSION['role'] = "Admin";

        $stmt->close();

        redirect("../admin/dashboard.php");
    }
}

$stmt->close();

/* ======================================================
   2. USER LOGIN
====================================================== */

$stmt = $conn->prepare("SELECT * FROM user WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = "User";

        $stmt->close();

        redirect("../user/dashboard.php");
    }
}

$stmt->close();

/* ======================================================
   3. SCRAP COLLECTOR LOGIN
====================================================== */

$stmt = $conn->prepare("SELECT * FROM scrapcollector WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {

    $collector = $result->fetch_assoc();

    // Verify password first
    if (password_verify($password, $collector['password'])) {

        // Check approval
        if ($collector['verification_status'] != "Approved") {

            setMessage(
                "warning",
                "Your collector account is waiting for administrator approval."
            );

            redirect("../login.php");
        }

        session_regenerate_id(true);

        $_SESSION['collector_id'] = $collector['collector_id'];
        $_SESSION['name'] = $collector['name'];
        $_SESSION['email'] = $collector['email'];
        $_SESSION['role'] = "Collector";

        // Automatically mark collector available
        $update = $conn->prepare("
            UPDATE scrapcollector
            SET availability_status='Available'
            WHERE collector_id=?
        ");

        $update->bind_param("i", $collector['collector_id']);
        $update->execute();
        $update->close();

        $stmt->close();

        redirect("../scrapcollector/dashboard.php");
    }
}

$stmt->close();

/* ======================================================
   INVALID LOGIN
====================================================== */

setMessage("danger", "Invalid email or password.");
redirect("../login.php");