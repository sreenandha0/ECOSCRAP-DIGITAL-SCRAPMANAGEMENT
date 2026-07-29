<?php
session_start();

// 1. Database Connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "ecoscrap_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Auth Check (Redirect if not logged in)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int) $_SESSION['user_id'];

// Fetch Logged-in User Info
$user_stmt = $conn->prepare("SELECT name FROM user WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result()->fetch_assoc();
$current_user_name = $user_res['name'] ?? 'User';
$user_stmt->close();

// 3. Fetch Requests + Collector Info
$sql = "SELECT 
            a.*, 
            c.name AS collector_name, 
            c.phone AS collector_phone,
            c.email AS collector_email,
            c.vehicle_no AS collector_vehicle
        FROM activity a
        LEFT JOIN scrapcollector c 
            ON a.collector_id = c.collector_id
        WHERE a.user_id = ?
        ORDER BY a.request_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$pending_count     = 0;
$approved_count    = 0;
$assigned_count    = 0;
$in_progress_count = 0;
$completed_count   = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $st = strtolower(trim($row['status'] ?? 'pending'));

        if ($st === 'pending')                 $pending_count++;
        elseif ($st === 'approved')            $approved_count++;
        elseif ($st === 'assigned')            $assigned_count++;
        elseif ($st === 'in progress')         $in_progress_count++;
        elseif ($st === 'verified' || $st === 'completed') $completed_count++;
    }
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pickup Requests | EcoScrap</title>

    <!-- Google Fonts & Remix Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #10B981;
            --secondary: #047857;
            --accent: #0EA5E9;
            --bg-color: #F8FAFC;
            --surface: rgba(255, 255, 255, 0.85);
            --surface-border: rgba(15, 23, 42, 0.08);
            --text-main: #0F172A;
            --text-muted: #64748B;
            --font-main: 'Inter', sans-serif;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            padding-bottom: 50px;
        }

        /* Navigation Header */
        .header-bar {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--surface-border);
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .brand-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .nav-icon-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 20px;
            cursor: pointer;
        }

        .nav-icon-btn .badge-dot {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
        }

        /* Metric Cards */
        .metric-card {
            background: var(--surface);
            backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }

        .metric-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .metric-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
        }

        /* Search & Filter Section */
        .filter-panel {
            background: var(--surface);
            border: 1px solid var(--surface-border);
            border-radius: 14px;
            padding: 14px;
            margin: 24px 0;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-input, .filter-select {
            background: #ffffff;
            border: 1px solid var(--surface-border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            color: var(--text-main);
            outline: none;
        }

        .filter-input { flex: 1; min-width: 200px; }

        .btn-filter-search {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-filter-search:hover { background: var(--secondary); }

        /* Card Wrapper */
        .card-wrapper {
            background: var(--surface);
            backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.03);
            transition: var(--transition);
        }

        .card-wrapper:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
        }

        /* Badges */
        .status-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-pending     { background: rgba(245, 158, 11, 0.12); color: #d97706; }
        .badge-approved    { background: rgba(16, 185, 129, 0.12); color: #047857; }
        .badge-assigned    { background: rgba(14, 165, 233, 0.12); color: #0284c7; }
        .badge-in-progress { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
        .badge-completed   { background: rgba(16, 185, 129, 0.20); color: #059669; }

        /* Collector Box */
        .collector-box {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid var(--surface-border);
            border-radius: 12px;
            padding: 16px;
            margin: 16px 0;
        }

        /* Buttons */
        .btn-custom-outline {
            background: #ffffff;
            border: 1px solid var(--surface-border);
            color: var(--text-main);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-custom-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-custom-primary {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-custom-primary:hover {
            background: var(--secondary);
            color: #ffffff;
        }
    </style>
</head>

<body>

    <!-- Header Navigation Bar -->
    <header class="header-bar">
        <a href="dashboard.php" class="brand-title">
            <i class="ri-leaf-line" style="color: var(--primary);"></i>
            <span>EcoScrap</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="fw-semibold fs-6 d-none d-md-inline">My Pickup Requests</span>
            <button class="nav-icon-btn" title="Notifications">
                <i class="ri-notification-3-line"></i>
                <span class="badge-dot"></span>
            </button>
            <div class="dropdown">
                <button class="btn border-0 dropdown-toggle fw-bold text-dark p-0" data-bs-toggle="dropdown">
                    <i class="ri-user-3-line me-1"></i> <?= htmlspecialchars($current_user_name); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="container my-4">

        <!-- Metrics Row -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-2">
                <div class="metric-card">
                    <span class="metric-label">Pending</span>
                    <span class="metric-value" style="color: #d97706;"><?= sprintf('%02d', $pending_count); ?></span>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card">
                    <span class="metric-label">Approved</span>
                    <span class="metric-value" style="color: #047857;"><?= sprintf('%02d', $approved_count); ?></span>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card">
                    <span class="metric-label">Assigned</span>
                    <span class="metric-value" style="color: #0284c7;"><?= sprintf('%02d', $assigned_count); ?></span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <span class="metric-label">In Progress</span>
                    <span class="metric-value" style="color: #4f46e5;"><?= sprintf('%02d', $in_progress_count); ?></span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <span class="metric-label">Completed</span>
                    <span class="metric-value" style="color: #059669;"><?= sprintf('%02d', $completed_count); ?></span>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <form class="filter-panel" id="filterForm">
            <input type="text" id="searchInput" class="filter-input" placeholder="🔍 Search Pickup...">
            <select id="statusSelect" class="filter-select">
                <option value="">Status (All)</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="Assigned">Assigned</option>
                <option value="In Progress">In Progress</option>
                <option value="Verified">Verified / Completed</option>
            </select>
            <select id="scrapTypeSelect" class="filter-select">
                <option value="">Scrap Type (All)</option>
                <option value="Plastic">Plastic</option>
                <option value="Metal">Metal</option>
                <option value="Glass">Glass</option>
                <option value="Paper">Paper</option>
            </select>
            <button type="submit" class="btn-filter-search">Search</button>
        </form>

        <div class="row g-4">
            <!-- Feed Column (Centered Layout) -->
            <div class="col-12 col-lg-10 mx-auto">
                <?php if (!empty($rows)) { 
                    foreach ($rows as $row) { 
                        $status = $row['status'] ?? 'Pending';
                        $status_clean = strtolower(trim($status));
                ?>
                    <div class="card-wrapper" data-status="<?= htmlspecialchars($status); ?>" data-type="<?= htmlspecialchars($row['scrap_type']); ?>">
                        
                        <!-- Card Top Header -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-bold text-dark fs-6">📦 Request #REQ-<?= htmlspecialchars($row['activity_id']); ?></span>
                            <?php 
                                if ($status_clean === 'pending')                             echo '<span class="status-badge badge-pending">🟡 Pending</span>';
                                elseif ($status_clean === 'approved')                        echo '<span class="status-badge badge-approved">🟢 Approved</span>';
                                elseif ($status_clean === 'assigned')                        echo '<span class="status-badge badge-assigned">🟣 Assigned</span>';
                                elseif ($status_clean === 'in progress')                     echo '<span class="status-badge badge-in-progress">🔵 In Progress</span>';
                                elseif ($status_clean === 'verified' || $status_clean === 'completed') echo '<span class="status-badge badge-completed">🟢 Completed</span>';
                            ?>
                        </div>

                        <!-- Main Info Section -->
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <h5 class="fw-bold mb-1">♻ <?= htmlspecialchars($row['scrap_type']); ?> Scrap</h5>
                                <p class="text-muted small mb-1">📍 <?= htmlspecialchars($row['pickup_address']); ?>, PIN: <?= htmlspecialchars($row['pickup_pincode']); ?></p>
                            </div>
                            <div class="col-md-5 text-md-end">
                                <div class="fw-bold fs-6"><?= htmlspecialchars($row['scrap_weight']); ?> kg</div>
                                <div class="text-muted small">Pickup Date: <?= htmlspecialchars($row['preferred_pickup_date']); ?> (<?= htmlspecialchars($row['pickup_time']); ?>)</div>
                                <?php if (!empty($row['amount'])) { ?>
                                    <div class="fw-bold text-success fs-5">₹ <?= htmlspecialchars($row['amount']); ?></div>
                                <?php } ?>
                            </div>
                        </div>

                        <!-- Collector Box (Only renders AFTER the collector accepts the request) -->
                        <?php 
                            $is_accepted = !empty($row['collector_name']) && !in_array($status_clean, ['pending', 'approved', 'assigned']);
                            if ($is_accepted) { 
                        ?>
                            <div class="collector-box">
                                <span class="fw-bold d-block text-muted small mb-2">👤 ASSIGNED COLLECTOR</span>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['collector_name']); ?></div>
                                        <div class="small text-muted">
                                            <i class="ri-phone-line me-1"></i><?= htmlspecialchars($row['collector_phone']); ?>
                                            <?php if (!empty($row['collector_vehicle'])) { ?>
                                                <span class="ms-2">| <i class="ri-car-line ms-1 me-1"></i><?= htmlspecialchars($row['collector_vehicle']); ?></span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <a href="tel:<?= htmlspecialchars($row['collector_phone']); ?>" class="btn btn-sm btn-outline-success">
                                            <i class="ri-phone-fill"></i> Call Collector
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <!-- Action Controls -->
                        <div class="d-flex gap-2 mt-3 pt-2 border-top border-light">

                            <?php if ($status_clean === 'pending' || $status_clean === 'approved') { ?>

                                <a href="track_status.php?id=<?= htmlspecialchars($row['activity_id']); ?>" class="btn-custom-outline">
                                    <i class="ri-eye-line"></i> View Details
                                </a>

                                <button class="btn-custom-outline text-danger border-danger-subtle">
                                    <i class="ri-close-circle-line"></i> Cancel Request
                                </button>

                            <?php } elseif ($status_clean === 'assigned') { ?>

                                <button class="btn-custom-outline text-muted" disabled>
                                    <i class="ri-time-line"></i> Awaiting Collector Acceptance
                                </button>

                                <a href="track_status.php?id=<?= htmlspecialchars($row['activity_id']); ?>" class="btn-custom-outline">
                                    <i class="ri-map-pin-time-line"></i> Track Status
                                </a>

                            <?php } elseif ($status_clean === 'in progress') { ?>

                                <?php if (!empty($row['qr_code'])) { ?>
                                    <a href="../uploads/qr/<?= htmlspecialchars($row['qr_code']); ?>" target="_blank" class="btn-custom-primary">
                                        <i class="ri-qr-code-line"></i> View QR Pass
                                    </a>
                                <?php } ?>

                                <a href="track_status.php?id=<?= htmlspecialchars($row['activity_id']); ?>" class="btn-custom-outline">
                                    <i class="ri-map-pin-time-line"></i> Track Status
                                </a>

                            <?php } elseif ($status_clean === 'verified' || $status_clean === 'completed') { ?>

                                <a href="track_status.php?id=<?= htmlspecialchars($row['activity_id']); ?>" class="btn-custom-outline">
                                    <i class="ri-file-list-line"></i> View Receipt
                                </a>

                                <button class="btn-custom-outline text-warning border-warning-subtle">
                                    <i class="ri-star-line"></i> Rate Collector
                                </button>

                            <?php } ?>

                        </div>

                    </div>
                <?php 
                    } // end foreach
                } else { 
                ?>
                    <div class="card-wrapper text-center py-5">
                        <i class="ri-inbox-line text-muted mb-2" style="font-size: 48px;"></i>
                        <h5 class="fw-bold text-dark mb-1">No Requests Found</h5>
                        <p class="text-muted small mb-0">You haven't scheduled any pickup requests yet.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side Filter Script
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const searchVal = document.getElementById('searchInput').value.toLowerCase();
            const statusVal = document.getElementById('statusSelect').value.toLowerCase();
            const typeVal   = document.getElementById('scrapTypeSelect').value.toLowerCase();

            document.querySelectorAll('.card-wrapper[data-status]').forEach(card => {
                const text       = card.innerText.toLowerCase();
                const cardStatus = card.getAttribute('data-status').toLowerCase();
                const cardType   = card.getAttribute('data-type').toLowerCase();

                const matchesSearch = text.includes(searchVal);
                const matchesStatus = !statusVal || cardStatus.includes(statusVal);
                const matchesType   = !typeVal   || cardType.includes(typeVal);

                if (matchesSearch && matchesStatus && matchesType) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>