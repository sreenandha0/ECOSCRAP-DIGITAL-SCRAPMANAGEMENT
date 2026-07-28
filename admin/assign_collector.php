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

    <style>
        body {
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-main);
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
            left: 10%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.15) 0%, transparent 70%);
        }

        /* Full-Width Workspace Container */
        .workspace-container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
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

        /* Glass Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
            margin-bottom: 28px;
        }

        .card-header-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--surface-border);
        }

        .card-header-title i { color: var(--primary); font-size: 20px; }

        /* Request Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Collector Selection List */
        .collector-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .collector-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid var(--surface-border);
            border-radius: 14px;
            transition: var(--transition);
        }

        .collector-card:hover {
            border-color: var(--primary);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.08);
            transform: translateY(-2px);
        }

        .collector-meta h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .collector-info-pills {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 13px;
            color: var(--text-muted);
        }

        .collector-info-pills span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-assign {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: var(--primary);
            color: #ffffff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-assign:hover {
            background: var(--secondary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .empty-alert {
            text-align: center;
            padding: 36px 20px;
            color: var(--text-muted);
        }

        .empty-alert i {
            font-size: 36px;
            margin-bottom: 10px;
            display: block;
            color: #f59e0b;
        }

        @media (max-width: 768px) {
            .workspace-container { padding: 24px 16px; }
            .collector-card { flex-direction: column; align-items: flex-start; gap: 16px; }
            .btn-assign { width: 100%; justify-content: center; }
        }
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
