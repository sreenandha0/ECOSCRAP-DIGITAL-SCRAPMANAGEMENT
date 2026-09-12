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
| Helper Function
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
| Availability Status Update
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_status'
) {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $posted_token)
    ) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        header('Location: collector_profile.php');
        exit;
    }

    $new_status = (string) ($_POST['availability_status'] ?? '');
    $allowed_statuses = ['Available', 'Busy', 'Offline'];

    if (!in_array($new_status, $allowed_statuses, true)) {
        $_SESSION['flash_error'] = 'Invalid availability status.';
        header('Location: collector_profile.php');
        exit;
    }

    $update_stmt = $conn->prepare(
        'UPDATE scrapcollector
         SET availability_status = ?
         WHERE collector_id = ?'
    );

    if (!$update_stmt) {
        $_SESSION['flash_error'] = 'Unable to prepare the status update.';
    } else {
        $update_stmt->bind_param('si', $new_status, $collector_id);

        if ($update_stmt->execute()) {
            $_SESSION['flash_success'] =
                'Availability status updated to ' . $new_status . '.';
        } else {
            $_SESSION['flash_error'] =
                'Unable to update your availability status.';
        }

        $update_stmt->close();
    }

    header('Location: collector_profile.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Collector Details
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
        pincode,
        availability_status,
        verification_status,
        completed_pickups,
        created_at
     FROM scrapcollector
     WHERE collector_id = ?'
);

if (!$stmt) {
    die('Database query preparation failed.');
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
| Collector Data
|--------------------------------------------------------------------------
*/

$collector_name = (string) ($collector['name'] ?? 'Collector');
$collector_email = (string) ($collector['email'] ?? 'N/A');
$collector_phone = (string) ($collector['phone'] ?? 'N/A');
$vehicle_number = (string) ($collector['vehicle_no'] ?? 'N/A');
$pincode = (string) ($collector['pincode'] ?? 'N/A');

$status = (string) ($collector['availability_status'] ?? 'Offline');

$verification_status =
    (string) ($collector['verification_status'] ?? 'Pending');

$completed_count = (int) ($collector['completed_pickups'] ?? 0);

$co2_impact_tons = number_format(
    ($completed_count * 15) / 1000,
    1
);

$created_at = !empty($collector['created_at'])
    ? date('d M Y', strtotime((string) $collector['created_at']))
    : 'N/A';

/*
|--------------------------------------------------------------------------
| Status Colors
|--------------------------------------------------------------------------
*/

$status_color_map = [
    'Available' => '#10B981',
    'Busy' => '#F59E0B',
    'Offline' => '#64748B'
];

$current_status_color =
    $status_color_map[$status] ?? $status_color_map['Offline'];

/*
|--------------------------------------------------------------------------
| Avatar
|--------------------------------------------------------------------------
*/

$trimmed_name = trim($collector_name);

$initials = $trimmed_name !== ''
    ? strtoupper(substr($trimmed_name, 0, 1))
    : 'C';

$avatar_filename = basename(
    (string) ($collector['profile_image'] ?? '')
);

$avatar_file_path =
    __DIR__ . '/../uploads/profile/' . $avatar_filename;

$avatar_src = (
    $avatar_filename !== '' &&
    is_file($avatar_file_path)
)
    ? '../uploads/profile/' . rawurlencode($avatar_filename)
    : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= e($collector_name); ?> -
        Collector Profile | EcoScrap
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

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
            --secondary: #047857;
            --accent: #0EA5E9;

            --bg-color: #F8FAFC;
            --surface: #FFFFFF;
            --surface-card: #FFFFFF;
            --surface-border: #E2E8F0;
            --border-color: #E2E8F0;

            --text-main: #0F172A;
            --text-muted: #64748B;

            --font-main:
                'Inter',
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                'Segoe UI',
                sans-serif;

            --transition: all 0.25s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: var(--bg-color);
            color: var(--text-main);
            font-family: var(--font-main);
            line-height: 1.6;
            padding-top: 90px;
            padding-bottom: 60px;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
        }

        button,
        select {
            font-family: inherit;
        }

        .container {
            width: 100%;
            max-width: 1050px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .glass-card {
            background: var(--surface);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
            transition: var(--transition);
        }

        .glass-card:hover {
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.06);
        }

        /*
        |--------------------------------------------------------------------------
        | Top Navigation
        |--------------------------------------------------------------------------
        */

        .topbar {
            position: fixed;
            inset: 0 0 auto 0;
            z-index: 100;

            min-height: 70px;
            padding: 0 32px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            background: rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid var(--border-color);

            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 10px;

            min-width: 0;
            color: var(--text-main);
            text-decoration: none;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            display: block;
            flex-shrink: 0;
            object-fit: contain;
        }

        .brand-name {
            overflow: hidden;

            color: var(--text-main);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-btn {
            width: 40px;
            height: 40px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            color: var(--text-muted);
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;

            font-size: 18px;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .icon-btn:hover {
            color: var(--primary);
            background: #F8FAFC;
            border-color: rgba(16, 185, 129, 0.35);
            transform: translateY(-1px);
        }

        .avatar-pill {
            display: flex;
            align-items: center;
            gap: 10px;

            padding: 6px 14px 6px 8px;

            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 30px;

            transition: var(--transition);
        }

        .avatar-pill:hover {
            border-color: rgba(16, 185, 129, 0.35);
        }

        .avatar {
            width: 30px;
            height: 30px;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #FFFFFF;
            background: var(--secondary);
            border-radius: 50%;

            font-size: 13px;
            font-weight: 700;
        }

        .user-name {
            max-width: 150px;
            overflow: hidden;

            font-size: 14px;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .btn-secondary,
        .btn-danger {
            min-height: 46px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            padding: 10px 18px;

            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-secondary {
            color: var(--text-main);
            background: #FFFFFF;
            border: 1px solid var(--surface-border);
        }

        .btn-secondary:hover {
            background: #F1F5F9;
            border-color: var(--text-main);
            transform: translateY(-1px);
        }

        .btn-danger {
            color: #EF4444;
            background: #FEF2F2;
            border: 1px solid #FECACA;
        }

        .btn-danger:hover {
            color: #FFFFFF;
            background: #EF4444;
            border-color: #EF4444;
            transform: translateY(-1px);
        }

        /*
        |--------------------------------------------------------------------------
        | Page Heading
        |--------------------------------------------------------------------------
        */

        .page-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .page-title {
            color: var(--text-main);
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        /*
        |--------------------------------------------------------------------------
        | Flash Messages
        |--------------------------------------------------------------------------
        */

        .toast-alert {
            display: flex;
            align-items: center;
            gap: 8px;

            margin-bottom: 20px;
            padding: 12px 20px;

            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
        }

        .toast-success {
            color: #065F46;
            background: #D1FAE5;
            border: 1px solid #A7F3D0;
        }

        .toast-error {
            color: #991B1B;
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
        }

        /*
        |--------------------------------------------------------------------------
        | Main Profile Section
        |--------------------------------------------------------------------------
        */

        .profile-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            margin-bottom: 36px;
        }

        .avatar-card {
            display: flex;
            flex-direction: column;
            align-items: center;

            padding: 32px 24px;
            text-align: center;
        }

        .avatar-wrapper {
            position: relative;

            width: 110px;
            height: 110px;
            margin-bottom: 16px;
        }

        .avatar-img,
        .avatar-fallback {
            width: 100%;
            height: 100%;

            border: 3px solid #FFFFFF;
            border-radius: 50%;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .avatar-img {
            display: block;
            object-fit: cover;
        }

        .avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;

            color: #FFFFFF;
            background: linear-gradient(
                135deg,
                var(--primary),
                var(--secondary)
            );

            font-size: 38px;
            font-weight: 700;
        }

        .status-indicator-dot {
            position: absolute;
            right: 4px;
            bottom: 4px;

            width: 18px;
            height: 18px;

            background: <?= e($current_status_color); ?>;
            border: 3px solid #FFFFFF;
            border-radius: 50%;
        }

        .collector-name {
            margin-bottom: 6px;
            font-size: 20px;
            font-weight: 700;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            margin-bottom: 20px;
            padding: 4px 12px;

            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-approved {
            color: #047857;
            background: #D1FAE5;
        }

        .badge-pending {
            color: #B45309;
            background: #FEF3C7;
        }

        /*
        |--------------------------------------------------------------------------
        | Status Form
        |--------------------------------------------------------------------------
        */

        .status-select-form {
            width: 100%;
            margin-top: 12px;
            padding-top: 16px;
            border-top: 1px solid var(--surface-border);
        }

        .status-select-form label {
            display: block;

            margin-bottom: 8px;

            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-align: left;
            text-transform: uppercase;
        }

        .custom-select {
            width: 100%;
            padding: 10px 12px;

            color: var(--text-main);
            background: #FFFFFF;
            border: 1px solid var(--surface-border);
            border-radius: 8px;
            outline: none;

            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .custom-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
        }

        /*
        |--------------------------------------------------------------------------
        | Information Card
        |--------------------------------------------------------------------------
        */

        .info-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 32px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .info-item label {
            display: block;

            margin-bottom: 6px;

            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .info-item span {
            display: block;
            overflow-wrap: anywhere;

            color: var(--text-main);
            font-size: 16px;
            font-weight: 600;
        }

        /*
        |--------------------------------------------------------------------------
        | Performance Statistics
        |--------------------------------------------------------------------------
        */

        .section-title {
            margin-bottom: 16px;

            color: var(--text-main);
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 36px;
        }

        .stat-item {
            padding: 24px;
            text-align: center;
        }

        .stat-value {
            margin-bottom: 4px;

            color: var(--text-main);
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /*
        |--------------------------------------------------------------------------
        | Settings
        |--------------------------------------------------------------------------
        */

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive Design
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid,
            .settings-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            body {
                padding-top: 80px;
            }

            .topbar {
                min-height: 64px;
                padding: 0 16px;
            }

            .brand-logo {
                width: 36px;
                height: 36px;
            }

            .brand-name {
                font-size: 18px;
            }

            .user-profile {
                gap: 8px;
            }

            .user-name {
                display: none;
            }

            .icon-btn {
                width: 36px;
                height: 36px;
            }

            .container {
                padding: 0 16px;
            }

            .page-title {
                font-size: 23px;
            }

            .info-card,
            .avatar-card {
                padding: 24px 18px;
            }

            .info-grid,
            .stats-grid,
            .settings-grid {
                grid-template-columns: 1fr;
            }

            .stat-item {
                padding: 20px;
            }

            .settings-grid {
                gap: 12px;
            }
        }

        @media (max-width: 380px) {
            .brand-name {
                display: none;
            }

            .page-title {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <a href="dashboard.php" class="brand-header">
        <img
            src="../assets/logo/ecoscrap-logo.png"
            alt="EcoScrap"
            class="brand-logo"
        >

        <span class="brand-name">EcoScrap</span>
    </a>

    <div class="user-profile">
        <a
            href="dashboard.php"
            class="icon-btn"
            title="Back to Dashboard"
            aria-label="Back to Dashboard"
        >
            <i class="ri-dashboard-line"></i>
        </a>

        <div class="avatar-pill">
            <div class="avatar">
                <?= e($initials); ?>
            </div>

            <span class="user-name">
                <?= e($collector_name); ?>
            </span>
        </div>

        <a
            href="../logout.php"
            class="icon-btn"
            title="Logout"
            aria-label="Logout"
        >
            <i class="ri-logout-box-r-line"></i>
        </a>
    </div>
</header>

<main class="container">

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="toast-alert toast-success">
            <i class="ri-checkbox-circle-fill"></i>
            <span>
                <?= e($_SESSION['flash_success']); ?>
            </span>
        </div>

        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="toast-alert toast-error">
            <i class="ri-error-warning-fill"></i>
            <span>
                <?= e($_SESSION['flash_error']); ?>
            </span>
        </div>

        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="page-title-row">
        <h1 class="page-title">My Collector Profile</h1>
    </div>

    <section class="profile-grid">

        <div class="glass-card avatar-card">

            <div class="avatar-wrapper">
                <?php if ($avatar_src !== null): ?>
                    <img
                        src="<?= e($avatar_src); ?>"
                        alt="<?= e($collector_name); ?>"
                        class="avatar-img"
                    >
                <?php else: ?>
                    <div class="avatar-fallback">
                        <?= e($initials); ?>
                    </div>
                <?php endif; ?>

                <span
                    class="status-indicator-dot"
                    title="Current status: <?= e($status); ?>"
                ></span>
            </div>

            <h2 class="collector-name">
                <?= e($collector_name); ?>
            </h2>

            <?php if ($verification_status === 'Approved'): ?>
                <span class="verification-badge badge-approved">
                    <i class="ri-shield-check-fill"></i>
                    Verified Collector
                </span>
            <?php else: ?>
                <span class="verification-badge badge-pending">
                    <i class="ri-time-line"></i>
                    <?= e($verification_status); ?> Verification
                </span>
            <?php endif; ?>

            <form
                method="POST"
                action="collector_profile.php"
                class="status-select-form"
            >
                <input
                    type="hidden"
                    name="action"
                    value="update_status"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']); ?>"
                >

                <label for="availability_status">
                    Update Availability
                </label>

                <select
                    name="availability_status"
                    id="availability_status"
                    class="custom-select"
                    onchange="this.form.submit()"
                >
                    <option
                        value="Available"
                        <?= $status === 'Available' ? 'selected' : ''; ?>
                    >
                        🟢 Available
                    </option>

                    <option
                        value="Busy"
                        <?= $status === 'Busy' ? 'selected' : ''; ?>
                    >
                        🟠 Busy / On Pickup
                    </option>

                    <option
                        value="Offline"
                        <?= $status === 'Offline' ? 'selected' : ''; ?>
                    >
                        ⚪ Offline
                    </option>
                </select>
            </form>
        </div>

        <div class="glass-card info-card">
            <div class="info-grid">

                <div class="info-item">
                    <label>Full Name</label>
                    <span><?= e($collector_name); ?></span>
                </div>

                <div class="info-item">
                    <label>Email Address</label>
                    <span><?= e($collector_email); ?></span>
                </div>

                <div class="info-item">
                    <label>Phone Number</label>
                    <span><?= e($collector_phone); ?></span>
                </div>

                <div class="info-item">
                    <label>Vehicle Number</label>
                    <span><?= e($vehicle_number); ?></span>
                </div>

                <div class="info-item">
                    <label>Service Pincode</label>
                    <span><?= e($pincode); ?></span>
                </div>

                <div class="info-item">
                    <label>Account Created</label>
                    <span><?= e($created_at); ?></span>
                </div>

            </div>
        </div>

    </section>

    <h3 class="section-title">Performance Overview</h3>

    <section class="stats-grid">

        <div class="glass-card stat-item">
            <div
                class="stat-value"
                style="color: var(--primary);"
            >
                <?= e($completed_count); ?>
            </div>

            <div class="stat-label">
                Completed Pickups
            </div>
        </div>

        <div class="glass-card stat-item">
            <div
                class="stat-value"
                style="color: var(--accent);"
            >
                <?= e($co2_impact_tons); ?>T
            </div>

            <div class="stat-label">
                CO₂ Saved (Tons)
            </div>
        </div>

        <div class="glass-card stat-item">
            <div
                class="stat-value"
                style="color: <?= e($current_status_color); ?>;"
            >
                <?= e($status); ?>
            </div>

            <div class="stat-label">
                Current Status
            </div>
        </div>

        <div class="glass-card stat-item">
            <div
                class="stat-value"
                style="color: #047857;"
            >
                4.9 ★
            </div>

            <div class="stat-label">
                Rating &amp; Trust
            </div>
        </div>

    </section>

    <h3 class="section-title">Account Settings</h3>

    <section class="settings-grid">

        <a
            href="edit_profile.php"
            class="btn-secondary"
        >
            <i class="ri-edit-line"></i>
            Edit Profile
        </a>

        <a
            href="change_password.php"
            class="btn-secondary"
        >
            <i class="ri-lock-password-line"></i>
            Change Password
        </a>

        <a
            href="service_area.php"
            class="btn-secondary"
        >
            <i class="ri-map-pin-line"></i>
            Manage Pincodes
        </a>

        <a
            href="../logout.php"
            class="btn-danger"
        >
            <i class="ri-logout-box-r-line"></i>
            Logout
        </a>

    </section>

</main>

</body>
</html>