<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Unauthorized access.");
}

require_once "../config/app.php";
require_once "../includes/db.php";
require_once "../includes/phpqrcode/lib/qrlib.php";

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id    = $_SESSION['user_id'];

if ($request_id <= 0) {
    http_response_code(400);
    die("Invalid Request ID.");
}

// Verify request belongs to logged in user
$sql = "SELECT activity_id FROM activity WHERE activity_id=? AND user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    http_response_code(404);
    die("Pickup request not found or access denied.");
}

$stmt->close();

// Collector verification page
$qrData = BASE_URL . "/ecoscrap/scrapcollector/verify_qr.php?id=" . $request_id;

// Output QR Image
header("Content-Type: image/png");
header("Cache-Control: no-cache, must-revalidate");

QRcode::png(
    $qrData,
    false,
    QR_ECLEVEL_M,
    6,
    2
);

exit();