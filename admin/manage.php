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
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/manage.css">
    <style>
       
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