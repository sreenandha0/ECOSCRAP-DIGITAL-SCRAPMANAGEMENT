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
    <link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
       
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
