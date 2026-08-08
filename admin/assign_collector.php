<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != "Admin") {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php");
    exit();
}

$activity_id = intval($_GET['id']);

/*
|--------------------------------------------------------------------------
| Get Pickup Request Details
|--------------------------------------------------------------------------
*/
$request_sql = "
SELECT 
    activity.*,
    user.name AS user_name,
    user.phone AS user_phone,
    user.email AS user_email
FROM activity
INNER JOIN user ON activity.user_id = user.user_id
WHERE activity.activity_id = ?
";

$stmt = $conn->prepare($request_sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    header("Location: manage.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Get Available Approved Collectors Matching Pincode
|--------------------------------------------------------------------------
*/
$collector_sql = "
SELECT 
    *
FROM scrapcollector
WHERE verification_status = 'Approved'
  AND availability_status = 'Available'
  AND pincode = ?
";

$stmt2 = $conn->prepare($collector_sql);
$stmt2->bind_param("s", $request['pickup_pincode']);
$stmt2->execute();
$collectors = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Collector | EcoScrap</title>

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
    <link rel="stylesheet" href="../assets/css/assign_collector.css">
    <style>
       
    </style>
</head>

<body>

    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <!-- Workspace Container -->
    <main class="workspace-container">

        <!-- Page Header -->
        <header class="page-header">
            <div class="page-title">
                <h1>Assign Scrap Collector</h1>
                <p>Select an approved active collector available in pincode <strong><?= htmlspecialchars($request['pickup_pincode']) ?></strong></p>
            </div>
            <a href="manage.php" class="btn-back">
                <i class="ri-arrow-left-line"></i>
                <span>Back to Requests</span>
            </a>
        </header>

        <!-- Request Details Summary Card -->
        <div class="glass-card">
            <div class="card-header-title">
                <i class="ri-information-line"></i>
                <span>Pickup Request Summary #<?= htmlspecialchars($request['activity_id']) ?></span>
            </div>

            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Customer Name</span>
                    <span class="detail-value"><?= htmlspecialchars($request['user_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Contact Phone</span>
                    <span class="detail-value"><?= htmlspecialchars($request['user_phone']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Scrap Type</span>
                    <span class="detail-value" style="color: var(--primary);"><?= htmlspecialchars($request['scrap_type']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Est. Weight</span>
                    <span class="detail-value"><?= htmlspecialchars($request['scrap_weight']) ?> KG</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Pincode</span>
                    <span class="detail-value"><?= htmlspecialchars($request['pickup_pincode']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Preferred Date</span>
                    <span class="detail-value"><?= htmlspecialchars($request['preferred_pickup_date']) ?></span>
                </div>
            </div>
        </div>

        <!-- Available Collectors Listing -->
        <div class="glass-card">
            <div class="card-header-title">
                <i class="ri-user-follow-line"></i>
                <span>Available Collectors (Pincode <?= htmlspecialchars($request['pickup_pincode']) ?>)</span>
            </div>

            <div class="collector-list">
                <?php if ($collectors && $collectors->num_rows > 0): ?>
                    <?php while ($collector = $collectors->fetch_assoc()): ?>
                        <div class="collector-card">
                            <div class="collector-meta">
                                <h4>
                                    <i class="ri-user-3-line" style="color: var(--primary);"></i>
                                    <?= htmlspecialchars($collector['name']) ?>
                                </h4>
                                <div class="collector-info-pills">
                                    <span><i class="ri-phone-line"></i> <?= htmlspecialchars($collector['phone']) ?></span>
                                    <span><i class="ri-truck-line"></i> Vehicle: <?= htmlspecialchars($collector['vehicle_no'] ?? 'N/A') ?></span>
                                    <span><i class="ri-map-pin-line"></i> Pincode: <?= htmlspecialchars($collector['pincode']) ?></span>
                                </div>
                            </div>

                            <form action="assign_collector_process.php" method="post" onsubmit="return confirm('Assign <?= htmlspecialchars($collector['name'], ENT_QUOTES) ?> to this pickup request?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                <input type="hidden" name="activity_id" value="<?= (int) $activity_id ?>">
                                <input type="hidden" name="collector_id" value="<?= (int) $collector['collector_id'] ?>">
                                <button class="btn-assign" type="submit"><i class="ri-user-add-line"></i> Assign Now</button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-alert">
                        <i class="ri-alert-line"></i>
                        <h5>No Available Collectors</h5>
                        <p class="mb-0">There are currently no approved or available collectors registered under pincode <strong><?= htmlspecialchars($request['pickup_pincode']) ?></strong>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
