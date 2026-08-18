<?php
// 1. Session Setup & Cookie Security
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once "../includes/db.php";
require_once "../includes/functions.php";

// 2. Authentication Check
if (!isset($_SESSION['collector_id']) || ($_SESSION['role'] ?? '') !== "Collector") {
    header("Location: ../login.php");
    exit();
}

$collector_id = (int)$_SESSION['collector_id'];

// 3. Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. Handle Quick Availability Status Update (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    
    // CSRF Token Check
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $posted_token)) {
        
        $new_status = $_POST['availability_status'] ?? 'Available';
        $allowed_statuses = ['Available', 'Busy', 'Offline'];

        if (in_array($new_status, $allowed_statuses, true)) {
            $update_stmt = $conn->prepare("UPDATE scrapcollector SET availability_status = ? WHERE collector_id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("si", $new_status, $collector_id);
                $update_stmt->execute();
                $update_stmt->close();
                $_SESSION['flash_success'] = "Availability status updated to " . htmlspecialchars($new_status) . ".";
            }
        }
    } else {
        $_SESSION['flash_error'] = "Invalid security token. Please try again.";
    }

    header("Location: collector_profile.php");
    exit();
}

// 5. Fetch Collector Data from `scrapcollector` Table
$stmt = $conn->prepare("SELECT collector_id, name, email, phone, profile_image, vehicle_no, pincode, availability_status, verification_status, completed_pickups, created_at FROM scrapcollector WHERE collector_id = ?");
$stmt->bind_param("i", $collector_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Collector record not found.");
}

$collector = $result->fetch_assoc();
$stmt->close();

// Derived Metrics & Formatting
$completed_count = (int)$collector['completed_pickups'];
$co2_impact_tons = number_format(($completed_count * 15) / 1000, 1);

// Status Color Badge Map
$status = $collector['availability_status'];
$status_color_map = [
    'Available' => '#10B981',
    'Busy'      => '#F59E0B',
    'Offline'   => '#64748B'
];
$current_status_color = $status_color_map[$status] ?? '#10B981';

// Profile Picture & Initials Fallback
$avatar_src = !empty($collector['profile_image']) ? '../uploads/profile/' . htmlspecialchars($collector['profile_image']) : null;
$initials = strtoupper(substr($collector['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($collector['name']); ?> - Collector Profile | EcoScrap</title>
    
    <!-- Google Fonts & Remix Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #10B981;
            --secondary: #047857;
            --accent: #0EA5E9;
            --bg-color: #F8FAFC;
            --surface: #FFFFFF;
            --surface-border: #E2E8F0;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
            --transition: all 0.25s ease;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            padding-top: 90px;
            padding-bottom: 60px;
            -webkit-font-smoothing: antialiased;
        }

        .container {
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

        /* Navbar Header */
        .navbar {
            position: fixed;
            top: 0; left: 0; width: 100%;
            z-index: 100;
            padding: 14px 0;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--surface-border);
        }
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1050px;
            margin: 0 auto;
            padding: 0 24px;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .brand-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            display: block;
            flex-shrink: 0;
        }
        .brand-name {
            font-weight: 800;
            font-size: 20px;
            color: var(--text-main);
            letter-spacing: -0.03em;
        }
        .nav-actions { display: flex; align-items: center; gap: 12px; }

        /* Buttons */
        .btn-primary, .btn-secondary, .btn-ghost, .btn-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: var(--text-main);
            color: #FFFFFF;
        }
        .btn-primary:hover {
            background: var(--primary);
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.25);
        }
        .btn-secondary {
            background: #FFFFFF;
            color: var(--text-main);
            border: 1px solid var(--surface-border);
        }
        .btn-secondary:hover {
            border-color: var(--text-main);
            background: #F1F5F9;
        }
        .btn-ghost {
            background: transparent;
            color: var(--text-muted);
        }
        .btn-ghost:hover {
            color: var(--text-main);
            background: #F1F5F9;
        }
        .btn-danger {
            background: #FEF2F2;
            color: #EF4444;
            border: 1px solid #FECACA;
        }
        .btn-danger:hover {
            background: #EF4444;
            color: #FFFFFF;
        }

        .page-title-row {
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .page-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        /* Profile Layout Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            margin-bottom: 36px;
        }

        /* Avatar Side Card */
        .avatar-card {
            padding: 32px 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .avatar-wrapper {
            position: relative;
            width: 110px;
            height: 110px;
            margin-bottom: 16px;
        }
        .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #FFFFFF;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .avatar-fallback {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #FFFFFF;
            font-size: 38px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #FFFFFF;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
        }
        .status-indicator-dot {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid #FFFFFF;
            background-color: <?= $current_status_color ?>;
        }

        .collector-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .badge-approved {
            background: #D1FAE5;
            color: #047857;
        }
        .badge-pending {
            background: #FEF3C7;
            color: #B45309;
        }

        /* Status Form Control */
        .status-select-form {
            width: 100%;
            margin-top: 12px;
            padding-top: 16px;
            border-top: 1px solid var(--surface-border);
        }
        .status-select-form label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
            margin-bottom: 8px;
        }
        .custom-select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--surface-border);
            background: #FFFFFF;
            color: var(--text-main);
            font-weight: 500;
            font-size: 14px;
            outline: none;
            cursor: pointer;
        }

        /* Info Card */
        .info-card {
            padding: 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }
        .info-item label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .info-item span {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Section Headlines & Stats */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
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
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Settings Actions Toolbar */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        /* Toast Messages */
        .toast-alert {
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toast-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .toast-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }

        @media (max-width: 900px) {
            .profile-grid { grid-template-columns: 1fr; }
            .stats-grid, .settings-grid { grid-template-columns: repeat(2, 1fr); }
            .info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 540px) {
            .stats-grid, .settings-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Header -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <img src="../assets/logo/ecoscrap-logo.png" alt="EcoScrap Logo" class="brand-logo">
                <span class="brand-name">EcoScrap</span>
            </a>
            <div class="nav-actions">
                <a href="dashboard.php" class="btn-ghost"><i class="ri-dashboard-line"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <!-- Main Workspace -->
    <main class="container">

        <!-- Flash Notifications -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="toast-alert toast-success">
                <i class="ri-checkbox-circle-fill"></i> <?= htmlspecialchars($_SESSION['flash_success']); ?>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="toast-alert toast-error">
                <i class="ri-error-warning-fill"></i> <?= htmlspecialchars($_SESSION['flash_error']); ?>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <div class="page-title-row">
            <h1 class="page-title">My Collector Profile</h1>
        </div>

        <!-- Main Collector Details Section -->
        <section class="profile-grid">
            
            <!-- Avatar Card -->
            <div class="glass-card avatar-card">
                <div class="avatar-wrapper">
                    <?php if ($avatar_src): ?>
                        <img src="<?= $avatar_src ?>" alt="<?= htmlspecialchars($collector['name']) ?>" class="avatar-img">
                    <?php else: ?>
                        <div class="avatar-fallback"><?= $initials ?></div>
                    <?php endif; ?>
                    <span class="status-indicator-dot" title="Current status: <?= htmlspecialchars($status) ?>"></span>
                </div>

                <h2 class="collector-name"><?= htmlspecialchars($collector['name']); ?></h2>

                <?php if ($collector['verification_status'] === 'Approved'): ?>
                    <span class="verification-badge badge-approved">
                        <i class="ri-shield-check-fill"></i> Verified Collector
                    </span>
                <?php else: ?>
                    <span class="verification-badge badge-pending">
                        <i class="ri-time-line"></i> <?= htmlspecialchars($collector['verification_status']); ?> Verification
                    </span>
                <?php endif; ?>

                <!-- Quick Status Toggle Form -->
                <form method="POST" action="collector_profile.php" class="status-select-form">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="availability_status">Update Availability</label>
                    <select name="availability_status" id="availability_status" class="custom-select" onchange="this.form.submit()">
                        <option value="Available" <?= $status === 'Available' ? 'selected' : '' ?>>🟢 Available</option>
                        <option value="Busy" <?= $status === 'Busy' ? 'selected' : '' ?>>🟠 Busy / On Pickup</option>
                        <option value="Offline" <?= $status === 'Offline' ? 'selected' : '' ?>>⚪ Offline</option>
                    </select>
                </form>
            </div>

            <!-- Information Card -->
            <div class="glass-card info-card">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <span><?= htmlspecialchars($collector['name']); ?></span>
                    </div>

                    <div class="info-item">
                        <label>Email Address</label>
                        <span><?= htmlspecialchars($collector['email']); ?></span>
                    </div>

                    <div class="info-item">
                        <label>Phone Number</label>
                        <span><?= htmlspecialchars($collector['phone'] ?? 'N/A'); ?></span>
                    </div>

                    <div class="info-item">
                        <label>Vehicle Number</label>
                        <span><?= htmlspecialchars($collector['vehicle_no'] ?? 'N/A'); ?></span>
                    </div>

                    <div class="info-item">
                        <label>Service Pincode</label>
                        <span><?= htmlspecialchars($collector['pincode']); ?></span>
                    </div>

                    <div class="info-item">
                        <label>Account Created</label>
                        <span><?= date("d M Y", strtotime($collector['created_at'])); ?></span>
                    </div>
                </div>
            </div>

        </section>

        <!-- Performance Stats Overview -->
        <h3 class="section-title">Performance Overview</h3>
        <section class="stats-grid">
            <div class="glass-card stat-item">
                <div class="stat-value" style="color: var(--primary);"><?= $completed_count ?></div>
                <div class="stat-label">Completed Pickups</div>
            </div>

            <div class="glass-card stat-item">
                <div class="stat-value" style="color: var(--accent);"><?= $co2_impact_tons ?>T</div>
                <div class="stat-label">CO₂ Saved (Tons)</div>
            </div>

            <div class="glass-card stat-item">
                <div class="stat-value" style="color: <?= $current_status_color ?>;"><?= htmlspecialchars($status) ?></div>
                <div class="stat-label">Current Status</div>
            </div>

            <div class="glass-card stat-item">
                <div class="stat-value" style="color: #047857;">4.9 ★</div>
                <div class="stat-label">Rating & Trust</div>
            </div>
        </section>

        <!-- Account Actions Toolbar -->
        <h3 class="section-title">Account Settings</h3>
        <section class="settings-grid">
            <a href="edit_profile.php" class="btn-secondary">
                <i class="ri-edit-line"></i> Edit Profile
            </a>

            <a href="change_password.php" class="btn-secondary">
                <i class="ri-lock-password-line"></i> Change Password
            </a>

            <a href="service_area.php" class="btn-secondary">
                <i class="ri-map-pin-line"></i> Manage Pincodes
            </a>

            <a href="../logout.php" class="btn-danger">
                <i class="ri-logout-box-r-line"></i> Logout
            </a>
        </section>

    </main>

</body>
</html>