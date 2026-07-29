<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// Protect Page
if (!isset($_SESSION['collector_id']) || $_SESSION['role'] !== "Collector") {
    redirect("../login.php");
}

$collector_id = (int)$_SESSION['collector_id'];

// ============================
// Collector Profile Info
// ============================
$stmt = $conn->prepare("SELECT collector_id, name, email, phone, vehicle_no, pincode, availability_status FROM scrapcollector WHERE collector_id = ?");
$stmt->bind_param("i", $collector_id);
$stmt->execute();
$collector = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$collector) {
    redirect("../login.php");
}

// ============================
// Summary Statistics & Metrics
// ============================
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END), 0) AS assigned_count,
        COALESCE(SUM(CASE WHEN status = 'Completed' AND preferred_pickup_date = CURDATE() THEN 1 ELSE 0 END), 0) AS completed_today_count,
        COALESCE(SUM(CASE WHEN status = 'In Progress' OR status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_verification_count,
        COALESCE(SUM(CASE WHEN status = 'Completed' THEN amount ELSE 0 END), 0) AS total_earnings
    FROM activity
    WHERE collector_id = ?
");

$stmt->bind_param("i", $collector_id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();
$stmt->close();

$assigned            = (int)($counts['assigned_count'] ?? 0);
$completedToday      = (int)($counts['completed_today_count'] ?? 0);
$pendingVerification = (int)($counts['pending_verification_count'] ?? 0);
$earnings            = (int)($counts['total_earnings'] ?? 0);

// ============================
// Today's Assigned Pickups
// ============================
$stmt = $conn->prepare("
    SELECT 
        activity.activity_id,
        user.name AS customer_name,
        activity.scrap_type,
        activity.scrap_weight,
        activity.pickup_time,
        activity.status
    FROM activity
    INNER JOIN user ON activity.user_id = user.user_id
    WHERE activity.collector_id = ? AND activity.preferred_pickup_date = CURDATE()
    ORDER BY activity.activity_id DESC
");
$stmt->bind_param("i", $collector_id);
$stmt->execute();
$todaysPickups = $stmt->get_result();
$stmt->close();

// ============================
// Recent Activity Feed
// ============================
$stmt = $conn->prepare("
    SELECT 
        activity.activity_id,
        user.name AS customer_name,
        activity.scrap_type,
        activity.scrap_weight,
        activity.status,
        activity.request_date
    FROM activity
    INNER JOIN user ON activity.user_id = user.user_id
    WHERE activity.collector_id = ?
    ORDER BY activity.request_date DESC
    LIMIT 4
");
$stmt->bind_param("i", $collector_id);
$stmt->execute();
$recentActivities = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collector Dashboard | EcoScrap</title>

    <!-- Google Fonts & Remix Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Base CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        :root {
            --bg-color: #f8fafc;
            --surface-color: #ffffff;
            --primary: #10b981;
            --primary-dark: #059669;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --transition: cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            font-family: 'Inter', sans-serif;
            display: flex;
            overflow-x: hidden;
        }

        /* 1. Sidebar Styles */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--surface-color);
            border-right: 1px solid var(--border-color);
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 110;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            text-decoration: none;
            padding: 0 12px;
            margin-bottom: 32px;
        }

        .sidebar-brand i { color: var(--primary); }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            color: var(--text-main);
            background: #f1f5f9;
        }

        .nav-link.active {
            color: var(--primary-dark);
            background: rgba(16, 185, 129, 0.12);
            font-weight: 600;
        }

        .nav-link i { font-size: 18px; }

        .logout-link { color: #ef4444; }
        .logout-link:hover { background: #fef2f2; color: #dc2626; }

        /* Workspace Wrapper */
        .workspace {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            animation: fadeInPage 0.6s var(--transition) forwards;
        }

        /* 2. Top Header Bar */
        .top-navbar {
            background: var(--surface-color);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);
        }

        .header-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-btn {
            position: relative;
            background: #f1f5f9;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--text-main);
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .notification-btn:hover { background: #e2e8f0; }

        .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 9px;
            height: 9px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid var(--surface-color);
            animation: pulseGlow 1.8s infinite ease-in-out;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        /* Dashboard Container */
        .dashboard-container {
            padding: 28px 32px 60px;
            max-width: 1200px;
        }

        /* Hero Welcome Section */
        .welcome-header {
            margin-bottom: 24px;
            animation: slideDownHeader 0.5s var(--transition) forwards;
        }

        .welcome-header h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 6px 0;
            letter-spacing: -0.02em;
        }

        .welcome-header p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Summary Cards Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .card {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 22px;
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.02);
            transition: transform 0.25s var(--transition), box-shadow 0.25s var(--transition), border-color 0.25s var(--transition);
            opacity: 0;
            animation: fadeInUpCard 0.5s var(--transition) forwards;
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px -8px rgba(15, 23, 42, 0.08);
            border-color: var(--primary);
        }

        .summary-grid .card:nth-child(1) { animation-delay: 0.1s; }
        .summary-grid .card:nth-child(2) { animation-delay: 0.2s; }
        .summary-grid .card:nth-child(3) { animation-delay: 0.3s; }
        .summary-grid .card:nth-child(4) { animation-delay: 0.4s; }

        .card-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 10px;
        }

        .card-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        /* Section Container */
        .section-box {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.02);
            opacity: 0;
            animation: fadeInUpCard 0.5s var(--transition) 0.4s forwards;
        }

        .section-header {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 20px;
        }

        .table-responsive { width: 100%; overflow-x: auto; }

        .pickup-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .pickup-table th {
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        .pickup-table tr {
            opacity: 0;
            animation: slideUpRow 0.4s var(--transition) forwards;
        }

        .pickup-table tbody tr:nth-child(1) { animation-delay: 0.5s; }
        .pickup-table tbody tr:nth-child(2) { animation-delay: 0.6s; }
        .pickup-table tbody tr:nth-child(3) { animation-delay: 0.7s; }

        .pickup-table td {
            padding: 16px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #fef3c7;
            color: #d97706;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            background: #f1f5f9;
            color: var(--text-main);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            background: var(--primary);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .action-card {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px 20px;
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            text-decoration: none;
            transition: transform 0.25s var(--transition), box-shadow 0.25s var(--transition), border-color 0.25s var(--transition);
        }

        .action-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 8px 16px -4px rgba(16, 185, 129, 0.15);
            color: var(--primary-dark);
        }

        .activity-feed {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #f8fafc;
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-main);
            transition: transform 0.2s ease;
        }

        .activity-item:hover { transform: translateX(4px); }

        .activity-item i {
            color: var(--primary);
            font-size: 18px;
        }

        .empty-state {
            text-align: center;
            padding: 32px 16px;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Keyframes */
        @keyframes fadeInPage { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideDownHeader { from { opacity: 0; transform: translateY(-15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInUpCard { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUpRow { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulseGlow {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        @media (max-width: 1024px) {
            .summary-grid, .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .workspace { margin-left: 0; }
            .summary-grid, .quick-actions-grid { grid-template-columns: 1fr; }
            .dashboard-container { padding: 20px 16px; }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div>
            <a href="dashboard.php" class="sidebar-brand">
                <i class="ri-recycle-line"></i> EcoScrap
            </a>

            <ul class="nav-menu">
                <li>
                    <a href="dashboard.php" class="nav-link active">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="assigned_requests.php" class="nav-link">
                        <i class="ri-truck-line"></i>
                        <span>Assigned Pickups</span>
                    </a>
                </li>
                <li>
                    <a href="verify_qr.php" class="nav-link">
                        <i class="ri-qr-scan-2-line"></i>
                        <span>Scan QR</span>
                    </a>
                </li>
                <li>
                    <a href="completed.php" class="nav-link">
                        <i class="ri-history-line"></i>
                        <span>History</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="nav-link">
                        <i class="ri-user-settings-line"></i>
                        <span>Profile</span>
                    </a>
                </li>
            </ul>
        </div>

        <div>
            <a href="../logout.php" class="nav-link logout-link">
                <i class="ri-logout-box-r-line"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Workspace Container -->
    <div class="workspace">

        <!-- Top Header -->
        <header class="top-navbar">
            <div class="header-title">EcoScrap Collector</div>

            <div class="navbar-right">
                <button class="notification-btn" aria-label="Notifications">
                    <i class="ri-notification-3-line"></i>
                    <span class="notification-badge"></span>
                </button>

                <div class="user-profile">
                    <div class="avatar-circle">
                        <i class="ri-user-3-line"></i>
                    </div>
                    <span><?= htmlspecialchars($collector['name']); ?></span>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="dashboard-container">

            <!-- Welcome Header -->
            <div class="welcome-header">
                <h1>Welcome Back, <?= htmlspecialchars(explode(' ', trim($collector['name']))[0]); ?> 👋</h1>
                <p>Here's your pickup schedule for today.</p>
            </div>

            <!-- Metric Cards -->
            <section class="summary-grid">
                <div class="card">
                    <div class="card-title">Assigned Pickups</div>
                    <div class="card-value counter" data-target="<?= $assigned; ?>">0</div>
                </div>

                <div class="card">
                    <div class="card-title">Completed Today</div>
                    <div class="card-value counter" data-target="<?= $completedToday; ?>">0</div>
                </div>

                <div class="card">
                    <div class="card-title">Pending Verification</div>
                    <div class="card-value counter" data-target="<?= $pendingVerification; ?>">0</div>
                </div>

                <div class="card">
                    <div class="card-title">Earnings</div>
                    <div class="card-value">₹<span class="counter" data-target="<?= $earnings; ?>">0</span></div>
                </div>
            </section>

            <!-- Today's Pickups Table -->
            <section class="section-box">
                <div class="section-header">Today's Assigned Pickups</div>
                <div class="table-responsive">
                    <table class="pickup-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Scrap</th>
                                <th>Weight</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($todaysPickups && $todaysPickups->num_rows > 0): ?>
                                <?php while ($row = $todaysPickups->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['customer_name']); ?></strong></td>
                                        <td><?= htmlspecialchars($row['scrap_type']); ?></td>
                                        <td><?= htmlspecialchars($row['scrap_weight']); ?> kg</td>
                                        <td><?= !empty($row['pickup_time']) ? date("g A", strtotime($row['pickup_time'])) : 'N/A'; ?></td>
                                        <td><span class="status-badge"><?= htmlspecialchars($row['status']); ?></span></td>
                                        <td>
                                            <a href="assigned_requests.php?id=<?= (int)$row['activity_id']; ?>" class="btn-action">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="ri-inbox-line" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                                        No pickups assigned for today.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Quick Actions -->
            <section class="section-box">
                <div class="section-header">Quick Actions</div>
                <div class="quick-actions-grid">
                    <a href="assigned_requests.php" class="action-card">
                        <span>📍 View Route</span>
                    </a>
                    <a href="verify_qr.php" class="action-card">
                        <span>📷 Scan QR</span>
                    </a>
                    <a href="verify_qr.php" class="action-card">
                        <span>✔ Verify Pickup</span>
                    </a>
                    <a href="completed.php" class="action-card">
                        <span>📜 History</span>
                    </a>
                </div>
            </section>

            <!-- Activity Log -->
            <section class="section-box">
                <div class="section-header">Recent Activity</div>
                <div class="activity-feed">
                    <?php if ($recentActivities && $recentActivities->num_rows > 0): ?>
                        <?php while ($act = $recentActivities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <i class="ri-checkbox-circle-fill"></i>
                                <span>Pickup <?= htmlspecialchars(strtolower($act['status'])); ?> - <?= htmlspecialchars($act['customer_name']); ?> (<?= htmlspecialchars($act['scrap_weight']); ?>kg <?= htmlspecialchars($act['scrap_type']); ?>)</span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">No recent activity logged yet.</div>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </div>

    <!-- Animated Counter JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const counters = document.querySelectorAll('.counter');
            const duration = 1000;

            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                let startTime = null;

                const updateCount = (timestamp) => {
                    if (!startTime) startTime = timestamp;
                    const progress = Math.min((timestamp - startTime) / duration, 1);
                    const currentCount = Math.floor(progress * target);

                    counter.innerText = currentCount.toLocaleString();

                    if (progress < 1) {
                        requestAnimationFrame(updateCount);
                    } else {
                        counter.innerText = target.toLocaleString();
                    }
                };

                requestAnimationFrame(updateCount);
            });
        });
    </script>
</body>

</html>