<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// Protect Page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Admin") {
    header("Location: ../login.php");
    exit();
}

// Helper to safely fetch total counts
function getCount($conn, $query) {
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['total'];
    }
    return 0;
}

// Key Dashboard Metrics
$totalUsers        = getCount($conn, "SELECT COUNT(*) AS total FROM user");
$totalCollectors   = getCount($conn, "SELECT COUNT(*) AS total FROM scrapcollector");
$pendingRequests   = getCount($conn, "SELECT COUNT(*) AS total FROM activity WHERE status='Pending'");
$completedRequests = getCount($conn, "SELECT COUNT(*) AS total FROM activity WHERE status='Completed'");

// Query: Recent Pickup Requests (Top 5)
$recentRequests = $conn->query("
    SELECT
        activity.activity_id,
        user.name,
        activity.scrap_type,
        activity.scrap_weight,
        activity.status,
        activity.request_date
    FROM activity
    INNER JOIN user ON activity.user_id = user.user_id
    ORDER BY activity.request_date DESC
    LIMIT 5
");

// Query: Collectors Pending Verification (Top 5)
$pendingCollectors = $conn->query("
    SELECT
        collector_id,
        name,
        email,
        phone,
        vehicle_no,
        pincode
    FROM scrapcollector
    WHERE verification_status='Pending'
    ORDER BY created_at DESC
    LIMIT 5
");

// Helper for dynamic navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
function isActive($pageName, $currentPage) {
    return $pageName === $currentPage ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | EcoScrap</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Design System CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            background-color: var(--bg-color, #f8fafc);
            color: var(--text-main, #0f172a);
            display: flex;
            position: relative;
            overflow-x: hidden;
            font-family: 'Inter', sans-serif;
            margin: 0;
        }

        /* Ambient Glow Effects */
        .ambient-blur {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.4;
        }

        .blur-1 {
            width: 600px;
            height: 600px;
            top: -10%;
            right: 0%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
        }

        .blur-2 {
            width: 500px;
            height: 500px;
            bottom: -5%;
            left: 200px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%);
        }

        /* Admin Sidebar Drawer Navigation */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid var(--surface-border, #e2e8f0);
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 100;
            box-sizing: border-box;
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main, #0f172a);
            text-decoration: none;
            margin-bottom: 24px;
            padding-left: 8px;
        }

        .admin-badge-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: rgba(15, 23, 42, 0.08);
            color: var(--text-muted, #64748b);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            padding: 14px 12px 4px 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 12px;
            color: var(--text-muted, #64748b);
            text-decoration: none;
            font-weight: 500;
            font-size: 13.5px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .nav-link i {
            font-size: 18px;
        }

        .nav-link:hover {
            color: var(--text-main, #0f172a);
            background: rgba(15, 23, 42, 0.04);
        }

        .nav-link.active {
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            font-weight: 600;
        }

        .sidebar-footer {
            padding-top: 16px;
            border-top: 1px solid var(--surface-border, #e2e8f0);
            margin-top: 16px;
        }

        .logout-link {
            color: #ef4444;
        }

        .logout-link:hover {
            background: rgba(239, 68, 68, 0.08);
            color: #dc2626;
        }

        /* Workspace Main Layout */
        .workspace-container {
            margin-left: 260px;
            flex: 1;
            padding: 40px;
            max-width: 1400px;
            position: relative;
            z-index: 1;
            box-sizing: border-box;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title h1 {
            font-size: 26px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .page-title p {
            font-size: 14px;
            color: var(--text-muted, #64748b);
            margin: 4px 0 0 0;
        }

        /* Metric Grid Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 36px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border, #e2e8f0);
            border-radius: 16px;
            padding: 22px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
            border-color: #10b981;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted, #64748b);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.users { background: rgba(14, 165, 233, 0.1); color: #0ea5e9; }
        .stat-icon.collectors { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
        .stat-icon.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-icon.completed { background: rgba(16, 185, 129, 0.1); color: #10b981; }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .dashboard-sections {
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border, #e2e8f0);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h3 i {
            color: #10b981;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .eco-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .eco-table th {
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted, #64748b);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--surface-border, #e2e8f0);
        }

        .eco-table td {
            padding: 16px;
            font-size: 14px;
            border-bottom: 1px solid var(--surface-border, #e2e8f0);
            vertical-align: middle;
        }

        .eco-table tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-assigned { background: rgba(99, 102, 241, 0.1); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.2); }
        .badge-completed { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); }

        .btn-action-approve {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-action-approve:hover {
            background: #10b981;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .workspace-container { margin-left: 0; padding: 24px 16px; }
        }
    </style>
</head>

<body>

    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <!-- Exact Navigation Mapping -->
<aside class="sidebar">
    <div>
        <a href="dashboard.php" class="sidebar-brand">
            <i class="ri-recycle-line" style="color:#10b981;"></i>
            <span>EcoScrap</span>
            <span class="admin-badge-label">Admin</span>
        </a>

        <ul class="nav-menu">

            <!-- Dashboard -->
            <li>
                <a href="dashboard.php" class="nav-link <?php echo isActive('dashboard.php', $currentPage); ?>">
                    <i class="ri-dashboard-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>


            <!-- Collector Management -->
            <li class="nav-section-title">Collector Management</li>

            <li>
                <a href="approve_collectors.php" class="nav-link <?php echo isActive('approve_collectors.php', $currentPage); ?>">
                    <i class="ri-user-shared-line"></i>
                    <span>Collector Approvals</span>
                </a>
            </li>
            <li>
                <a href="manageuser.php" class="nav-link <?php echo isActive('manageuser.php', $currentPage); ?>">
                    <i class="ri-user-shared-line"></i>
                    <span>Manage Users</span>
                </a>
            </li>

            <!-- Pickup Management -->
            <li class="nav-section-title">Pickup Management</li>

            <li>
                <a href="manage.php" class="nav-link <?php echo isActive('manage.php', $currentPage); ?>">
                    <i class="ri-inbox-archive-line"></i>
                    <span>Manage Requests</span>
                </a>
            </li>

            <!-- System -->
            <li class="nav-section-title">System</li>

            <li>
                <a href="reports.php" class="nav-link <?php echo isActive('reports.php', $currentPage); ?>">
                    <i class="ri-bar-chart-box-line"></i>
                    <span>Reports & Analytics</span>
                </a>
            </li>

            <li>
                <a href="profile.php" class="nav-link <?php echo isActive('profile.php', $currentPage); ?>">
                    <i class="ri-user-3-line"></i>
                    <span>Admin Profile</span>
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

    <!-- Main Workspace Area -->
    <main class="workspace-container">

        <header class="page-header">
            <div class="page-title">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?> 👋</h1>
                <p>System overview, user metrics, and pending collector approvals.</p>
            </div>
        </header>

        <!-- KPI Metrics Grid -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Total Users</span>
                    <div class="stat-icon users"><i class="ri-user-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $totalUsers; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Total Collectors</span>
                    <div class="stat-icon collectors"><i class="ri-user-follow-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $totalCollectors; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Pending Pickups</span>
                    <div class="stat-icon pending"><i class="ri-time-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $pendingRequests; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Completed Pickups</span>
                    <div class="stat-icon completed"><i class="ri-checkbox-circle-line"></i></div>
                </div>
                <div class="stat-value"><?php echo $completedRequests; ?></div>
            </div>
        </section>

        <!-- Table Overview Sections -->
        <div class="dashboard-sections">

            <!-- Collector Approvals Queue -->
            <div class="glass-card">
                <div class="card-header">
                    <h3>
                        <i class="ri-user-unfollow-line"></i>
                        Collectors Waiting For Approval
                    </h3>
                </div>

                <div class="table-responsive">
                    <table class="eco-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Vehicle</th>
                                <th>Pincode</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pendingCollectors && $pendingCollectors->num_rows > 0): ?>
                                <?php while ($collector = $pendingCollectors->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($collector['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($collector['email']); ?></td>
                                        <td><?php echo htmlspecialchars($collector['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($collector['vehicle_no']); ?></td>
                                        <td><?php echo htmlspecialchars($collector['pincode']); ?></td>
                                        <td>
                                            <a href="approve_collectors.php" class="btn-action-approve">
                                                <i class="ri-check-line"></i>
                                                <span>Approve</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:var(--text-muted, #64748b); padding:32px;">
                                        No pending collector approvals at this time.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Pickup Activity -->
            <div class="glass-card">
                <div class="card-header">
                    <h3>
                        <i class="ri-history-line"></i>
                        Recent Pickup Requests
                    </h3>
                </div>

                <div class="table-responsive">
                    <table class="eco-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Scrap Type</th>
                                <th>Weight</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentRequests && $recentRequests->num_rows > 0): ?>
                                <?php while ($row = $recentRequests->fetch_assoc()): 
                                    $statusClass = 'badge-pending';
                                    if ($row['status'] === 'Assigned') $statusClass = 'badge-assigned';
                                    if ($row['status'] === 'Completed') $statusClass = 'badge-completed';
                                ?>
                                    <tr>
                                        <td>#<?php echo (int)$row['activity_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['scrap_type']); ?></td>
                                        <td><?php echo number_format((float)$row['scrap_weight'], 1); ?> kg</td>
                                        <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                        <td><?php echo date("M d, Y - h:i A", strtotime($row['request_date'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:var(--text-muted, #64748b); padding:32px;">
                                        No recent pickup requests found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

</body>
</html>
