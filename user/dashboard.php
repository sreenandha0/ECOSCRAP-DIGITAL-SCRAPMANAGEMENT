<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "User") {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name    = $_SESSION['name'];

// Initialize Metric Counters
$total     = 0;
$pending   = 0;
$assigned  = 0;
$completed = 0;

// Optimized: Get all metric counts in a single prepared query
$stats_sql = "SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) AS assigned,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed
              FROM activity 
              WHERE user_id = ?";

if ($stmt = $conn->prepare($stats_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total     = (int) $row['total'];
        $pending   = (int) $row['pending'];
        $assigned  = (int) $row['assigned'];
        $completed = (int) $row['completed'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | EcoScrap</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Design System CSS -->
    <style>
        :root {
            /* Brand Colors */
            --primary: #10B981;    /* Emerald Green */
            --secondary: #047857;  /* Forest Green */
            --accent: #0EA5E9;     /* Sky Blue */
            
            /* Backgrounds & Surface */
            --bg-color: #F8FAFC;
            --surface: rgba(255, 255, 255, 0.7);
            --surface-border: rgba(15, 23, 42, 0.08);
            
            /* Text */
            --text-main: #0F172A;
            --text-muted: #64748B;
            
            /* Utilities */
            --font-main: 'Inter', sans-serif;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --mouse-x: 50%;
            --mouse-y: 50%;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
        }

        /* Utilities */
        .glass-panel {
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.03);
        }

        .glass-card {
            background: var(--surface);
            backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.02);
            transition: var(--transition);
        }

        /* Mouse Glow Effect */
        .mouse-glow {
            position: relative;
            overflow: hidden;
        }
        .mouse-glow::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(
                800px circle at var(--mouse-x) var(--mouse-y),
                rgba(16, 185, 129, 0.06),
                transparent 40%
            );
            z-index: 0;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .mouse-glow:hover::before { opacity: 1; }
        .mouse-glow > * { position: relative; z-index: 1; }

        /* Buttons */
        .btn-primary, .btn-secondary, .btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-primary {
            background: var(--text-main);
            color: var(--bg-color);
            border: 1px solid var(--text-main);
        }
        .btn-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25);
        }

        /* Sidebar Navigation Drawer */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid var(--surface-border);
            padding: 32px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 100;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            text-decoration: none;
            margin-bottom: 36px;
            padding-left: 8px;
            letter-spacing: -0.03em;
        }

        .logo-mark {
            width: 12px; 
            height: 12px;
            background: var(--primary);
            border-radius: 3px;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 0;
            margin: 0;
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
            transition: var(--transition);
        }

        .nav-link i {
            font-size: 18px;
        }

        .nav-link:hover {
            color: var(--text-main);
            background: rgba(15, 23, 42, 0.04);
        }

        .nav-link.active {
            color: var(--primary);
            background: rgba(16, 185, 129, 0.1);
            font-weight: 600;
        }

        .sidebar-footer {
            padding-top: 20px;
            border-top: 1px solid var(--surface-border);
        }

        .logout-link {
            color: #ef4444;
        }

        .logout-link:hover {
            background: rgba(239, 68, 68, 0.08);
            color: #dc2626;
        }

        /* Main Workspace Container */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 40px;
            max-width: 1300px;
        }

        /* Top Bar Layout */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .topbar-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.03em;
        }

        .topbar-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Metric Grid Cards (using design system glass-card) */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            padding: 22px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
            border-color: var(--primary);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-icon.total { background: rgba(14, 165, 233, 0.1); color: var(--accent); }
        .stat-icon.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-icon.assigned { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
        .stat-icon.completed { background: rgba(16, 185, 129, 0.1); color: var(--primary); }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.04em;
        }

        /* Two-Column Dashboard Content Layout */
        .content-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
        }

        /* Action Panel Side */
        .action-panel {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .quick-action-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            color: var(--text-main);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .quick-action-card i {
            font-size: 20px;
            color: var(--primary);
            transition: var(--transition);
        }

        .quick-action-card:hover {
            background: #ffffff;
            border-color: var(--primary);
            transform: translateX(4px);
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04);
        }

        /* Table Card Container */
        .table-card {
            padding: 24px;
        }

        .table-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .custom-table th {
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--surface-border);
        }

        .custom-table td {
            padding: 16px;
            font-size: 14px;
            color: var(--text-main);
            border-bottom: 1px solid var(--surface-border);
        }

        .custom-table tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-assigned {
            background: rgba(99, 102, 241, 0.1);
            color: #4f46e5;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .badge-completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* Responsive Layout Switch */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
                padding: 24px 16px;
            }
        }
    </style>
</head>

<body class="mouse-glow">

    <!-- Sidebar Navigation -->
    <aside class="sidebar glass-panel">
        <div>
            <a href="dashboard.php" class="sidebar-brand">
                <div class="logo-mark"></div>
                <span>EcoScrap</span>
            </a>
            <ul class="nav-menu">
                <li>
                    <a href="dashboard.php" class="nav-link active">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="create_request.php" class="nav-link">
                        <i class="ri-add-box-line"></i>
                        <span>Create Pickup</span>
                    </a>
                </li>
                <li>
                    <a href="history.php" class="nav-link">
                        <i class="ri-history-line"></i>
                        <span>My Requests</span>
                    </a>
                </li>
                <li>
                    <a href="qr.php" class="nav-link">
                        <i class="ri-qr-code-line"></i>
                        <span>My QR Code</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="nav-link">
                        <i class="ri-user-3-line"></i>
                        <span>Profile</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-link logout-link">
                <i class="ri-logout-box-r-line"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
        
        <!-- Header Row -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>Welcome, <?php echo htmlspecialchars($name); ?> 👋</h1>
                <p>Manage your scrap pickups and track recycling progress.</p>
            </div>
            <a href="create_request.php" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                <i class="ri-add-line"></i>
                <span>New Pickup</span>
            </a>
        </header>

        <!-- Metric Stat Cards -->
        <section class="stats-grid">
            <div class="stat-item stat-card glass-card">
                <div class="stat-header">
                    <span class="stat-label">Total Requests</span>
                    <div class="stat-icon total"><i class="ri-inbox-archive-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $total; ?></div>
            </div>

            <div class="stat-item stat-card glass-card">
                <div class="stat-header">
                    <span class="stat-label">Pending</span>
                    <div class="stat-icon pending"><i class="ri-time-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $pending; ?></div>
            </div>

            <div class="stat-item stat-card glass-card">
                <div class="stat-header">
                    <span class="stat-label">Assigned</span>
                    <div class="stat-icon assigned"><i class="ri-truck-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $assigned; ?></div>
            </div>

            <div class="stat-item stat-card glass-card">
                <div class="stat-header">
                    <span class="stat-label">Completed</span>
                    <div class="stat-icon completed"><i class="ri-checkbox-circle-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $completed; ?></div>
            </div>
        </section>

        <!-- Main Workspace Grid -->
        <div class="content-grid">
            
            <!-- Quick Actions Panel -->
            <div class="action-panel">
                <a href="create_request.php" class="quick-action-card glass-card">
                    <i class="ri-calendar-event-line"></i>
                    <span>Schedule Pickup</span>
                </a>
                <a href="history.php" class="quick-action-card glass-card">
                    <i class="ri-file-list-3-line"></i>
                    <span>View Pickup History</span>
                </a>
                <a href="profile.php" class="quick-action-card glass-card">
                    <i class="ri-user-settings-line"></i>
                    <span>Manage Profile</span>
                </a>
            </div>

            <!-- Recent Activity Table -->
            <div class="table-card glass-card">
                <div class="table-card-header">
                    <h3>Recent Pickup Requests</h3>
                    <a href="history.php" style="font-size: 13px; font-weight: 600; color: var(--primary); text-decoration: none;">View All</a>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Scrap Type</th>
                                <th>Est. Weight</th>
                                <th>Status</th>
                                <th>Request Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_sql = "SELECT scrap_type, scrap_weight, status, request_date 
                                           FROM activity 
                                           WHERE user_id = ? 
                                           ORDER BY activity_id DESC 
                                           LIMIT 5";

                            if ($stmt = $conn->prepare($recent_sql)) {
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $statusClass = 'badge-pending';
                                        if ($row['status'] === 'Assigned') $statusClass = 'badge-assigned';
                                        if ($row['status'] === 'Completed') $statusClass = 'badge-completed';

                                        echo "<tr>";
                                        echo "<td><strong>" . htmlspecialchars($row['scrap_type']) . "</strong></td>";
                                        echo "<td>" . htmlspecialchars($row['scrap_weight']) . " kg</td>";
                                        echo "<td><span class='badge {$statusClass}'>" . htmlspecialchars($row['status']) . "</span></td>";
                                        echo "<td>" . htmlspecialchars($row['request_date']) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' style='text-align:center; color:var(--text-muted); padding:32px;'>No pickup requests found.</td></tr>";
                                }
                                $stmt->close();
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

    <!-- Mouse Glow Coordinate Track Script -->
    <script>
        document.addEventListener('mousemove', (e) => {
            const x = (e.clientX / window.innerWidth) * 100;
            const y = (e.clientY / window.innerHeight) * 100;
            document.documentElement.style.setProperty('--mouse-x', `${x}%`);
            document.documentElement.style.setProperty('--mouse-y', `${y}%`);
        });
    </script>
</body>

</html>