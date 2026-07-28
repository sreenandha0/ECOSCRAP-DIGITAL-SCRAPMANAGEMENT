<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (
    !isset($_SESSION['collector_id']) ||
    $_SESSION['role'] != "Collector"
) {
    redirect("../login.php");
}

$collector_id = $_SESSION['collector_id'];

// Get assigned pickup requests
$sql = "
SELECT
    activity.*,
    user.name AS customer_name,
    user.phone AS customer_phone
FROM activity
INNER JOIN user ON activity.user_id = user.user_id
WHERE activity.collector_id = ?
  AND activity.status = 'Assigned'
ORDER BY activity.request_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $collector_id);
$stmt->execute();
$requests = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Pickups | EcoScrap</title>

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

        /* Glass Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--transition);
        }

        .glass-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.06);
            border-color: var(--primary);
        }

        .card-header-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--surface-border);
        }

        .card-header-title span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .card-header-title i {
            color: var(--primary);
            font-size: 20px;
        }

        .info-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
        }

        .info-row i {
            font-size: 18px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .info-label {
            color: var(--text-muted);
            font-weight: 500;
            min-width: 85px;
        }

        .info-value {
            color: var(--text-main);
            font-weight: 600;
        }

        .badge-status {
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }

        .btn-accept {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: #ffffff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-accept:hover {
            background: var(--secondary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .empty-alert {
            text-align: center;
            padding: 48px 20px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            color: var(--text-muted);
        }

        .empty-alert i {
            font-size: 42px;
            margin-bottom: 12px;
            display: block;
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .workspace-container {
                padding: 24px 16px;
            }
        }
    </style>
</head>

<body>

    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <main class="workspace-container">

        <!-- Page Header -->
        <header class="page-header">
            <div class="page-title">
                <h1>Assigned Pickup Requests</h1>
                <p>Manage and accept pickup jobs assigned to your queue</p>
            </div>
        </header>

        <!-- Requests Grid -->
        <div class="row g-4">
            <?php if ($requests && $requests->num_rows > 0): ?>
                <?php while ($row = $requests->fetch_assoc()): ?>
                    <div class="col-md-6">
                        <div class="glass-card">
                            <div>
                                <div class="card-header-title">
                                    <span>
                                        <i class="ri-recycle-line"></i>
                                        <?= htmlspecialchars($row['scrap_type']) ?>
                                    </span>
                                    <span class="badge-status">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </div>

                                <div class="info-group">
                                    <div class="info-row">
                                        <i class="ri-user-3-line"></i>
                                        <span class="info-label">Customer:</span>
                                        <span class="info-value"><?= htmlspecialchars($row['customer_name']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="ri-phone-line"></i>
                                        <span class="info-label">Phone:</span>
                                        <span class="info-value"><?= htmlspecialchars($row['customer_phone']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="ri-map-pin-line"></i>
                                        <span class="info-label">Address:</span>
                                        <span class="info-value"><?= htmlspecialchars($row['pickup_address']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="ri-scales-3-line"></i>
                                        <span class="info-label">Weight:</span>
                                        <span class="info-value"><?= htmlspecialchars($row['scrap_weight']) ?> KG</span>
                                    </div>
                                    <div class="info-row">
                                        <i class="ri-calendar-event-line"></i>
                                        <span class="info-label">Pickup Date:</span>
                                        <span class="info-value"><?= htmlspecialchars($row['preferred_pickup_date']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <form action="accept_pickup.php" method="post" class="d-inline" onsubmit="return confirm('Start this pickup request?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['activity_id'] ?>">
                                <button type="submit" class="btn-accept"><i class="ri-play-line"></i> Start Pickup</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-alert">
                        <i class="ri-inbox-line"></i>
                        <h5>No Assigned Pickups</h5>
                        <p class="mb-0">You currently have no pending pickup assignments in your queue.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
