<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// 1. Authorize Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Admin") {
    header("Location: ../login.php");
    exit();
}

// 2. Process Request
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $activity_id = intval($_GET['id']);

    $stmt = $conn->prepare("UPDATE activity SET status = 'Approved' WHERE activity_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("i", $activity_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Pickup Request #{$activity_id} has been approved.";
        } else {
            $_SESSION['error'] = "Failed to update record: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Database preparation error: " . $conn->error;
    }
} else {
    $_SESSION['error'] = "Invalid or missing activity ID.";
}

// 3. Redirect back to manage.php
header("Location: manage.php");
exit();