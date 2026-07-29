<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Admin") {
    header("Location: ../login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Dashboard Counters Setup
|--------------------------------------------------------------------------
*/
$counts = [
    'Pending'     => 0,
    'Approved'    => 0,
    'Assigned'    => 0,
    'Accepted'    => 0,
    'In Progress' => 0,
    'Verified'    => 0,
    'Completed'   => 0,
    'Rejected'    => 0
];

$count_sql = "SELECT status, COUNT(*) AS count FROM activity GROUP BY status";
$count_res = $conn->query($count_sql);

if ($count_res) {
    while ($c_row = $count_res->fetch_assoc()) {
        if (array_key_exists($c_row['status'], $counts)) {
            $counts[$c_row['status']] = $c_row['count'];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Search & Filter Parameters
|--------------------------------------------------------------------------
*/
$search = trim($_GET['search'] ?? "");
$status = trim($_GET['status'] ?? "");

/*
|--------------------------------------------------------------------------
| Dynamic Query Setup
|--------------------------------------------------------------------------
*/
$sql = "
SELECT 
    activity.*,
    user.name AS user_name,
    user.phone AS user_phone,
    user.address AS user_address,
    scrapcollector.name AS collector_name,
    scrapcollector.phone AS collector_phone,
    scrapcollector.vehicle_no
FROM activity
INNER JOIN user ON activity.user_id = user.user_id
LEFT JOIN scrapcollector ON activity.collector_id = scrapcollector.collector_id
WHERE 1=1
";

$params = [];
$types  = "";

if (!empty($search)) {
    $sql .= " AND (
        user.name LIKE ? 
        OR activity.scrap_type LIKE ? 
        OR activity.pickup_pincode LIKE ? 
        OR scrapcollector.name LIKE ?
    )";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types   .= "ssss";
}

if (!empty($status)) {
    $sql .= " AND activity.status = ?";
    $params[] = $status;
    $types   .= "s";
}

$sql .= " ORDER BY activity.request_date DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$requests = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pickup Requests | EcoScrap</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Design System CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            position: relative;
            overflow-x: hidden;
        }

        .ambient-blur {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.35;
        }

        .blur-1 {
            width: 600px;
            height: 600px;
            top: -10%;
            right: 0%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, transparent 70%);
        }

        .blur-2 {
            width: 500px;
            height: 500px;
            bottom: -5%;
            left: 200px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%);
        }

        .workspace-container {
            margin-left: 260px;
            flex: 1;
            padding: 40px;
            max-width: 1400px;
            position: relative;
            z-index: 1;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .page-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-main);
            border: 1px solid var(--surface-border);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-back:hover {
            background: rgba(15, 23, 42, 0.04);
            border-color: var(--text-muted);
        }

        .counters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .counter-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.02);
        }

        .counter-info span {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .counter-info h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            margin: 4px 0 0 0;
        }

        .counter-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-pending { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .icon-approved { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        .icon-assigned { background: rgba(14, 165, 233, 0.15); color: #0284c7; }
        .icon-completed { background: rgba(16, 185, 129, 0.15); color: #059669; }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
            margin-bottom: 28px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 12px;
            align-items: center;
        }

        .form-control-eco, .form-select-eco {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--surface-border);
            border-radius: 10px;
            font-size: 14px;
            color: var(--text-main);
            outline: none;
            transition: var(--transition);
        }

        .form-control-eco:focus, .form-select-eco:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .btn-search {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 20px;
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-search:hover {
            background: var(--secondary);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 18px;
            background: rgba(15, 23, 42, 0.05);
            color: var(--text-muted);
            border: 1px solid var(--surface-border);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-reset:hover {
            background: rgba(15, 23, 42, 0.08);
            color: var(--text-main);
        }

        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
            gap: 20px;
        }

        .request-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .request-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--surface-border);
        }

        .scrap-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .scrap-title i { color: var(--primary); }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-approved { background: rgba(59, 130, 246, 0.1); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.2); }
        .badge-assigned { background: rgba(14, 165, 233, 0.1); color: #0284c7; border: 1px solid rgba(14, 165, 233, 0.2); }
        .badge-accepted { background: rgba(99, 102, 241, 0.1); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.2); }
        .badge-completed { background: rgba(16, 185, 129, 0.1); color: var(--secondary); border: 1px solid rgba(16, 185, 129, 0.2); }
        .badge-rejected { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.2); }
        .badge-default { background: rgba(15, 23, 42, 0.08); color: var(--text-main); border: 1px solid var(--surface-border); }

        .detail-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 16px;
            margin-bottom: 20px;
        }

        .detail-item { font-size: 13px; }
        .detail-label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 500;
            display: block;
            margin-bottom: 2px;
        }
        .detail-value {
            color: var(--text-main);
            font-weight: 600;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            padding-top: 14px;
            border-top: 1px solid var(--surface-border);
            flex-wrap: wrap;
        }

        .btn-act {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-act-view { background: rgba(15, 23, 42, 0.04); color: var(--text-main); border-color: var(--surface-border); }
        .btn-act-view:hover { background: rgba(15, 23, 42, 0.08); }

        .btn-act-approve { background: rgba(16, 185, 129, 0.1); color: var(--secondary); border-color: rgba(16, 185, 129, 0.25); }
        .btn-act-approve:hover { background: var(--primary); color: #fff; }

        .btn-act-reject { background: rgba(239, 68, 68, 0.1); color: #dc2626; border-color: rgba(239, 68, 68, 0.25); }
        .btn-act-reject:hover { background: #dc2626; color: #fff; }

        .btn-act-assign { background: rgba(14, 165, 233, 0.1); color: #0284c7; border-color: rgba(14, 165, 233, 0.25); }
        .btn-act-assign:hover { background: #0284c7; color: #fff; }

        .btn-act-disabled { background: rgba(15, 23, 42, 0.03); color: var(--text-muted); border-color: var(--surface-border); cursor: not-allowed; }

        .btn-act-qr { background: rgba(15, 23, 42, 0.9); color: #ffffff; border-color: rgba(15, 23, 42, 0.9); }
        .btn-act-qr:hover { background: #000000; }

        .empty-alert {
            grid-column: 1 / -1;
            padding: 32px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            text-align: center;
            color: var(--text-muted);
            font-weight: 500;
        }

        @media (max-width: 992px) {
            .filter-grid { grid-template-columns: 1fr; }
            .requests-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .workspace-container { margin-left: 0; padding: 24px 16px; }
        }
    </style>
</head>

<body>

    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <main class="workspace-container">

        <!-- Header -->
        <header class="page-header">
            <div class="page-title">
                <h1>Manage Pickup Requests</h1>
                <p>Filter, assign collectors, and monitor scrap recycling activity in real time.</p>
            </div>
            <a href="dashboard.php" class="btn-back">
                <i class="ri-arrow-left-line"></i>
                <span>Dashboard</span>
            </a>
        </header>

        <!-- Feedback Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="ri-checkbox-circle-line me-1"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="ri-error-warning-line me-1"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Dashboard Counters -->
        <div class="counters-grid">
            <div class="counter-card">
                <div class="counter-info">
                    <span>Pending</span>
                    <h2><?= $counts['Pending'] ?></h2>
                </div>
                <div class="counter-icon icon-pending"><i class="ri-time-line"></i></div>
            </div>
            <div class="counter-card">
                <div class="counter-info">
                    <span>Approved</span>
                    <h2><?= $counts['Approved'] ?></h2>
                </div>
                <div class="counter-icon icon-approved"><i class="ri-checkbox-circle-line"></i></div>
            </div>
            <div class="counter-card">
                <div class="counter-info">
                    <span>Assigned</span>
                    <h2><?= $counts['Assigned'] ?></h2>
                </div>
                <div class="counter-icon icon-assigned"><i class="ri-user-follow-line"></i></div>
            </div>
            <div class="counter-card">
                <div class="counter-info">
                    <span>Completed</span>
                    <h2><?= $counts['Completed'] ?></h2>
                </div>
                <div class="counter-icon icon-completed"><i class="ri-check-double-line"></i></div>
            </div>
        </div>

        <!-- Filter Box -->
        <div class="glass-card">
            <form method="GET" class="filter-grid">
                <div>
                    <input
                        type="text"
                        name="search"
                        class="form-control-eco"
                        placeholder="Search by user, scrap type, or pincode..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>

                <div>
                    <select name="status" class="form-select-eco">
                        <option value="">All Statuses</option>
                        <?php
                        $statuses = ["Pending", "Approved", "Assigned", "Accepted", "Completed", "Rejected"];
                        foreach ($statuses as $s) {
                            $selected = ($status === $s) ? "selected" : "";
                            echo "<option value=\"$s\" $selected>$s</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn-search">
                        <i class="ri-search-line"></i> Search
                    </button>
                </div>

                <div>
                    <a href="manage.php" class="btn-reset">
                        <i class="ri-refresh-line"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Requests Grid -->
        <div class="requests-grid">
            <?php
            if ($requests && $requests->num_rows > 0):
                while ($row = $requests->fetch_assoc()):

                    switch ($row['status']) {
                        case "Pending":
                            $badgeClass = "badge-pending";
                            $icon = "ri-time-line";
                            break;
                        case "Approved":
                            $badgeClass = "badge-approved";
                            $icon = "ri-checkbox-circle-line";
                            break;
                        case "Assigned":
                            $badgeClass = "badge-assigned";
                            $icon = "ri-user-follow-line";
                            break;
                        case "Accepted":
                            $badgeClass = "badge-accepted";
                            $icon = "ri-user-star-line";
                            break;
                        case "Completed":
                            $badgeClass = "badge-completed";
                            $icon = "ri-check-double-line";
                            break;
                        case "Rejected":
                            $badgeClass = "badge-rejected";
                            $icon = "ri-close-circle-line";
                            break;
                        default:
                            $badgeClass = "badge-default";
                            $icon = "ri-information-line";
                    }
            ?>

                    <div class="request-card">
                        <div>
                            <div class="card-top">
                                <span class="scrap-title">
                                    <i class="ri-recycle-line"></i>
                                    <?= htmlspecialchars($row['scrap_type'] ?? '') ?>
                                </span>
                                <span class="badge <?= $badgeClass ?>">
                                    <i class="<?= $icon ?>"></i> <?= htmlspecialchars($row['status'] ?? '') ?>
                                </span>
                            </div>

                            <div class="detail-list">
                                <div class="detail-item">
                                    <span class="detail-label">User Name</span>
                                    <span class="detail-value"><?= htmlspecialchars($row['user_name'] ?? '') ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value"><?= htmlspecialchars($row['user_phone'] ?? '') ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Estimated Weight</span>
                                    <span class="detail-value"><?= htmlspecialchars($row['scrap_weight'] ?? '') ?> KG</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Pickup Pincode</span>
                                    <span class="detail-value"><?= htmlspecialchars($row['pickup_pincode'] ?? '') ?></span>
                                </div>
                                
                                <?php if (!empty($row['collector_name'])): ?>
                                    <div class="detail-item" style="grid-column: span 2;">
                                        <span class="detail-label">Collector</span>
                                        <span class="detail-value" style="color: var(--primary);"><i class="ri-user-received-line"></i> <?= htmlspecialchars($row['collector_name']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="detail-item">
                                    <span class="detail-label">Preferred Date</span>
                                    <span class="detail-value"><?= htmlspecialchars($row['preferred_pickup_date'] ?? '') ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Requested On</span>
                                    <span class="detail-value"><?= !empty($row['request_date']) ? date("d M Y, h:i A", strtotime($row['request_date'])) : 'N/A' ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Status Action Controls -->
                        <div class="card-actions">
                            <button type="button" 
                                    class="btn-act btn-act-view" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#viewRequestModal"
                                    data-user="<?= htmlspecialchars($row['user_name'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($row['user_phone'] ?? '') ?>"
                                    data-address="<?= htmlspecialchars($row['user_address'] ?? 'N/A') ?>"
                                    data-scraptype="<?= htmlspecialchars($row['scrap_type'] ?? '') ?>"
                                    data-weight="<?= htmlspecialchars($row['scrap_weight'] ?? '') ?> KG"
                                    data-pickupdate="<?= htmlspecialchars($row['preferred_pickup_date'] ?? '') ?>"
                                    data-collector="<?= htmlspecialchars($row['collector_name'] ?? 'Not Assigned') ?>"
                                    data-status="<?= htmlspecialchars($row['status'] ?? '') ?>">
                                <i class="ri-eye-line"></i> View
                            </button>

                            <?php if ($row['status'] == "Pending"): ?>
                                <a href="approve_request.php?id=<?= $row['activity_id'] ?>" class="btn-act btn-act-approve">
                                    <i class="ri-check-line"></i> Approve
                                </a>
                                <a href="reject_request.php?id=<?= $row['activity_id'] ?>" class="btn-act btn-act-reject" onclick="return confirm('Are you sure you want to reject this request?');">
                                    <i class="ri-close-line"></i> Reject
                                </a>

                            <?php elseif ($row['status'] == "Approved"): ?>
                                <a href="assign_collector.php?id=<?= $row['activity_id'] ?>" class="btn-act btn-act-assign">
                                    <i class="ri-user-add-line"></i> Assign Collector
                                </a>

                            <?php elseif ($row['status'] == "Assigned"): ?>
                                <?php if (!empty($row['qr_code'])): ?>
                                    <a href="generate_qr.php?id=<?= $row['activity_id'] ?>" class="btn-act btn-act-qr">
                                        <i class="ri-qr-code-fill"></i> View QR
                                    </a>
                                <?php else: ?>
                                    <button class="btn-act btn-act-disabled" disabled>
                                        <i class="ri-error-warning-line"></i> QR Missing
                                    </button>
                                <?php endif; ?>

                            <?php elseif ($row['status'] == "Completed"): ?>
                                <button class="btn-act btn-act-disabled" disabled>
                                    <i class="ri-checkbox-circle-fill" style="color: var(--secondary);"></i> Completed
                                </button>

                            <?php elseif ($row['status'] == "Rejected"): ?>
                                <button class="btn-act btn-act-disabled" disabled>
                                    <i class="ri-close-circle-fill" style="color: #dc2626;"></i> Rejected
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php
                endwhile;
            else:
                ?>
                <div class="empty-alert">
                    No pickup requests found matching your filters.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- JS for Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>