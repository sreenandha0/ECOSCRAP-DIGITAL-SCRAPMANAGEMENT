<?php
session_start();

// Database Configuration for WAMP Server
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ecoscrap_db';

$conn = null;
$db_error = null;

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

// Handle Switching Collector ID via Dropdown Query String
if (isset($_GET['switch_collector_id']) && is_numeric($_GET['switch_collector_id'])) {
    $_SESSION['collector_id'] = intval($_GET['switch_collector_id']);
    header("Location: dashboard.php");
    exit();
}

// Default Collector ID if session is empty (Default to 1 or first available record)
$collector_id = $_SESSION['collector_id'] ?? 1;

// Handle AJAX Status Update request for scrapcollector table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $new_status = $_POST['status'] ?? 'Available';
    
    $allowed_statuses = ['Available', 'Busy', 'Offline'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit();
    }

    if ($conn && !$conn->connect_error) {
        $stmt = $conn->prepare("UPDATE scrapcollector SET availability_status = ? WHERE collector_id = ?");
        $stmt->bind_param("si", $new_status, $collector_id);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success, 'status' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database connection offline']);
    }
    exit();
}

$collector = null;
if ($conn && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT * FROM scrapcollector WHERE collector_id = ?");
    $stmt->bind_param("i", $collector_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $collector = $row;
    }
    $stmt->close();

    // Fallback if requested collector_id doesn't exist: fetch first available record
    if (!$collector) {
        $res_fallback = $conn->query("SELECT * FROM scrapcollector ORDER BY collector_id ASC LIMIT 1");
        if ($res_fallback && $row = $res_fallback->fetch_assoc()) {
            $collector = $row;
            $collector_id = $collector['collector_id'];
            $_SESSION['collector_id'] = $collector_id;
        }
    }
}

$all_collectors = [];
if ($conn && !$conn->connect_error) {
    $col_res = $conn->query("SELECT collector_id, name, vehicle_no, pincode, availability_status FROM scrapcollector ORDER BY collector_id ASC");
    if ($col_res && $col_res->num_rows > 0) {
        while ($c_row = $col_res->fetch_assoc()) {
            $all_collectors[] = $c_row;
        }
    }
}

$activity_requests = [];
$pincode = $collector['pincode'] ?? '';

if ($conn && !$conn->connect_error) {
    // Select activity records assigned to this collector OR matching pincode
    $act_sql = "SELECT * FROM activity 
                WHERE collector_id = ? OR (collector_id IS NULL AND pickup_pincode = ?) 
                ORDER BY activity_id DESC";
    $act_stmt = $conn->prepare($act_sql);
    $act_stmt->bind_param("is", $collector_id, $pincode);
    $act_stmt->execute();
    $act_res = $act_stmt->get_result();
    while ($row = $act_res->fetch_assoc()) {
        $activity_requests[] = $row;
    }
    $act_stmt->close();
}

$metrics = [
    'completed_count' => 0,
    'total_weight' => 0.0,
    'total_earned' => 0.0,
    'active_count' => 0
];

if ($conn && !$conn->connect_error) {
    // 1. Overall stats for this collector
    $stat_sql = "SELECT 
                    COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_count,
                    COUNT(CASE WHEN status IN ('Approved', 'Assigned', 'In Progress') THEN 1 END) as active_count,
                    COALESCE(SUM(CASE WHEN status = 'Completed' THEN scrap_weight ELSE 0 END), 0) as total_weight,
                    COALESCE(SUM(CASE WHEN status = 'Completed' THEN amount ELSE 0 END), 0) as total_earned
                 FROM activity WHERE collector_id = ?";
    $stat_stmt = $conn->prepare($stat_sql);
    $stat_stmt->bind_param("i", $collector_id);
    $stat_stmt->execute();
    $stat_res = $stat_stmt->get_result();
    if ($s_row = $stat_res->fetch_assoc()) {
        $metrics['completed_count'] = intval($s_row['completed_count']);
        $metrics['active_count'] = intval($s_row['active_count']);
        $metrics['total_weight'] = floatval($s_row['total_weight']);
        $metrics['total_earned'] = floatval($s_row['total_earned']);
    }
    $stat_stmt->close();

    // Combine with completed_pickups column from scrapcollector if higher
    if (isset($collector['completed_pickups']) && $collector['completed_pickups'] > $metrics['completed_count']) {
        $metrics['completed_count'] = intval($collector['completed_pickups']);
    }
}

$material_breakdown = [];
if ($conn && !$conn->connect_error) {
    $mat_sql = "SELECT scrap_type, SUM(scrap_weight) as weight_sum, COUNT(*) as total_items 
                FROM activity 
                WHERE collector_id = ? OR pickup_pincode = ?
                GROUP BY scrap_type";
    $mat_stmt = $conn->prepare($mat_sql);
    $mat_stmt->bind_param("is", $collector_id, $pincode);
    $mat_stmt->execute();
    $mat_res = $mat_stmt->get_result();
    while ($m_row = $mat_res->fetch_assoc()) {
        $material_breakdown[] = $m_row;
    }
    $mat_stmt->close();
}

$monthly_trends = [];
if ($conn && !$conn->connect_error) {
    $trend_sql = "SELECT DATE_FORMAT(request_date, '%b %Y') as month_name, 
                         COUNT(*) as total_pickups, 
                         COALESCE(SUM(scrap_weight), 0) as total_weight 
                  FROM activity 
                  WHERE collector_id = ? OR pickup_pincode = ?
                  GROUP BY DATE_FORMAT(request_date, '%Y-%m') 
                  ORDER BY request_date ASC 
                  LIMIT 6";
    $trend_stmt = $conn->prepare($trend_sql);
    $trend_stmt->bind_param("is", $collector_id, $pincode);
    $trend_stmt->execute();
    $trend_res = $trend_stmt->get_result();
    while ($t_row = $trend_res->fetch_assoc()) {
        $monthly_trends[] = $t_row;
    }
    $trend_stmt->close();
}

// Compute initials for collector avatar
$name_str = $collector['name'] ?? 'Collector';
$name_parts = explode(' ', trim($name_str));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoScrap Portal - <?php echo htmlspecialchars($collector['name'] ?? 'Dashboard'); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js for DB Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        eco: {
                            light: '#82C843',
                            primary: '#2E7D32',
                            dark: '#004D40',
                            accent: '#00B4D8',
                            cyan: '#0288D1',
                            bg: '#F4F7F6'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f4;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #a8d5ba;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #2E7D32;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 231, 235, 0.8);
        }
        .bg-pattern {
            background-image: radial-gradient(rgba(46, 125, 50, 0.08) 1px, transparent 0);
            background-size: 16px 16px;
        }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col md:flex-row bg-pattern">

    <!-- Sidebar Navigation -->
    <aside id="sidebar" class="w-full md:w-64 bg-white border-r border-slate-200 flex-shrink-0 flex flex-col justify-between transition-all duration-300 z-30">
        <div>
            <!-- Logo Header matching original brand mark -->
            <div class="p-5 flex items-center justify-between border-b border-slate-100">
                <div class="flex items-center space-x-3 cursor-pointer" onclick="window.location.reload()">
                    <div class="w-10 h-10 relative flex items-center justify-center">
                        <svg viewBox="0 0 100 100" class="w-10 h-10">
                            <path d="M 20 30 C 40 10, 80 10, 90 35 C 70 25, 40 30, 20 30 Z" fill="#82C843"/>
                            <path d="M 15 45 C 35 25, 85 25, 92 50 C 70 38, 35 45, 15 45 Z" fill="#2E7D32"/>
                            <path d="M 18 60 C 35 42, 85 45, 88 72 C 65 55, 30 60, 18 60 Z" fill="#004D40"/>
                            <path d="M 10 50 C 10 85, 50 95, 80 88 C 60 92, 45 80, 48 72 C 55 65, 65 78, 40 80 C 20 80, 12 60, 10 50 Z" fill="#00B4D8"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg leading-tight text-slate-800 tracking-tight">ECOSCRAP</h1>
                        <p class="text-[10px] uppercase tracking-widest text-emerald-600 font-bold">COLLECTOR PORTAL</p>
                    </div>
                </div>
                <button id="mobile-menu-btn" class="md:hidden text-slate-500 hover:text-emerald-700">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5">
                <a href="dashboard.php" class="nav-item active flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition-all bg-emerald-100/80 text-emerald-800 shadow-xs">
                    <i class="fa-solid fa-gauge-high text-emerald-600 text-base"></i>
                    <span>Dashboard</span>
                </a>
                <a href="assigned_requests.php" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-50 hover:text-emerald-700">
                    <i class="fa-solid fa-truck-ramp-box text-base"></i>
                    <span>Assigned Requests</span>
                    <span class="ml-auto bg-emerald-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?php echo count($activity_requests); ?></span>
                </a>
                <a href="verify_qr.php" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-50 hover:text-emerald-700">
                    <i class="fa-solid fa-qrcode text-base"></i>
                    <span>Verify QR Code</span>
                </a>
                <a href="completed.php" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-50 hover:text-emerald-700">
                    <i class="fa-solid fa-circle-check text-base"></i>
                    <span>Completed Pickups</span>
                </a>
                <a href="profile.php" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-xl font-medium text-sm transition-all text-slate-600 hover:bg-slate-50 hover:text-emerald-700">
                    <i class="fa-solid fa-id-card text-base"></i>
                    <span>Collector Profile</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer Connection Info -->
        <div class="p-4 border-t border-slate-100 space-y-2">
            <a href="verify_qr.php" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-4 rounded-xl shadow-xs transition flex items-center justify-center space-x-2 text-sm">
                <i class="fa-solid fa-camera text-xs"></i>
                <span>Scan Customer QR</span>
            </a>
            <div class="pt-2 text-[11px] text-slate-400 flex items-center justify-between">
                <span>Database: <strong class="text-slate-600"><?php echo $conn && !$conn->connect_error ? 'MySQL Connected' : 'Disconnected'; ?></strong></span>
                <span class="inline-block w-2 h-2 rounded-full <?php echo $conn && !$conn->connect_error ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'; ?>"></span>
            </div>
        </div>
    </aside>

    <!-- Main Content Container -->
    <main class="flex-1 flex flex-col min-w-0 overflow-y-auto custom-scrollbar">
        
        <!-- Header Bar -->
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4 sticky top-0 z-20 shadow-xs">
            <div>
                <div class="flex items-center space-x-2">
                    <h2 class="text-xl md:text-2xl font-bold tracking-tight text-slate-900">COLLECTOR OVERVIEW</h2>
                    <span class="bg-emerald-100 text-emerald-800 text-xs font-semibold px-2.5 py-0.5 rounded-full border border-emerald-300 flex items-center gap-1">
                        <i class="fa-solid fa-shield-check text-xs"></i> <?php echo htmlspecialchars($collector['verification_status'] ?? 'Pending'); ?>
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    Logged in as <strong class="text-emerald-800"><?php echo htmlspecialchars($collector['name'] ?? 'N/A'); ?></strong> 
                    (ID: #<?php echo htmlspecialchars($collector['collector_id'] ?? '0'); ?>)
                </p>
            </div>

            <!-- Header Controls & Database Collector Selector -->
            <div class="flex items-center space-x-3 flex-wrap gap-y-2">
                
                <!-- Availability Status Toggle (DB AJAX) -->
                <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200">
                    <?php $cur_status = $collector['availability_status'] ?? 'Available'; ?>
                    <button onclick="updateAvailability('Available')" id="btnAvailable" class="px-3 py-1 text-xs font-bold rounded-lg transition <?php echo ($cur_status === 'Available') ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-200'; ?>">
                        Available
                    </button>
                    <button onclick="updateAvailability('Busy')" id="btnBusy" class="px-3 py-1 text-xs font-bold rounded-lg transition <?php echo ($cur_status === 'Busy') ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-200'; ?>">
                        Busy
                    </button>
                    <button onclick="updateAvailability('Offline')" id="btnOffline" class="px-3 py-1 text-xs font-bold rounded-lg transition <?php echo ($cur_status === 'Offline') ? 'bg-slate-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-200'; ?>">
                        Offline
                    </button>
                </div>

                <!-- Notifications Button -->
                <button onclick="showToast('Active DB pincode area: <?php echo htmlspecialchars($collector['pincode'] ?? 'N/A'); ?>')" class="p-2.5 text-slate-500 hover:text-emerald-700 hover:bg-slate-100 rounded-xl transition relative border border-slate-200" title="Pincode Info">
                    <i class="fa-solid fa-bell text-base"></i>
                    <?php if (count($activity_requests) > 0): ?>
                        <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white"></span>
                    <?php endif; ?>
                </button>

                <!-- Database Profile Switcher Dropdown -->
                <div class="flex items-center space-x-2 border-l border-slate-200 pl-3">
                    <div class="w-9 h-9 rounded-full bg-emerald-700 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                        <?php echo $initials; ?>
                    </div>
                    <div class="text-left">
                        <?php if (!empty($all_collectors)): ?>
                            <select onchange="location.href='dashboard.php?switch_collector_id=' + this.value" class="bg-transparent text-xs font-bold text-slate-800 outline-none cursor-pointer">
                                <?php foreach ($all_collectors as $col_item): ?>
                                    <option value="<?php echo $col_item['collector_id']; ?>" <?php echo ($col_item['collector_id'] == $collector['collector_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($col_item['name']); ?> (#<?php echo $col_item['collector_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <span class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($collector['name'] ?? 'N/A'); ?></span>
                        <?php endif; ?>
                        <p class="text-[10px] text-slate-500 font-medium">
                            <?php echo htmlspecialchars($collector['vehicle_no'] ?? 'N/A'); ?> • Pin: <?php echo htmlspecialchars($collector['pincode'] ?? 'N/A'); ?>
                        </p>
                    </div>
                </div>

            </div>
        </header>

        <!-- Main Dashboard Content -->
        <div class="p-4 md:p-6 space-y-6">

            <?php if ($db_error): ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded-2xl text-red-800 text-xs flex items-center space-x-3">
                    <i class="fa-solid fa-triangle-exclamation text-base text-red-600"></i>
                    <div>
                        <strong>Database Notice:</strong> <?php echo htmlspecialchars($db_error); ?>. Please ensure MySQL server is running at <code>localhost:3306</code> with database <code>ecoscrap_db</code>.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Metrics Cards Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Metric Card 1: Completed Pickups -->
                <div class="glass-card rounded-2xl p-4 flex flex-col justify-between shadow-xs hover:shadow-md transition border-l-4 border-l-emerald-600">
                    <div class="flex justify-between items-start mb-1">
                        <div>
                            <h3 class="text-xs uppercase font-bold tracking-wider text-slate-500">Completed Pickups</h3>
                            <p class="text-[10px] text-slate-400 font-medium">Table: activity / scrapcollector</p>
                        </div>
                        <span class="p-2 bg-emerald-100 text-emerald-700 rounded-xl">
                            <i class="fa-solid fa-boxes-packing text-sm"></i>
                        </span>
                    </div>
                    <div class="flex items-baseline space-x-2 my-2">
                        <span class="text-3xl font-extrabold text-slate-800"><?php echo $metrics['completed_count']; ?></span>
                        <span class="text-xs text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded-md">Verified</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Total Active Requests: <strong class="text-slate-700"><?php echo $metrics['active_count']; ?></strong></p>
                </div>

                <!-- Metric Card 2: Status & Vehicle Details -->
                <div class="glass-card rounded-2xl p-4 flex flex-col justify-between shadow-xs hover:shadow-md transition border-l-4 border-l-sky-500">
                    <div class="flex justify-between items-start mb-1">
                        <div>
                            <h3 class="text-xs uppercase font-bold tracking-wider text-slate-500">Collector Status</h3>
                            <p class="text-[10px] text-slate-400 font-medium">Field: availability_status</p>
                        </div>
                        <span id="statusBadge" class="px-2.5 py-1 text-xs font-bold rounded-full flex items-center gap-1.5 <?php echo ($cur_status === 'Available') ? 'bg-emerald-100 text-emerald-800' : (($cur_status === 'Busy') ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'); ?>">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span id="statusLabel"><?php echo htmlspecialchars($cur_status); ?></span>
                        </span>
                    </div>
                    <div class="my-2">
                        <p class="text-xs text-slate-600 font-medium">Vehicle Reg No:</p>
                        <p class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($collector['vehicle_no'] ?? 'Unassigned'); ?></p>
                    </div>
                    <p class="text-[11px] text-slate-500">Target Pincode: <strong class="text-slate-700"><?php echo htmlspecialchars($collector['pincode'] ?? 'N/A'); ?></strong></p>
                </div>

                <!-- Metric Card 3: Weight Recovered -->
                <div class="glass-card rounded-2xl p-4 flex flex-col justify-between shadow-xs hover:shadow-md transition border-l-4 border-l-teal-600">
                    <div class="flex justify-between items-start mb-1">
                        <div>
                            <h3 class="text-xs uppercase font-bold tracking-wider text-slate-500">Scrap Weight</h3>
                            <p class="text-[10px] text-slate-400 font-medium">Sum: scrap_weight</p>
                        </div>
                        <span class="p-2 bg-teal-100 text-teal-700 rounded-xl">
                            <i class="fa-solid fa-weight-hanging text-sm"></i>
                        </span>
                    </div>
                    <div class="flex items-baseline space-x-2 my-2">
                        <span class="text-3xl font-extrabold text-slate-800"><?php echo number_format($metrics['total_weight'], 1); ?></span>
                        <span class="text-xs text-slate-500 font-semibold">Kg</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Phone: <span class="text-emerald-700 font-semibold"><?php echo htmlspecialchars($collector['phone'] ?? 'N/A'); ?></span></p>
                </div>

                <!-- Metric Card 4: Total Revenue/Amount Value -->
                <div class="glass-card rounded-2xl p-4 flex flex-col justify-between shadow-xs hover:shadow-md transition border-l-4 border-l-amber-500">
                    <div class="flex justify-between items-start mb-1">
                        <div>
                            <h3 class="text-xs uppercase font-bold tracking-wider text-slate-500">Value Collected</h3>
                            <p class="text-[10px] text-slate-400 font-medium">Sum: amount (₹)</p>
                        </div>
                        <span class="p-2 bg-amber-100 text-amber-700 rounded-xl">
                            <i class="fa-solid fa-indian-rupee-sign text-sm"></i>
                        </span>
                    </div>
                    <div class="flex items-baseline space-x-2 my-2">
                        <span class="text-3xl font-extrabold text-slate-800">₹<?php echo number_format($metrics['total_earned'], 2); ?></span>
                    </div>
                    <p class="text-[11px] text-slate-500">Verification: <strong class="text-emerald-700"><?php echo htmlspecialchars($collector['verification_status'] ?? 'Approved'); ?></strong></p>
                </div>

            </div>

            <!-- Analytics Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                <!-- Collection Activity Chart -->
                <div class="lg:col-span-7 glass-card rounded-2xl p-5 shadow-xs">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center pb-4 mb-2 border-b border-slate-100 gap-2">
                        <div>
                            <h3 class="font-bold text-slate-800 text-base">Collection Activity Trend</h3>
                            <p class="text-xs text-slate-500">Dynamic database timeline grouped by month</p>
                        </div>
                        <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-lg border border-slate-200">
                            Table: <code>activity</code>
                        </span>
                    </div>
                    <div class="h-64 w-full flex items-center justify-center">
                        <?php if (!empty($monthly_trends)): ?>
                            <canvas id="collectorActivityChart"></canvas>
                        <?php else: ?>
                            <div class="text-center py-10 text-slate-400">
                                <i class="fa-solid fa-chart-line text-3xl mb-2 text-slate-300"></i>
                                <p class="text-xs">No activity record logs available in table <code>activity</code> for this collector.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Material Distribution Chart -->
                <div class="lg:col-span-5 glass-card rounded-2xl p-5 shadow-xs flex flex-col justify-between">
                    <div class="pb-3 border-b border-slate-100 flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-slate-800 text-base">Scrap Material Share</h3>
                            <p class="text-xs text-slate-500">Scrap types collected (field: <code>scrap_type</code>)</p>
                        </div>
                        <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">Live SQL</span>
                    </div>
                    
                    <div class="relative flex items-center justify-center my-3">
                        <div class="w-48 h-48 flex items-center justify-center">
                            <?php if (!empty($material_breakdown)): ?>
                                <canvas id="materialDoughnutChart"></canvas>
                            <?php else: ?>
                                <div class="text-center text-slate-400">
                                    <i class="fa-solid fa-chart-pie text-3xl mb-2 text-slate-300"></i>
                                    <p class="text-xs">No scrap categories found in database.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dynamic Legend -->
                    <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100">
                        <?php if (!empty($material_breakdown)): ?>
                            <?php 
                            $badge_colors = ['bg-emerald-600', 'bg-cyan-500', 'bg-lime-500', 'bg-amber-500', 'bg-indigo-500'];
                            $i = 0;
                            foreach ($material_breakdown as $mb): 
                                $c = $badge_colors[$i % count($badge_colors)];
                                $i++;
                            ?>
                                <div class="flex items-center space-x-2">
                                    <span class="w-3 h-3 rounded-full <?php echo $c; ?>"></span>
                                    <span class="text-slate-600 truncate"><?php echo htmlspecialchars($mb['scrap_type']); ?> (<?php echo floatval($mb['weight_sum']); ?> Kg)</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-slate-400 text-[11px] col-span-2 text-center">No categories recorded</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Active Assigned Pickups Section -->
            <div class="glass-card rounded-2xl p-5 shadow-xs">
                <div class="flex flex-col md:flex-row justify-between md:items-center pb-4 mb-4 border-b border-slate-100 gap-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-emerald-600"></i>
                            Database Pickup Activity Log
                        </h3>
                        <p class="text-xs text-slate-500">Live records from table <code>activity</code> matching pincode <strong class="text-slate-700"><?php echo htmlspecialchars($pincode); ?></strong></p>
                    </div>
                    <div class="flex gap-2">
                        <a href="assigned_requests.php" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition flex items-center gap-1">
                            <i class="fa-solid fa-arrow-right-to-bracket text-xs"></i>
                            <span>View All Requests</span>
                        </a>
                        <a href="verify_qr.php" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs transition flex items-center gap-1">
                            <i class="fa-solid fa-qrcode text-xs"></i>
                            <span>Open QR Verification</span>
                        </a>
                    </div>
                </div>

                <!-- Table Displaying DB Rows -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider">
                            <tr>
                                <th class="p-3 rounded-l-lg">Activity ID</th>
                                <th class="p-3">User ID</th>
                                <th class="p-3">Scrap Type</th>
                                <th class="p-3">Weight</th>
                                <th class="p-3">Pickup Address</th>
                                <th class="p-3">Pincode</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">QR Status</th>
                                <th class="p-3">Amount</th>
                                <th class="p-3 rounded-r-lg text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($activity_requests)): ?>
                                <?php foreach ($activity_requests as $act): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="p-3 font-bold text-emerald-800">#<?php echo intval($act['activity_id']); ?></td>
                                        <td class="p-3 font-semibold text-slate-800">User #<?php echo intval($act['user_id']); ?></td>
                                        <td class="p-3">
                                            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold">
                                                <?php echo htmlspecialchars($act['scrap_type'] ?? 'General Scrap'); ?>
                                            </span>
                                        </td>
                                        <td class="p-3 font-bold text-slate-700"><?php echo floatval($act['scrap_weight']); ?> Kg</td>
                                        <td class="p-3 max-w-xs truncate" title="<?php echo htmlspecialchars($act['pickup_address']); ?>">
                                            <?php echo htmlspecialchars($act['pickup_address']); ?>
                                        </td>
                                        <td class="p-3 font-medium text-slate-600"><?php echo htmlspecialchars($act['pickup_pincode']); ?></td>
                                        <td class="p-3">
                                            <?php 
                                            $st = $act['status'] ?? 'Pending';
                                            $st_badge = 'bg-slate-100 text-slate-700';
                                            if ($st === 'Approved' || $st === 'Assigned') $st_badge = 'bg-amber-100 text-amber-800';
                                            elseif ($st === 'In Progress') $st_badge = 'bg-sky-100 text-sky-800';
                                            elseif ($st === 'Completed') $st_badge = 'bg-emerald-100 text-emerald-800';
                                            ?>
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?php echo $st_badge; ?>">
                                                <?php echo htmlspecialchars($st); ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md <?php echo ($act['qr_status'] === 'Used') ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'; ?>">
                                                <?php echo htmlspecialchars($act['qr_status'] ?? 'Unused'); ?>
                                            </span>
                                        </td>
                                        <td class="p-3 font-bold text-slate-800">
                                            <?php echo !empty($act['amount']) ? '₹' . number_format($act['amount'], 2) : '—'; ?>
                                        </td>
                                        <td class="p-3 text-right space-x-1">
                                            <?php if (empty($act['collector_id']) || $act['status'] === 'Approved'): ?>
                                                <a href="accept_pickup.php?id=<?php echo urlencode($act['activity_id']); ?>" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold text-xs transition inline-block">
                                                    Accept
                                                </a>
                                            <?php endif; ?>
                                            <a href="verify_qr.php?id=<?php echo urlencode($act['activity_id']); ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-lg font-semibold text-xs transition inline-block">
                                                Scan QR
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="p-6 text-center text-slate-400">
                                        <i class="fa-solid fa-folder-open text-2xl mb-2 text-slate-300"></i>
                                        <p class="text-xs">No active pickup requests found in table <code>activity</code> for pincode <strong><?php echo htmlspecialchars($pincode); ?></strong>.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <footer class="flex flex-col sm:flex-row justify-between items-center text-xs text-slate-400 pt-2 pb-6 border-t border-slate-200 gap-2">
                <p>EcoScrap Collector Dashboard • Server Time: <span class="font-semibold text-slate-600"><?php echo date('Y-m-d H:i:s'); ?></span></p>
                <p class="font-medium">Active Database Record: <span class="text-emerald-700 font-bold">collector_id = <?php echo intval($collector['collector_id'] ?? 0); ?></span></p>
            </footer>

        </div>
    </main>

    <!-- Notification Toast -->
    <div id="toastBox" class="fixed bottom-5 right-5 bg-slate-900 text-white px-4 py-3 rounded-xl shadow-2xl flex items-center space-x-3 transition-all duration-300 transform translate-y-20 opacity-0 z-50 border border-slate-700">
        <i id="toastIcon" class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
        <span id="toastMessage" class="text-xs font-medium">Action completed successfully!</span>
    </div>

    <script>
        const colors = {
            lightGreen: '#82C843',
            mediumGreen: '#2E7D32',
            darkGreen: '#004D40',
            cyanAccent: '#00B4D8',
            skyBlue: '#0288D1'
        };

        window.onload = function() {
            initDatabaseCharts();
            setupMobileMenu();
        };

        function setupMobileMenu() {
            const btn = document.getElementById('mobile-menu-btn');
            const sidebar = document.getElementById('sidebar');
            if (btn && sidebar) {
                btn.addEventListener('click', () => sidebar.classList.toggle('hidden'));
            }
        }

        // Live availability status update via AJAX back to scrapcollector database table
        function updateAvailability(status) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('status', status);

            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    ['Available', 'Busy', 'Offline'].forEach(s => {
                        const btn = document.getElementById('btn' + s);
                        if (btn) {
                            if (s === status) {
                                if (s === 'Available') btn.className = "px-3 py-1 text-xs font-bold rounded-lg transition bg-emerald-600 text-white shadow-xs";
                                else if (s === 'Busy') btn.className = "px-3 py-1 text-xs font-bold rounded-lg transition bg-amber-500 text-white shadow-xs";
                                else btn.className = "px-3 py-1 text-xs font-bold rounded-lg transition bg-slate-600 text-white shadow-xs";
                            } else {
                                btn.className = "px-3 py-1 text-xs font-bold text-slate-600 rounded-lg hover:bg-slate-200 transition";
                            }
                        }
                    });

                    const lbl = document.getElementById('statusLabel');
                    if (lbl) lbl.innerText = status;

                    showToast("Updated DB availability_status to '" + status + "'");
                } else {
                    showToast("Error updating status: " + (data.message || 'Failed'));
                }
            })
            .catch(err => {
                showToast("Updated status to " + status);
            });
        }

        function initDatabaseCharts() {
            // 1. Monthly Trend Bar Chart
            const trendData = <?php echo json_encode($monthly_trends); ?>;
            const ctxActivity = document.getElementById('collectorActivityChart');

            if (ctxActivity && trendData.length > 0) {
                const labels = trendData.map(d => d.month_name);
                const pickups = trendData.map(d => parseInt(d.total_pickups));
                const weights = trendData.map(d => parseFloat(d.total_weight));

                new Chart(ctxActivity.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Pickups Count',
                                data: pickups,
                                backgroundColor: colors.mediumGreen,
                                borderRadius: 6
                            },
                            {
                                label: 'Scrap Weight (Kg)',
                                data: weights,
                                type: 'line',
                                borderColor: colors.cyanAccent,
                                backgroundColor: colors.cyanAccent,
                                borderWidth: 3,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#F1F5F9' } },
                            y1: { beginAtZero: true, position: 'right', grid: { display: false } }
                        }
                    }
                });
            }

            // 2. Scrap Category Material Doughnut Chart
            const materialData = <?php echo json_encode($material_breakdown); ?>;
            const ctxDoughnut = document.getElementById('materialDoughnutChart');

            if (ctxDoughnut && materialData.length > 0) {
                const labels = materialData.map(d => d.scrap_type);
                const weights = materialData.map(d => parseFloat(d.weight_sum));

                new Chart(ctxDoughnut.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: weights,
                            backgroundColor: [colors.mediumGreen, colors.cyanAccent, colors.lightGreen, '#f59e0b', '#6366f1'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        cutout: '70%'
                    }
                });
            }
        }

        function showToast(msg) {
            const toast = document.getElementById('toastBox');
            const toastMsg = document.getElementById('toastMessage');
            if (toast && toastMsg) {
                toastMsg.innerText = msg;
                toast.classList.remove('translate-y-20', 'opacity-0');
                setTimeout(() => {
                    toast.classList.add('translate-y-20', 'opacity-0');
                }, 3200);
            }
        }
    </script>
</body>
</html>