<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['collector_id']) || ($_SESSION['role'] ?? '') !== 'Collector') {
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile.php');
}

$collectorId = (int) $_SESSION['collector_id'];
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$vehicleNo = strtoupper(trim($_POST['vehicle_no'] ?? ''));
$pincode = trim($_POST['pincode'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($name === '' || !preg_match('/^[0-9]{10}$/', $phone) || $vehicleNo === '' || !preg_match('/^[0-9]{6}$/', $pincode)) {
    setMessage('danger', 'Please provide a name, valid phone number, vehicle number, and pincode.');
    redirect('profile.php');
}

if ($password !== '' && (strlen($password) < 8 || $password !== $confirmPassword)) {
    setMessage('danger', 'New passwords must match and contain at least 8 characters.');
    redirect('profile.php');
}

$imageName = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK || $_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
        setMessage('danger', 'Profile image upload failed or exceeds 2 MB.');
        redirect('profile.php');
    }

    $imageInfo = @getimagesize($_FILES['profile_image']['tmp_name']);
    $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($imageInfo === false || !isset($allowedTypes[$imageInfo[2]])) {
        setMessage('danger', 'Please upload a valid JPG, PNG, or WEBP image.');
        redirect('profile.php');
    }

    $folder = '../uploads/profile/';
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        setMessage('danger', 'Unable to prepare profile image storage.');
        redirect('profile.php');
    }

    $imageName = 'collector_' . bin2hex(random_bytes(12)) . '.' . $allowedTypes[$imageInfo[2]];
    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $folder . $imageName)) {
        setMessage('danger', 'Unable to save the profile image.');
        redirect('profile.php');
    }
}

$updates = 'name = ?, phone = ?, vehicle_no = ?, pincode = ?';
$types = 'ssss';
$params = [$name, $phone, $vehicleNo, $pincode];

if ($imageName !== null) {
    $updates .= ', profile_image = ?';
    $types .= 's';
    $params[] = $imageName;
}

if ($password !== '') {
    $updates .= ', password = ?';
    $types .= 's';
    $params[] = password_hash($password, PASSWORD_DEFAULT);
}

$types .= 'i';
$params[] = $collectorId;
$stmt = $conn->prepare("UPDATE scrapcollector SET $updates WHERE collector_id = ?");
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    setMessage('danger', 'Unable to update your profile.');
    $stmt->close();
    redirect('profile.php');
}

$stmt->close();
$_SESSION['name'] = $name;
setMessage('success', 'Your profile has been updated.');
redirect('profile.php');
