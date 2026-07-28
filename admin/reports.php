<?php
// admin/reports.php
session_start();

// Check if admin is logged in (adjust session variable if needed)
if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db.php';

// ----------------------------------------------------
// 1. Fetch Summary Analytics
// ----------------------------------------------------

// Total Users
$userCountQuery = $conn->query("SELECT COUNT(*) AS total FROM user");
$totalUsers = $userCountQuery ? $userCountQuery->fetch_assoc()['total'] : 0;

// Total Scrap Collectors
$collectorCountQuery = $conn->query("SELECT COUNT(*) AS total FROM scrapcollector");
$totalCollectors = $collectorCountQuery ? $collectorCountQuery->fetch_assoc()['total'] : 0;

// Active / Approved Collectors
$approvedCollectorsQuery = $conn->query("SELECT COUNT(*) AS total FROM scrapcollector WHERE verification_status = 'Approved'");
$approvedCollectors = $approvedCollectorsQuery ? $approvedCollectorsQuery->fetch_assoc()['total'] : 0;

// Total Activity / Pickup Requests
$activityCountQuery = $conn->query("SELECT COUNT(*) AS total FROM activity");
$totalActivities = $activityCountQuery ? $activityCountQuery->fetch_assoc()['total'] : 0;

// Completed Pickups
$completedPickupsQuery = $conn->query("SELECT COUNT(*) AS total FROM activity WHERE status = 'Completed'");
$completedPickups = $completedPickupsQuery ? $completedPickupsQuery->fetch_assoc()['total'] : 0;

// Pending Pickups
$pendingPickupsQuery = $conn->query("SELECT COUNT(*) AS total FROM activity WHERE status = 'Pending'");
$pendingPickups = $pendingPickupsQuery ? $pendingPickupsQuery->fetch_assoc()['total'] : 0;


// ----------------------------------------------------
// 2. Fetch Detailed Collectors List
// ----------------------------------------------------
$collectorsResult = $conn->query("
    SELECT 
        collector_id, 
        name, 
        email, 
        phone, 
        vehicle_no, 
        pincode, 
        availability_status, 
        verification_status, 
        completed_pickups, 
        created_at 
    FROM scrapcollector 
    ORDER BY created_at DESC
");


// ----------------------------------------------------
// 3. Fetch Recent Activity Logs with User & Collector Info
// ----------------------------------------------------
$recentActivitiesResult = $conn->query("
    SELECT 
        a.activity_id,
        u.name AS user_name,
        c.name AS collector_name,
        a.scrap_type,
        a.status,
        a.request_date AS activity_date
    FROM activity a
    LEFT JOIN user u ON a.user_id = u.user_id
    LEFT JOIN scrapcollector c ON a.collector_id = c.collector_id
    ORDER BY a.request_date DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | EcoScrap Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #080a1b;
            color: #e2e8f0;
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="min-h-screen p-6 font-sans">

    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-5">
            <div>
                <h1 class="text-3xl font-bold text-white tracking-tight">System Reports & Analytics</h1>
                <p class="text-slate-400 text-sm mt-1">Overview of registered users, scrap collectors, and pickup activities.</p>
            </div>
            <div>
                <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium transition flex items-center gap-2 text-sm shadow-lg shadow-blue-500/20">
                    <i class="fa-solid fa-print"></i> Export / Print Report
                </button>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <div class="glass-card p-5 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Total Users</p>
                        <h3 class="text-3xl font-extrabold text-white mt-1"><?= number_format($totalUsers) ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/10 text-blue-400 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card p-5 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Scrap Collectors</p>
                        <h3 class="text-3xl font-extrabold text-white mt-1"><?= number_format($totalCollectors) ?></h3>
                        <span class="text-xs text-emerald-400 font-medium"><?= $approvedCollectors ?> Approved</span>
                    </div>
                    <div class="w-12 h-12 bg-emerald-500/10 text-emerald-400 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-truck-pickup"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card p-5 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Total Pickup Requests</p>
                        <h3 class="text-3xl font-extrabold text-white mt-1"><?= number_format($totalActivities) ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-purple-500/10 text-purple-400 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-recycle"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card p-5 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Completed Pickups</p>
                        <h3 class="text-3xl font-extrabold text-white mt-1"><?= number_format($completedPickups) ?></h3>
                        <span class="text-xs text-amber-400 font-medium"><?= $pendingPickups ?> Pending</span>
                    </div>
                    <div class="w-12 h-12 bg-amber-500/10 text-amber-400 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Registered Collectors Section -->
        <div class="glass-card rounded-2xl p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-id-card text-blue-400"></i> Registered Collectors Summary
                </h2>
                <span class="text-xs bg-slate-800 text-slate-300 px-3 py-1 rounded-full font-mono">Table: scrapcollector</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-xs font-semibold tracking-wider">
                        <tr>
                            <th class="p-3 rounded-l-lg">ID</th>
                            <th class="p-3">Collector Name</th>
                            <th class="p-3">Contact</th>
                            <th class="p-3">Vehicle No.</th>
                            <th class="p-3">Pincode</th>
                            <th class="p-3">Availability</th>
                            <th class="p-3">Verification</th>
                            <th class="p-3 rounded-r-lg">Completed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php if ($collectorsResult && $collectorsResult->num_rows > 0): ?>
                            <?php while ($c = $collectorsResult->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="p-3 font-mono text-slate-400">#<?= htmlspecialchars($c['collector_id']) ?></td>
                                    <td class="p-3 font-semibold text-white"><?= htmlspecialchars($c['name']) ?></td>
                                    <td class="p-3">
                                        <div><?= htmlspecialchars($c['email']) ?></div>
                                        <div class="text-xs text-slate-500"><?= htmlspecialchars($c['phone']) ?></div>
                                    </td>
                                    <td class="p-3 font-mono text-xs text-slate-300"><?= htmlspecialchars($c['vehicle_no']) ?></td>
                                    <td class="p-3 text-slate-400"><?= htmlspecialchars($c['pincode']) ?></td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded-md font-medium <?= strtolower($c['availability_status']) === 'available' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                                            <?= htmlspecialchars($c['availability_status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded-md font-medium <?= strtolower($c['verification_status']) === 'approved' ? 'bg-blue-500/10 text-blue-400' : 'bg-amber-500/10 text-amber-400' ?>">
                                            <?= htmlspecialchars($c['verification_status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-semibold text-center text-white"><?= htmlspecialchars($c['completed_pickups']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="p-4 text-center text-slate-500">No collectors found in `scrapcollector`.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Pickup Activity Logs -->
        <div class="glass-card rounded-2xl p-6">
            <h2 class="text-xl font-bold text-white mb-5 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-purple-400"></i> Recent Activity Logs
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/60 text-slate-400 uppercase text-xs font-semibold tracking-wider">
                        <tr>
                            <th class="p-3 rounded-l-lg">Activity ID</th>
                            <th class="p-3">User</th>
                            <th class="p-3">Collector</th>
                            <th class="p-3">Scrap Type</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 rounded-r-lg">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php if ($recentActivitiesResult && $recentActivitiesResult->num_rows > 0): ?>
                            <?php while ($a = $recentActivitiesResult->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="p-3 font-mono text-slate-400">#<?= htmlspecialchars($a['activity_id']) ?></td>
                                    <td class="p-3 font-medium text-white"><?= htmlspecialchars($a['user_name'] ?? 'N/A') ?></td>
                                    <td class="p-3 font-medium text-slate-300"><?= htmlspecialchars($a['collector_name'] ?? 'Unassigned') ?></td>
                                    <td class="p-3 text-slate-400"><?= htmlspecialchars($a['scrap_type'] ?? 'General') ?></td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 text-xs rounded-md font-medium bg-slate-800 text-slate-300">
                                            <?= htmlspecialchars($a['status'] ?? 'Pending') ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-xs text-slate-500"><?= htmlspecialchars($a['activity_date'] ?? '—') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-4 text-center text-slate-500">No activity history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>
