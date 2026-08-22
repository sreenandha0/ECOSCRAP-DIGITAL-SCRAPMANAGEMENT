<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['collector_id']) || ($_SESSION['role'] ?? '') !== "Collector") {
    redirect("../login.php");
}

$collector_id = (int) $_SESSION['collector_id'];

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
$result = $stmt->get_result();

$requests = [];
$total_weight = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
        $total_weight += (float) ($row['scrap_weight'] ?? 0);
    }
}
$total_count = count($requests);

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assigned Pickups | EcoScrap Collector Hub</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --eco-shadow: 0 12px 32px -4px rgba(15, 23, 42, 0.05);
            --eco-shadow-hover: 0 20px 40px -4px rgba(16, 185, 129, 0.12);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
        }

        body {
            min-height: 100vh;
            background-color: #f8fafc;
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            color: var(--text-primary);
            position: relative;
            overflow-x: hidden;
            padding-bottom: 90px;
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
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.25) 0%, transparent 70%);
        }

        .blur-2 {
            width: 450px;
            height: 450px;
            bottom: -50px;
            left: -100px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, transparent 70%);
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

        .brand-logo-img {
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
            max-width: 1140px;
            margin: 0 auto;
            padding: 28px 20px;
            position: relative;
            z-index: 1;
        }

        .header-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 24px;
            padding: 32px 28px;
            color: #ffffff;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.2);
        }

        .header-banner::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background: radial-gradient(circle at 100% 0%, rgba(16, 185, 129, 0.3) 0%, transparent 70%);
            pointer-events: none;
        }

        .header-title {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .header-subtitle {
            font-size: 0.95rem;
            color: #94a3b8;
            margin: 0;
        }

        .stat-pill {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon-wrapper {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .stat-pill-green .stat-icon-wrapper {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .stat-pill-blue .stat-icon-wrapper {
            background: rgba(14, 165, 233, 0.2);
            color: #38bdf8;
        }

        .stat-val {
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.2;
            color: #ffffff;
        }

        .stat-lbl {
            font-size: 0.78rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .filter-card {
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--eco-card-border);
            border-radius: 18px;
            padding: 16px 20px;
            margin-bottom: 28px;
            box-shadow: var(--eco-shadow);
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .search-input {
            width: 100%;
            padding: 11px 16px 11px 44px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            font-size: 0.92rem;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--eco-primary);
            box-shadow: 0 0 0 4px var(--eco-primary-light);
        }

        .category-pill-btn {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: var(--text-secondary);
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.83rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .category-pill-btn:hover,
        .category-pill-btn.active {
            background: var(--eco-dark);
            color: #ffffff;
            border-color: var(--eco-dark);
        }

        .pickup-card {
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--eco-card-border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--eco-shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .pickup-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--eco-shadow-hover);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .scrap-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            background: #f1f5f9;
            color: var(--text-primary);
        }

        .badge-assigned {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 30px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.25);
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.9rem;
            margin-bottom: 12px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-icon {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            color: var(--eco-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .info-content {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.92rem;
            margin-top: 1px;
        }

        .weight-chip {
            display: inline-block;
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .quick-actions {
            display: flex;
            gap: 8px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px dashed #e2e8f0;
        }

        .btn-action-icon {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: var(--text-secondary);
        }

        .btn-action-icon:hover {
            background: #f1f5f9;
            color: var(--eco-dark);
            border-color: #cbd5e1;
        }

        .btn-start-pickup {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--eco-primary) 0%, #059669 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            margin-top: 12px;
        }

        .btn-start-pickup:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.35);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
        }

        .btn-reject-pickup {
            width: 100%;
            border: 1px solid #dc3545;
            background: transparent;
            color: #dc3545;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-reject-pickup:hover {
            background: #dc3545;
            color: #fff;
        }

        .empty-alert {
            text-align: center;
            padding: 60px 24px;
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            border: 1px dashed #cbd5e1;
            border-radius: 24px;
            color: var(--text-secondary);
        }

        .empty-icon-circle {
            width: 80px;
            height: 80px;
            background: var(--eco-primary-light);
            color: var(--eco-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 20px auto;
        }

        .custom-modal-content {
            border-radius: 24px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .custom-modal-header {
            background: var(--eco-dark);
            color: #ffffff;
            padding: 20px 24px;
            border: none;
        }

        .custom-modal-body {
            padding: 24px;
        }

        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-top: 1px solid #e2e8f0;
            padding: 10px 16px;
            z-index: 999;
            display: flex;
            justify-content: space-around;
            align-items: center;
        }

        .nav-item-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .nav-item-btn i {
            font-size: 1.3rem;
        }

        .nav-item-btn.active {
            color: var(--eco-primary);
        }

        @media (max-width: 768px) {
            .workspace-container {
                padding: 16px 12px;
            }

            .header-banner {
                padding: 24px 20px;
                border-radius: 20px;
            }

            .header-title {
                font-size: 1.4rem;
            }

            .stat-pill {
                padding: 10px 14px;
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
                <img src="../assets/logo/ecoscrap-logo.png" alt="EcoScrap Logo" class="brand-logo-img">
                <span>EcoScrap</span>
                <span class="brand-badge">Collector Hub</span>
            </a>

            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-emerald-light text-emerald px-3 py-2 rounded-pill d-none d-sm-inline-flex align-items-center gap-2" style="background: rgba(16, 185, 129, 0.1); color: #059669; font-weight: 600;">
                    <span class="spinner-grow spinner-grow-sm text-success" role="status" style="width: 8px; height: 8px;"></span>
                    Queue Active
                </span>
                <a href="../logout.php" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" title="Logout">
                    <i class="ri-logout-box-r-line"></i>
                </a>
            </div>
        </div>
    </nav>

    <main class="workspace-container">
        <header class="header-banner">
            <div class="row align-items-center g-3">
                <div class="col-lg-7">
                    <h1 class="header-title">Assigned Pickup Queue</h1>
                    <p class="header-subtitle">Review customer locations, estimated scrap weights, and initiate pickups seamlessly.</p>
                </div>
                <div class="col-lg-5">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="stat-pill stat-pill-green">
                                <div class="stat-icon-wrapper"><i class="ri-truck-line"></i></div>
                                <div>
                                    <div class="stat-val"><?= (int) $total_count ?></div>
                                    <div class="stat-lbl">Jobs Assigned</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-pill stat-pill-blue">
                                <div class="stat-icon-wrapper"><i class="ri-scales-3-line"></i></div>
                                <div>
                                    <div class="stat-val"><?= number_format($total_weight, 1) ?> <span style="font-size: 0.8rem;">KG</span></div>
                                    <div class="stat-lbl">Total Weight</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="filter-card">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="ri-search-2-line"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Search by customer name, phone, or address..." onkeyup="filterCards()">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 overflow-x-auto pb-1" id="categoryFilters">
                        <button type="button" class="category-pill-btn active" onclick="setCategoryFilter('all', this)">All Types</button>
                        <button type="button" class="category-pill-btn" onclick="setCategoryFilter('Paper', this)">Paper</button>
                        <button type="button" class="category-pill-btn" onclick="setCategoryFilter('Plastic', this)">Plastic</button>
                        <button type="button" class="category-pill-btn" onclick="setCategoryFilter('Metal', this)">Metal</button>
                        <button type="button" class="category-pill-btn" onclick="setCategoryFilter('E-Waste', this)">E-Waste</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4" id="requestsGrid">
            <?php if (!empty($requests)): ?>
                <?php foreach ($requests as $row):
                    $scrapType = (string) ($row['scrap_type'] ?? 'General Scrap');
                    $scrapIcon = 'ri-recycle-line';
                    if (stripos($scrapType, 'paper') !== false) $scrapIcon = 'ri-newspaper-line';
                    elseif (stripos($scrapType, 'plastic') !== false) $scrapIcon = 'ri-cup-line';
                    elseif (stripos($scrapType, 'metal') !== false) $scrapIcon = 'ri-hammer-line';
                    elseif (stripos($scrapType, 'electronic') !== false || stripos($scrapType, 'e-waste') !== false) $scrapIcon = 'ri-computer-line';
                    elseif (stripos($scrapType, 'glass') !== false) $scrapIcon = 'ri-goblet-line';

                    $custName = (string) ($row['customer_name'] ?? 'N/A');
                    $custPhone = (string) ($row['customer_phone'] ?? '');
                    $address = (string) ($row['pickup_address'] ?? 'N/A');
                    $weight = (string) ($row['scrap_weight'] ?? '0');
                    $pickupDate = (string) ($row['preferred_pickup_date'] ?? 'Asap');
                    $activityId = (int) ($row['activity_id'] ?? 0);
                    $searchData = strtolower($custName . ' ' . $custPhone . ' ' . $address . ' ' . $scrapType);
                ?>
                    <div class="col-md-6 col-lg-6 request-item" data-search="<?= e($searchData) ?>" data-category="<?= e($scrapType) ?>">
                        <div class="pickup-card">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                                    <span class="scrap-badge">
                                        <i class="<?= e($scrapIcon) ?> text-success"></i>
                                        <?= e($scrapType) ?>
                                    </span>
                                    <span class="badge-assigned">
                                        <i class="ri-time-line me-1"></i><?= e($row['status'] ?? '') ?>
                                    </span>
                                </div>

                                <div class="info-row">
                                    <div class="info-icon"><i class="ri-user-3-line"></i></div>
                                    <div class="info-content">
                                        <span class="info-label">Customer Name</span>
                                        <span class="info-value"><?= e($custName) ?></span>
                                    </div>
                                </div>

                                <div class="info-row">
                                    <div class="info-icon"><i class="ri-phone-line"></i></div>
                                    <div class="info-content">
                                        <span class="info-label">Phone Contact</span>
                                        <span class="info-value"><?= $custPhone !== '' ? e($custPhone) : 'No phone provided' ?></span>
                                    </div>
                                </div>

                                <div class="info-row">
                                    <div class="info-icon"><i class="ri-map-pin-2-line"></i></div>
                                    <div class="info-content">
                                        <span class="info-label">Pickup Location</span>
                                        <span class="info-value"><?= e($address) ?></span>
                                    </div>
                                </div>

                                <div class="row mt-3 pt-2 g-2">
                                    <div class="col-6">
                                        <div class="info-row">
                                            <div class="info-icon"><i class="ri-scales-3-line"></i></div>
                                            <div class="info-content">
                                                <span class="info-label">Est. Weight</span>
                                                <span class="info-value"><span class="weight-chip"><?= e($weight) ?> KG</span></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-row">
                                            <div class="info-icon"><i class="ri-calendar-event-line"></i></div>
                                            <div class="info-content">
                                                <span class="info-label">Preferred Date</span>
                                                <span class="info-value"><?= e($pickupDate) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="quick-actions">
                                    <?php if ($custPhone !== ''): ?>
                                        <a href="tel:<?= rawurlencode($custPhone) ?>" class="btn-action-icon">
                                            <i class="ri-phone-fill text-success"></i> Call Customer
                                        </a>
                                    <?php endif; ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($address) ?>" target="_blank" rel="noopener noreferrer" class="btn-action-icon">
                                        <i class="ri-direction-line text-primary"></i> Navigate Route
                                    </a>
                                </div>
                            </div>

                            <form action="accept_pickup.php" method="post" id="form-pickup-<?= $activityId ?>" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= $activityId ?>">

                                <button type="button"
                                        class="btn-start-pickup"
                                        data-activity-id="<?= $activityId ?>"
                                        data-cust-name="<?= e($custName) ?>"
                                        data-scrap-type="<?= e($scrapType) ?>"
                                        data-weight="<?= e($weight) ?>">
                                    <i class="ri-play-circle-fill fs-5"></i>
                                    Start Pickup Job
                                </button>
                            </form>

                            <form action="reject_pickup.php"
                                  method="post"
                                  class="mt-2"
                                  onsubmit="return confirm('Are you sure you want to reject this pickup request?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= $activityId ?>">
                                <button type="submit" class="btn-reject-pickup">
                                    <i class="ri-close-circle-line fs-5"></i>
                                    Reject Pickup
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-alert">
                        <div class="empty-icon-circle">
                            <i class="ri-inbox-2-line"></i>
                        </div>
                        <h4 class="fw-bold mb-2">No Assigned Pickups</h4>
                        <p class="mb-0">You currently have no pickup requests assigned to your queue.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal fade" id="confirmStartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content custom-modal-content">
                <div class="modal-header custom-modal-header">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="ri-truck-fill text-success"></i> Confirm Start Pickup
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body custom-modal-body">
                    <p class="text-secondary mb-3">You are about to initiate this pickup route. Please confirm the job details below:</p>

                    <div class="p-3 bg-light rounded-3 mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Customer:</span>
                            <span class="fw-bold text-dark" id="modalCustName">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Scrap Material:</span>
                            <span class="fw-bold text-dark" id="modalScrapType">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Est. Weight:</span>
                            <span class="fw-bold text-emerald" id="modalWeight">-</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light flex-grow-1 py-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmModalSubmitBtn" class="btn btn-success flex-grow-1 py-2 fw-bold text-white" style="background: var(--eco-primary); border: none;">
                            <i class="ri-play-fill me-1"></i> Begin Job Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mobile-bottom-nav d-md-none">
        <a href="dashboard.php" class="nav-item-btn">
            <i class="ri-dashboard-3-line"></i>
            <span>Home</span>
        </a>
        <a href="assigned_pickups.php" class="nav-item-btn active">
            <i class="ri-truck-line"></i>
            <span>Assigned</span>
        </a>
        <a href="history.php" class="nav-item-btn">
            <i class="ri-history-line"></i>
            <span>History</span>
        </a>
        <a href="profile.php" class="nav-item-btn">
            <i class="ri-user-3-line"></i>
            <span>Profile</span>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentActiveFormId = null;
        let selectedCategory = 'all';

        document.querySelectorAll('.btn-start-pickup').forEach(btn => {
            btn.addEventListener('click', () => {
                currentActiveFormId = 'form-pickup-' + btn.dataset.activityId;
                document.getElementById('modalCustName').textContent = btn.dataset.custName || '-';
                document.getElementById('modalScrapType').textContent = btn.dataset.scrapType || '-';
                document.getElementById('modalWeight').textContent = (btn.dataset.weight || '0') + ' KG';
                const modal = new bootstrap.Modal(document.getElementById('confirmStartModal'));
                modal.show();
            });
        });

        document.getElementById('confirmModalSubmitBtn').addEventListener('click', function() {
            if (currentActiveFormId) {
                document.getElementById(currentActiveFormId).submit();
            }
        });

        function filterCards() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.request-item');

            cards.forEach(card => {
                const searchData = card.getAttribute('data-search') || '';
                const categoryData = card.getAttribute('data-category') || '';
                const matchesSearch = searchData.includes(query);
                const matchesCategory = (selectedCategory === 'all') || categoryData.toLowerCase().includes(selectedCategory.toLowerCase());
                card.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
            });
        }

        function setCategoryFilter(category, btnElement) {
            selectedCategory = category;
            document.querySelectorAll('#categoryFilters button').forEach(b => b.classList.remove('active'));
            btnElement.classList.add('active');
            filterCards();
        }
    </script>
</body>
</html>