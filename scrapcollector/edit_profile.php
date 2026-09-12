<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Session Setup
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    );

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['collector_id']) ||
    ($_SESSION['role'] ?? '') !== 'Collector'
) {
    header('Location: ../login.php');
    exit;
}

$collector_id = (int) $_SESSION['collector_id'];

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
|--------------------------------------------------------------------------
| Fetch Collector
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    'SELECT
        collector_id,
        name,
        email,
        phone,
        profile_image,
        vehicle_no,
        pincode
     FROM scrapcollector
     WHERE collector_id = ?'
);

if (!$stmt) {
    die('Unable to prepare collector query.');
}

$stmt->bind_param('i', $collector_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die('Collector record not found.');
}

$collector = $result->fetch_assoc();
$stmt->close();

/*
|--------------------------------------------------------------------------
| Form Processing
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $posted_token)
    ) {
        $_SESSION['flash_error'] =
            'Invalid security token. Please try again.';

        header('Location: edit_profile.php');
        exit;
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $vehicle_no = trim((string) ($_POST['vehicle_no'] ?? ''));
    $pincode = trim((string) ($_POST['pincode'] ?? ''));

    $errors = [];

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Name must not exceed 100 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (mb_strlen($email) > 100) {
        $errors[] = 'Email must not exceed 100 characters.';
    }

    if ($phone === '') {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    if ($vehicle_no === '') {
        $errors[] = 'Vehicle number is required.';
    } elseif (mb_strlen($vehicle_no) > 30) {
        $errors[] = 'Vehicle number must not exceed 30 characters.';
    }

    if ($pincode === '') {
        $errors[] = 'Pincode is required.';
    } elseif (!preg_match('/^[0-9]{4,10}$/', $pincode)) {
        $errors[] = 'Please enter a valid pincode.';
    }

    $new_profile_image = $collector['profile_image'];

    /*
    |--------------------------------------------------------------------------
    | Profile Image Upload
    |--------------------------------------------------------------------------
    */

    if (
        isset($_FILES['profile_image']) &&
        $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES['profile_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading the profile image.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Profile image must not exceed 2 MB.';
        } else {
            $allowed_mime_types = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $file['tmp_name']);
            finfo_close($file_info);

            if (!isset($allowed_mime_types[$mime_type])) {
                $errors[] =
                    'Only JPG, PNG, and WEBP images are allowed.';
            } else {
                $upload_directory =
                    __DIR__ . '/../uploads/profile/';

                if (!is_dir($upload_directory)) {
                    mkdir($upload_directory, 0755, true);
                }

                $extension = $allowed_mime_types[$mime_type];

                $new_filename =
                    'collector_' .
                    $collector_id .
                    '_' .
                    bin2hex(random_bytes(8)) .
                    '.' .
                    $extension;

                $destination =
                    $upload_directory . $new_filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] =
                        'Unable to save the uploaded profile image.';
                } else {
                    $old_filename = basename(
                        (string) ($collector['profile_image'] ?? '')
                    );

                    $old_file =
                        $upload_directory . $old_filename;

                    if (
                        $old_filename !== '' &&
                        is_file($old_file) &&
                        $old_filename !== $new_filename
                    ) {
                        @unlink($old_file);
                    }

                    $new_profile_image = $new_filename;
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Database
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {
        $update_stmt = $conn->prepare(
            'UPDATE scrapcollector
             SET
                name = ?,
                email = ?,
                phone = ?,
                vehicle_no = ?,
                pincode = ?,
                profile_image = ?
             WHERE collector_id = ?'
        );

        if (!$update_stmt) {
            $errors[] = 'Unable to prepare profile update.';
        } else {
            $update_stmt->bind_param(
                'ssssssi',
                $name,
                $email,
                $phone,
                $vehicle_no,
                $pincode,
                $new_profile_image,
                $collector_id
            );

            if ($update_stmt->execute()) {
                $_SESSION['flash_success'] =
                    'Profile updated successfully.';

                $update_stmt->close();

                header('Location: collector_profile.php');
                exit;
            }

            if ($conn->errno === 1062) {
                $errors[] =
                    'That email address is already registered.';
            } else {
                $errors[] =
                    'Unable to update your profile.';
            }

            $update_stmt->close();
        }
    }

    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' ', $errors);

        $collector['name'] = $name;
        $collector['email'] = $email;
        $collector['phone'] = $phone;
        $collector['vehicle_no'] = $vehicle_no;
        $collector['pincode'] = $pincode;
        $collector['profile_image'] = $new_profile_image;
    }
}

/*
|--------------------------------------------------------------------------
| Avatar
|--------------------------------------------------------------------------
*/

$collector_name = (string) ($collector['name'] ?? 'Collector');

$avatar_filename = basename(
    (string) ($collector['profile_image'] ?? '')
);

$avatar_path =
    __DIR__ . '/../uploads/profile/' . $avatar_filename;

$avatar_src = (
    $avatar_filename !== '' &&
    is_file($avatar_path)
)
    ? '../uploads/profile/' . rawurlencode($avatar_filename)
    : null;

$initials = strtoupper(
    substr(trim($collector_name), 0, 1)
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Edit Profile | EcoScrap</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --primary: #10B981;
            --primary-dark: #047857;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --border: #E2E8F0;
            --text: #0F172A;
            --muted: #64748B;
            --danger: #DC2626;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            padding: 90px 16px 50px;
            color: var(--text);
            background: var(--bg);
            font-family: 'Inter', sans-serif;
        }

        .topbar {
            position: fixed;
            inset: 0 0 auto 0;
            z-index: 10;

            min-height: 70px;
            padding: 0 32px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            background: rgba(255, 255, 255, .92);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            text-decoration: none;
        }

        .brand img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .brand span {
            font-size: 20px;
            font-weight: 800;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .back-link:hover {
            color: var(--primary-dark);
        }

        .page {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
        }

        .heading {
            margin-bottom: 24px;
        }

        .heading h1 {
            font-size: 28px;
            font-weight: 800;
        }

        .heading p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 14px;
        }

        .card {
            padding: 30px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        }

        .alert {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-success {
            color: #065F46;
            background: #D1FAE5;
            border: 1px solid #A7F3D0;
        }

        .alert-error {
            color: #991B1B;
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
        }

        .image-section {
            display: flex;
            align-items: center;
            gap: 22px;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .profile-preview,
        .profile-fallback {
            width: 100px;
            height: 100px;
            flex-shrink: 0;
            border-radius: 50%;
        }

        .profile-preview {
            object-fit: cover;
            border: 3px solid #FFFFFF;
            box-shadow: 0 5px 18px rgba(15, 23, 42, .12);
        }

        .profile-fallback {
            display: flex;
            align-items: center;
            justify-content: center;

            color: #FFFFFF;
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--primary-dark)
            );

            font-size: 34px;
            font-weight: 800;
        }

        .image-content h2 {
            margin-bottom: 5px;
            font-size: 16px;
        }

        .image-content p {
            margin-bottom: 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .file-input {
            width: 100%;
            max-width: 280px;
            font-size: 13px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 12px 13px;

            color: var(--text);
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 9px;
            outline: none;

            font-size: 14px;
            transition: .2s ease;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .12);
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;

            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .btn {
            min-height: 44px;
            padding: 10px 18px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-cancel {
            color: var(--text);
            background: #FFFFFF;
            border: 1px solid var(--border);
        }

        .btn-save {
            color: #FFFFFF;
            background: var(--primary-dark);
            border: 1px solid var(--primary-dark);
        }

        .btn-save:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        @media (max-width: 600px) {
            body {
                padding-top: 80px;
            }

            .topbar {
                padding: 0 16px;
            }

            .brand img {
                width: 36px;
                height: 36px;
            }

            .brand span {
                font-size: 18px;
            }

            .card {
                padding: 22px 18px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .image-section {
                align-items: flex-start;
                flex-direction: column;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <a href="dashboard.php" class="brand">
        <img
            src="../assets/logo/ecoscrap-logo.png"
            alt="EcoScrap"
        >
        <span>EcoScrap</span>
    </a>

    <a href="profile.php" class="back-link">
        <i class="ri-arrow-left-line"></i>
        Back to Profile
    </a>
</header>

<main class="page">

    <div class="heading">
        <h1>Edit Profile</h1>
        <p>Update your personal and vehicle details.</p>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <?= e($_SESSION['flash_success']); ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-error">
            <?= e($_SESSION['flash_error']); ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        class="card"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']); ?>"
        >

        <div class="image-section">
            <?php if ($avatar_src !== null): ?>
                <img
                    src="<?= e($avatar_src); ?>"
                    alt="<?= e($collector_name); ?>"
                    class="profile-preview"
                >
            <?php else: ?>
                <div class="profile-fallback">
                    <?= e($initials); ?>
                </div>
            <?php endif; ?>

            <div class="image-content">
                <h2>Profile Picture</h2>
                <p>JPG, PNG, or WEBP. Maximum size: 2 MB.</p>

                <input
                    type="file"
                    name="profile_image"
                    class="file-input"
                    accept="image/jpeg,image/png,image/webp"
                >
            </div>
        </div>

        <div class="form-grid">

            <div class="form-group full">
                <label for="name">Full Name</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    required
                    value="<?= e($collector['name'] ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="100"
                    required
                    value="<?= e($collector['email'] ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>

                <input
                    type="text"
                    id="phone"
                    name="phone"
                    maxlength="15"
                    required
                    value="<?= e($collector['phone'] ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label for="vehicle_no">Vehicle Number</label>

                <input
                    type="text"
                    id="vehicle_no"
                    name="vehicle_no"
                    maxlength="30"
                    required
                    value="<?= e($collector['vehicle_no'] ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label for="pincode">Service Pincode</label>

                <input
                    type="text"
                    id="pincode"
                    name="pincode"
                    maxlength="10"
                    required
                    value="<?= e($collector['pincode'] ?? ''); ?>"
                >
            </div>

        </div>

        <div class="actions">
            <a
                href="collector_profile.php"
                class="btn btn-cancel"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-save"
            >
                <i class="ri-save-line"></i>
                Save Changes
            </button>
        </div>
    </form>

</main>

</body>
</html>