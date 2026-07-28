<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "Admin") {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $activity_id = (int) ($_POST['id'] ?? 0);

    $stmt = $conn->prepare("UPDATE activity SET status = 'Rejected' WHERE activity_id = ? AND status = 'Pending'");
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: manage.php");
exit();
