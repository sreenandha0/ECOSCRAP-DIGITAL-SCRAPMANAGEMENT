<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== "Admin") {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage.php");
    exit();
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$activity_id = (int) $_GET['id'];

$request_sql = "
    SELECT
        activity.*,
        user.name AS user_name,
        user.phone AS user_phone,
        user.email AS user_email
    FROM activity
    INNER JOIN user
        ON activity.user_id = user.user_id
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

if ($request['status'] !== "Approved" && $request['status'] !== "Rejected") {
    $_SESSION['error'] = "This pickup request is not currently available for assignment.";
    header("Location: manage.php");
    exit();
}

$collector_sql = "
    SELECT *
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
    <title><?= $request['status'] === 'Rejected' ? 'Reassign Scrap Collector' : 'Assign Scrap Collector' ?> | EcoScrap</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        :root {
            --eco-primary: #10b981;
            --eco-primary-hover: #059669;
            --eco-primary-light: rgba(16, 185, 129, 0.12);
            --eco-secondary: #0ea5e9;
            --eco-dark: #0f172a;
            --eco-card-bg: rgba(255, 255, 255, 0.88);
            --eco-card-border: rgba(226, 232, 240, 0.8);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --shadow: 0 12px 32px -4px rgba(15, 23, 42, 0.06);
            --shadow-hover: 0 20px 40px -4px rgba(16, 185, 129, 0.12);
        }

        body {
            min-height: 100vh;
            background: #f8fafc;
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            color: var(--text-primary);
            overflow-x: hidden;
        }

        .ambient-blur {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
        }

        .blur-1 {
            width: 500px;
            height: 500px;
            top: -120px;
            right: -120px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.24) 0%, transparent 70%);
        }

        .blur-2 {
            width: 450px;
            height: 450px;
            bottom: -70px;
            left: -120px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.20) 0%, transparent 70%);
        }

        .app-navbar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--eco-dark);
        }

        .brand-logo img {
            height: 36px;
            width: auto;
            object-fit: contain;
            border-radius: 6px;
        }

        .brand-badge {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 30px;
            background: var(--eco-primary-light);
            color: var(--eco-primary);
        }

        .workspace-container {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 20px;
            position: relative;
            z-index: 1;
        }

        .page-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 24px;
            padding: 32px 28px;
            color: #fff;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.2);
        }

        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 800;
        }

        .page-header p {
            margin: 8px 0 0 0;
            color: #94a3b8;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 12px;
            text-decoration: none;
            background: rgba(255,255,255,0.08);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.12);
            font-weight: 600;
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.14);
            color: #fff;
        }

        .glass-card {
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--eco-card-border);
            border-radius: 22px;
            padding: 24px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .card-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            margin-bottom: 18px;
            color: var(--text-primary);
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .detail-item {
            background: rgba(255,255,255,0.78);
            border: 1px solid var(--eco-card-border);
            border-radius: 16px;
            padding: 16px;
        }

        .detail-label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 6px;
        }

        .detail-value {
            font-weight: 700;
            color: var(--text-primary);
        }

        .collector-list {
            display: grid;
            gap: 14px;
        }

        .collector-card {
            background: rgba(255,255,255,0.78);
            border: 1px solid var(--eco-card-border);
            border-radius: 18px;
            padding: 18px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .collector-meta h4 {
            margin: 0 0 8px 0;
            font-size: 1.05rem;
            font-weight: 800;
        }

        .collector-info-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .collector-info-pills span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.08);
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .btn-assign {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, var(--eco-primary) 0%, #059669 100%);
            color: #fff;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-assign:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .empty-alert {
            text-align: center;
            padding: 56px 24px;
            background: rgba(255,255,255,0.82);
            border: 1px dashed #cbd5e1;
            border-radius: 22px;
        }

        @media (max-width: 992px) {
            .details-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .collector-card {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 768px) {
            .workspace-container {
                padding: 16px 12px;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 24px 20px;
                border-radius: 20px;
            }
            .page-header h1 {
                font-size: 1.45rem;
            }
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <nav class="app-navbar py-3">
        <div class="container-fluid max-width-1140 px-4 d-flex align-items-center justify-content-between">
            <a href="dashboard.php" class="brand-logo">
                <img src="../assets/logo/ecoscrap-logo.png" alt="EcoScrap Logo">
                <span>EcoScrap</span>
                <span class="brand-badge">Admin Hub</span>
            </a>
        </div>
    </nav>

    <main class="workspace-container">
        <header class="page-header">
            <div class="page-title">
                <h1><?= $request['status'] === 'Rejected' ? 'Reassign Scrap Collector' : 'Assign Scrap Collector' ?></h1>
                <p>
                    <?= $request['status'] === 'Rejected' ? 'Select another approved and available scrap collector' : 'Select an approved and available scrap collector' ?>
                    in pincode <strong><?= e($request['pickup_pincode']) ?></strong>
                </p>
            </div>
            <a href="manage.php" class="btn-back">
                <i class="ri-arrow-left-line"></i>
                <span>Back to Requests</span>
            </a>
        </header>

        <?php if ($request['status'] === 'Rejected') : ?>
            <div class="alert alert-warning" role="alert">
                <i class="ri-refresh-line"></i>
                <strong>Reassignment Required</strong><br>
                The previously assigned scrap collector rejected this pickup request. Please select another available scrap collector.
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <div class="card-header-title">
                <i class="ri-information-line"></i>
                <span>Pickup Request Summary #<?= e($request['activity_id']) ?></span>
            </div>

            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Customer Name</span>
                    <span class="detail-value"><?= e($request['user_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Contact Phone</span>
                    <span class="detail-value"><?= e($request['user_phone']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Scrap Type</span>
                    <span class="detail-value" style="color: var(--eco-primary);"><?= e($request['scrap_type']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Est. Weight</span>
                    <span class="detail-value"><?= e($request['scrap_weight']) ?> KG</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Pincode</span>
                    <span class="detail-value"><?= e($request['pickup_pincode']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Preferred Date</span>
                    <span class="detail-value"><?= e($request['preferred_pickup_date']) ?></span>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="card-header-title">
                <i class="ri-user-follow-line"></i>
                <span>Available Scrap Collectors (Pincode <?= e($request['pickup_pincode']) ?>)</span>
            </div>

            <div class="collector-list">
                <?php if ($collectors && $collectors->num_rows > 0) : ?>
                    <?php while ($collector = $collectors->fetch_assoc()) : ?>
                        <div class="collector-card">
                            <div class="collector-meta">
                                <h4>
                                    <i class="ri-user-3-line" style="color: var(--eco-primary);"></i>
                                    <?= e($collector['name']) ?>
                                </h4>
                                <div class="collector-info-pills">
                                    <span><i class="ri-phone-line"></i> <?= e($collector['phone']) ?></span>
                                    <span><i class="ri-truck-line"></i> Vehicle: <?= e($collector['vehicle_no'] ?? 'N/A') ?></span>
                                    <span><i class="ri-map-pin-line"></i> Pincode: <?= e($collector['pincode']) ?></span>
                                </div>
                            </div>

                            <form action="assign_collector_process.php" method="post" onsubmit="return confirm('<?= $request['status'] === 'Rejected' ? 'Reassign' : 'Assign' ?> <?= e($collector['name']) ?> to this pickup request?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="activity_id" value="<?= (int)$activity_id ?>">
                                <input type="hidden" name="collector_id" value="<?= (int)$collector['collector_id'] ?>">
                                <button class="btn-assign" type="submit">
                                    <i class="ri-user-add-line"></i>
                                    <?= $request['status'] === 'Rejected' ? 'Reassign Now' : 'Assign Now' ?>
                                </button>
                            </form>
                        </div>
                    <?php endwhile; ?>
                <?php else : ?>
                    <div class="empty-alert">
                        <i class="ri-alert-line fs-1 text-warning"></i>
                        <h5 class="mt-3 fw-bold">No Available Scrap Collectors</h5>
                        <p class="mb-0">
                            No approved and available scrap collector is currently available for pincode
                            <strong><?= e($request['pickup_pincode']) ?></strong>.
                            The request can be assigned or reassigned when a suitable scrap collector becomes available.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>