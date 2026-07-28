<?php
session_start();

require_once 'includes/db.php';

// If Collector, set Offline
if (isset($_SESSION['role'], $_SESSION['collector_id']) && $_SESSION['role'] === "Collector") {

    $stmt = $conn->prepare("
        UPDATE scrapcollector
        SET availability_status='Offline'
        WHERE collector_id=?
    ");

    $collectorId = (int) $_SESSION['collector_id'];
    $stmt->bind_param("i", $collectorId);
    $stmt->execute();
    $stmt->close();
}

session_unset();
session_destroy();

header("Location: login.php");
exit();
