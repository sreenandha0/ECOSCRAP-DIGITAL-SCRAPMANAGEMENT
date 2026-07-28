<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// Authorization Check
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== "User"
) {
    redirect("../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id  = $_SESSION['user_id'];
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $place    = trim($_POST['place'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $state    = trim($_POST['state'] ?? '');
    $pincode  = trim($_POST['pincode'] ?? '');

    $imageUploaded = false;
    $newImageName  = "";

    // Handle Profile Image Upload
    if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {

        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName    = $_FILES['profile_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $folder = "../uploads/profile/";

            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Generate unique filename
            $newImageName = time() . "_" . bin2hex(random_bytes(4)) . "." . $fileExtension;
            $destPath     = $folder . $newImageName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imageUploaded = true;

                // Optional: Delete previous image file if exists
                $prevQuery = $conn->prepare("SELECT profile_image FROM user WHERE user_id = ?");
                $prevQuery->bind_param("i", $user_id);
                $prevQuery->execute();
                $oldImg = $prevQuery->get_result()->fetch_assoc()['profile_image'] ?? '';
                $prevQuery->close();

                if (!empty($oldImg) && file_exists($folder . $oldImg)) {
                    @unlink($folder . $oldImg);
                }
            }
        }
    }

    // Prepare Update Query
    if ($imageUploaded) {
        $sql = "UPDATE user
                SET name = ?,
                    phone = ?,
                    address = ?,
                    place = ?,
                    district = ?,
                    state = ?,
                    pincode = ?,
                    profile_image = ?
                WHERE user_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssi",
            $name,
            $phone,
            $address,
            $place,
            $district,
            $state,
            $pincode,
            $newImageName,
            $user_id
        );
    } else {
        $sql = "UPDATE user
                SET name = ?,
                    phone = ?,
                    address = ?,
                    place = ?,
                    district = ?,
                    state = ?,
                    pincode = ?
                WHERE user_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssi",
            $name,
            $phone,
            $address,
            $place,
            $district,
            $state,
            $pincode,
            $user_id
        );
    }

    $stmt->execute();
    $stmt->close();

    header("Location: profile.php?success=1");
    exit();
} else {
    header("Location: profile.php");
    exit();
}
?>