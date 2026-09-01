<?php
session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Lax"
]);
session_start();

require_once "../includes/db.php";
require_once "../includes/csrf.php";

if (!isset($_SESSION["role"], $_SESSION["user_id"]) || $_SESSION["role"] !== "User") {
    header("Location: ../login.php");
    exit;
}

$userId = filter_var($_SESSION["user_id"], FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1]
]);

if ($userId === false) {
    header("Location: ../login.php");
    exit;
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function profileImageUrl($profileImage): string {
    $filename = basename((string)$profileImage);
    $path = __DIR__ . "/../uploads/profile/" . $filename;

    if ($filename !== "" && is_file($path)) {
        return "../uploads/profile/" . rawurlencode($filename);
    }

    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 132 132'%3E%3Ccircle cx='66' cy='66' r='66' fill='%23d1fae5'/%3E%3Ccircle cx='66' cy='52' r='23' fill='%23047857'/%3E%3Cpath d='M25 117c5-25 20-37 41-37s36 12 41 37' fill='%23047857'/%3E%3C/svg%3E";
}

function removeOldProfileImage($profileImage): void {
    $filename = basename((string)$profileImage);
    $path = __DIR__ . "/../uploads/profile/" . $filename;

    if ($filename !== "" && is_file($path)) {
        @unlink($path);
    }
}

$stmt = $conn->prepare("SELECT user_id, name, email, password, phone, profile_image, address, place, district, state, pincode, created_at FROM user WHERE user_id=? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: ../logout.php");
    exit;
}

$errors = [];
$success = $_SESSION["profile_success"] ?? "";
unset($_SESSION["profile_success"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrfToken();

    if (isset($_POST["update_profile"])) {
        $name = trim((string)($_POST["name"] ?? ""));
        $email = trim((string)($_POST["email"] ?? ""));
        $phone = trim((string)($_POST["phone"] ?? ""));
        $address = trim((string)($_POST["address"] ?? ""));
        $place = trim((string)($_POST["place"] ?? ""));
        $district = trim((string)($_POST["district"] ?? ""));
        $state = trim((string)($_POST["state"] ?? ""));
        $pincode = trim((string)($_POST["pincode"] ?? ""));

        foreach ([
            "name" => $name,
            "email" => $email,
            "phone" => $phone,
            "address" => $address,
            "place" => $place,
            "district" => $district,
            "state" => $state,
            "pincode" => $pincode
        ] as $field => $value) {
            if ($value === "") {
                $errors[$field] = ucfirst($field) . " is required.";
            }
        }

        if (!isset($errors["email"]) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["email"] = "Enter a valid email address.";
        }

        if (!isset($errors["phone"]) && !preg_match("/^[0-9+() .-]{7,20}$/", $phone)) {
            $errors["phone"] = "Enter a valid phone number.";
        }

        if (!isset($errors["pincode"]) && !preg_match("/^[0-9]{4,10}$/", $pincode)) {
            $errors["pincode"] = "Enter a valid pincode.";
        }

        if (!isset($errors["email"])) {
            $check = $conn->prepare("SELECT user_id FROM user WHERE email=? AND user_id!=? LIMIT 1");
            $check->bind_param("si", $email, $userId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $errors["email"] = "This email is already used by another account.";
            }
            $check->close();
        }

        $newImage = (string)($user["profile_image"] ?? "");
        $uploadedPath = "";

        if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES["profile_image"];
            $allowedMime = ["image/jpeg" => "jpg", "image/png" => "png"];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file["tmp_name"]) : false;
            if ($finfo) {
                finfo_close($finfo);
            }

            if ($file["error"] !== UPLOAD_ERR_OK || !isset($allowedMime[$mime])) {
                $errors["profile_image"] = "Only JPG, JPEG, and PNG images are allowed.";
            } elseif ($file["size"] > 2 * 1024 * 1024 || !is_uploaded_file($file["tmp_name"])) {
                $errors["profile_image"] = "Image size must be under 2 MB.";
            } else {
                $directory = __DIR__ . "/../uploads/profile/";
                $filename = "profile_" . $userId . "_" . bin2hex(random_bytes(12)) . "." . $allowedMime[$mime];

                if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
                    $errors["profile_image"] = "Could not prepare the image directory.";
                } elseif (move_uploaded_file($file["tmp_name"], $directory . $filename)) {
                    $newImage = $filename;
                    $uploadedPath = $directory . $filename;
                } else {
                    $errors["profile_image"] = "Could not save the uploaded image.";
                }
            }
        }

        if (!$errors) {
            $update = $conn->prepare("UPDATE user SET name=?,email=?,phone=?,profile_image=?,address=?,place=?,district=?,state=?,pincode=? WHERE user_id=?");
            $update->bind_param("sssssssssi", $name, $email, $phone, $newImage, $address, $place, $district, $state, $pincode, $userId);

            if ($update->execute()) {
                if ($newImage !== ($user["profile_image"] ?? "")) {
                    removeOldProfileImage($user["profile_image"] ?? "");
                }

                $_SESSION["name"] = $name;
                $_SESSION["profile_success"] = "Profile updated successfully.";
                header("Location: profile.php");
                exit;
            }

            if ($uploadedPath !== "") {
                @unlink($uploadedPath);
            }

            $errors["general"] = "Could not update your profile.";
            $update->close();
        }
    }

    if (isset($_POST["change_password"])) {
        $current = (string)($_POST["current_password"] ?? "");
        $new = (string)($_POST["new_password"] ?? "");
        $confirm = (string)($_POST["confirm_password"] ?? "");

        if ($current === "") {
            $errors["current_password"] = "Current password is required.";
        }
        if (strlen($new) < 6) {
            $errors["new_password"] = "New password must be at least 6 characters.";
        }
        if ($new !== $confirm) {
            $errors["confirm_password"] = "Passwords do not match.";
        }

        if (!$errors && !password_verify($current, $user["password"])) {
            $errors["current_password"] = "Current password is incorrect.";
        }

        if (!$errors) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare("UPDATE user SET password=? WHERE user_id=?");
            $passwordStmt->bind_param("si", $hash, $userId);

            if ($passwordStmt->execute()) {
                $_SESSION["profile_success"] = "Password changed successfully.";
                header("Location: profile.php");
                exit;
            }

            $errors["general"] = "Could not change your password.";
            $passwordStmt->close();
        }
    }
}

$profileImage = profileImageUrl($user["profile_image"]);
$createdAt = !empty($user["created_at"]) ? date("d M Y", strtotime($user["created_at"])) : "Not available";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EcoScrap | My Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --eco-light: #82c843;
            --eco-primary: #2e7d32;
            --eco-primary-dark: #236128;
            --eco-dark: #004d40;
            --eco-accent: #00b4d8;
            --body-bg: #f1f5f4;
            --text-main: #16342f;
            --text-muted: #64748b;
            --text-soft: #94a3b8;
            --border: #e6eeeb;
            --white: #ffffff;
            --shadow-sm: 0 8px 25px rgba(22, 52, 47, 0.06);
            --shadow-md: 0 18px 45px rgba(22, 52, 47, 0.10);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --spring: cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 90% 0%, rgba(130, 200, 67, 0.14), transparent 30%),
                var(--body-bg);
            color: var(--text-main);
            font-family: "DM Sans", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            border: 0;
            cursor: pointer;
            font: inherit;
        }

        .user-page-shell {
            min-height: calc(100vh - 76px);
            padding: 34px 5% 52px;
            background:
                radial-gradient(circle at 90% 0%, rgba(130, 200, 67, 0.14), transparent 30%),
                var(--body-bg);
        }

        .user-page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            max-width: 1520px;
            margin: 0 auto 24px;
        }

        .user-page-heading-copy {
            margin-top: 14px;
        }

        .user-page-heading h1 {
            margin: 0 0 7px;
            color: var(--eco-dark);
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: clamp(26px, 3vw, 36px);
            letter-spacing: -0.7px;
        }

        .user-page-heading p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .user-page-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .user-page-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid #bde5c0;
            border-radius: var(--radius-sm);
            background: #effaf0;
            color: #256029;
            font-size: 13px;
            font-weight: 600;
        }

        .impact-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            min-height: 150px;
            margin-bottom: 25px;
            padding: 27px 31px;
            border-radius: var(--radius-lg);
            background: linear-gradient(120deg, rgba(0, 77, 64, 0.97), rgba(46, 125, 50, 0.95));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .impact-card::before {
            position: absolute;
            top: -75px;
            right: 13%;
            width: 210px;
            height: 210px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            content: "";
        }

        .impact-card::after {
            position: absolute;
            top: -35px;
            right: 5%;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            content: "";
        }

        .impact-info {
            position: relative;
            z-index: 2;
            max-width: 640px;
        }

        .impact-info .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            color: var(--eco-light);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .impact-info h2 {
            margin-bottom: 7px;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 20px;
        }

        .impact-info p {
            max-width: 500px;
            color: rgba(255,255,255,0.68);
            font-size: 12px;
        }

        .impact-progress-wrap {
            position: relative;
            z-index: 2;
            width: 220px;
            flex: 0 0 220px;
        }

        .impact-progress-head {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: rgba(255,255,255,0.75);
            font-size: 11px;
            font-weight: 600;
        }

        .impact-progress-head strong {
            color: white;
        }

        .progress-bar {
            height: 9px;
            overflow: hidden;
            border-radius: 9px;
            background: rgba(255,255,255,0.18);
        }

        .progress-bar span {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: var(--eco-light);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
            max-width: 1520px;
            margin: 0 auto;
        }

        .section-card {
            padding: 24px;
        }

        .section-card h3 {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .section-desc {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            color: var(--text-main);
            font-size: 13px;
            font-weight: 700;
        }

        input, textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 13px 14px;
            background: #fff;
            color: var(--text-main);
            font: inherit;
            transition: 0.25s ease;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--eco-light);
            box-shadow: 0 0 0 4px rgba(130, 200, 67, 0.12);
        }

        .input-error {
            color: #c62828;
            font-size: 12px;
            margin-top: -2px;
        }

        .helper-text {
            color: var(--text-soft);
            font-size: 12px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 22px;
        }

        .btn-primary-green,
        .btn-outline-green {
            border-radius: 14px;
            padding: 13px 18px;
            font-weight: 800;
            font-size: 13px;
            transition: 0.25s ease;
        }

        .btn-primary-green {
            background: var(--eco-primary);
            color: white;
            box-shadow: 0 10px 20px rgba(46, 125, 50, 0.18);
        }

        .btn-primary-green:hover {
            background: var(--eco-primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline-green {
            border: 1px solid var(--border);
            background: white;
            color: var(--text-main);
        }

        .btn-outline-green:hover {
            border-color: var(--eco-light);
            color: var(--eco-primary);
        }

        .security-list {
            display: grid;
            gap: 12px;
        }

        .security-item {
            padding: 15px;
            border-radius: 14px;
            background: #f8fbfa;
            border: 1px solid #edf2ef;
        }

        .security-item strong {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
        }

        .security-item span {
            color: var(--text-muted);
            font-size: 12px;
        }

        .upload-preview {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 12px;
            padding: 14px;
            border: 1px dashed var(--border);
            border-radius: 14px;
            background: #fafdfb;
        }

        .upload-preview img {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            object-fit: cover;
            background: #fff;
        }

        .toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1000;
            min-width: 280px;
            max-width: 90vw;
            padding: 14px 16px;
            border-radius: 14px;
            background: #e8f7e9;
            color: #256029;
            box-shadow: var(--shadow-md);
            display: none;
        }

        .toast.show {
            display: block;
        }

        @media (max-width: 900px) {
            .user-page-shell {
                padding: 24px 18px 36px;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .impact-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .impact-progress-wrap {
                width: 100%;
                flex: 1 1 auto;
            }
        }

        @media (max-width: 560px) {
            .user-page-heading {
                display: block;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn-primary-green,
            .actions .btn-outline-green {
                width: 100%;
                text-align: center;
            }

            .impact-card {
                padding: 22px 18px;
            }
        }
    </style>
</head>
<body>

<div class="user-page-shell">
    <div class="user-page-heading">
        <div class="user-page-heading-copy">
            <h1>My Profile</h1>
            <p>Manage your personal details, update your profile image, and change your password securely.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="user-page-alert">
            <i class="ri-checkbox-circle-line"></i>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors["general"])): ?>
        <div class="user-page-alert" style="border-color:#f3c5c5;background:#fff4f4;color:#a94442;">
            <i class="ri-error-warning-line"></i>
            <span><?= e($errors["general"]) ?></span>
        </div>
    <?php endif; ?>

    <section class="impact-card">
        <div class="impact-info">
            <p class="eyebrow"><i class="ri-shield-user-line"></i> Account profile</p>
            <h2><?= e($user["name"]) ?></h2>
            <p><?= e($user["email"]) ?> • <?= e($user["phone"]) ?></p>
            <p>Member since <?= e($createdAt) ?></p>
        </div>

        <div class="impact-progress-wrap">
            <div class="impact-progress-head">
                <strong>Profile</strong>
                <span>Complete</span>
            </div>
            <div class="progress-bar">
                <span></span>
            </div>
        </div>
    </section>

    <div class="profile-grid">
        <div>
            <div class="user-page-card section-card">
                <h3>Personal Information</h3>
                <p class="section-desc">Update your account details below. These values are loaded from your profile and saved back to the same user record.</p>

                <form method="post" enctype="multipart/form-data" id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="form-grid">
                        <?php foreach (["name" => "Name", "email" => "Email", "phone" => "Phone", "place" => "Place", "district" => "District", "state" => "State", "pincode" => "Pincode"] as $field => $label): ?>
                            <div class="form-group">
                                <label for="<?= e($field) ?>"><?= e($label) ?></label>
                                <input id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $field === "email" ? "email" : "text" ?>" value="<?= e($_POST[$field] ?? $user[$field]) ?>" class="<?= isset($errors[$field]) ? "is-invalid" : "" ?>" required>
                                <?php if (isset($errors[$field])): ?>
                                    <span class="input-error"><?= e($errors[$field]) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="form-group full">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="<?= isset($errors["address"]) ? "is-invalid" : "" ?>" required><?= e($_POST["address"] ?? $user["address"]) ?></textarea>
                            <?php if (isset($errors["address"])): ?>
                                <span class="input-error"><?= e($errors["address"]) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="upload">
                        <div class="form-group full">
                            <label for="profile_image"><strong>Profile image</strong></label>
                            <input id="profile_image" name="profile_image" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                            <div class="helper-text">JPG or PNG, maximum 2 MB. Choose a new image to replace the current one.</div>
                            <?php if (isset($errors["profile_image"])): ?>
                                <span class="input-error"><?= e($errors["profile_image"]) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="upload-preview">
                            <img id="uploadPreview" src="<?= e($profileImage) ?>" alt="Selected profile preview">
                            <div>
                                <strong style="display:block; margin-bottom:4px; font-size:13px;">Image Preview</strong>
                                <span class="helper-text">The selected image will appear here before saving.</span>
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <a class="btn-outline-green" href="dashboard.php">Cancel</a>
                        <button class="btn-primary-green" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <div class="user-page-card section-card" style="margin-bottom:20px;">
                <h3>Change Password</h3>
                <p class="section-desc">Enter your current password and choose a new secure password.</p>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    <input type="hidden" name="change_password" value="1">

                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="current_password">Current Password</label>
                            <input id="current_password" name="current_password" type="password" class="<?= isset($errors["current_password"]) ? "is-invalid" : "" ?>" required>
                            <?php if (isset($errors["current_password"])): ?>
                                <span class="input-error"><?= e($errors["current_password"]) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group full">
                            <label for="new_password">New Password</label>
                            <input id="new_password" name="new_password" type="password" class="<?= isset($errors["new_password"]) ? "is-invalid" : "" ?>" required>
                            <?php if (isset($errors["new_password"])): ?>
                                <span class="input-error"><?= e($errors["new_password"]) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group full">
                            <label for="confirm_password">Confirm New Password</label>
                            <input id="confirm_password" name="confirm_password" type="password" class="<?= isset($errors["confirm_password"]) ? "is-invalid" : "" ?>" required>
                            <?php if (isset($errors["confirm_password"])): ?>
                                <span class="input-error"><?= e($errors["confirm_password"]) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button class="btn-primary-green" type="submit" style="width:100%; margin-top:6px;">Update Password</button>
                </form>
            </div>

            <div class="user-page-card section-card">
                <h3>Account Security</h3>
                <p class="section-desc">Your profile uses the existing login session and updates only your own record.</p>

                <div class="security-list">
                    <div class="security-item">
                        <strong><i class="ri-shield-check-line" style="color:var(--eco-primary); margin-right:6px;"></i> Secure Session</strong>
                        <span>Profile updates are tied to your logged-in user ID.</span>
                    </div>
                    <div class="security-item">
                        <strong><i class="ri-user-check-line" style="color:var(--eco-primary); margin-right:6px;"></i> Email Uniqueness</strong>
                        <span>Email changes are checked against other users before saving.</span>
                    </div>
                    <div class="security-item">
                        <strong><i class="ri-key-2-line" style="color:var(--eco-primary); margin-right:6px;"></i> Password Protection</strong>
                        <span>The current password is verified before changing to a new one.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="successToast">Profile saved successfully.</div>

<script>
const imageInput = document.getElementById('profile_image');
const uploadPreview = document.getElementById('uploadPreview');
const toast = document.getElementById('successToast');

imageInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file || !['image/jpeg', 'image/png'].includes(file.type) || file.size > 2 * 1024 * 1024) return;

    const reader = new FileReader();
    reader.onload = function (event) {
        uploadPreview.src = event.target.result;
    };
    reader.readAsDataURL(file);
});

<?php if ($success): ?>
toast.textContent = <?= json_encode($success) ?>;
toast.classList.add('show');
setTimeout(() => toast.classList.remove('show'), 2500);
<?php endif; ?>
</script>
</body>
</html>