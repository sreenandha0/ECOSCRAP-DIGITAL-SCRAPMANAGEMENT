<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = (int) $_SESSION['user_id'];

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$user_stmt = $conn->prepare("SELECT name FROM user WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result()->fetch_assoc();
$current_user_name = $user_res['name'] ?? 'User';
$user_stmt->close();

$sql = "
    SELECT 
        a.*, 
        c.name AS collector_name, 
        c.phone AS collector_phone,
        c.email AS collector_email,
        c.vehicle_no AS collector_vehicle
    FROM activity a
    LEFT JOIN scrapcollector c 
        ON a.collector_id = c.collector_id
    WHERE a.user_id = ?
    ORDER BY a.request_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$pending_count = 0;
$approved_count = 0;
$assigned_count = 0;
$in_progress_count = 0;
$completed_count = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $st = strtolower(trim($row['status'] ?? 'pending'));

        if ($st === 'pending') $pending_count++;
        elseif ($st === 'approved') $approved_count++;
        elseif ($st === 'assigned') $assigned_count++;
        elseif ($st === 'in progress') $in_progress_count++;
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

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
            padding-bottom: 90px;
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
            width: 320px;
            height: 100%;
            background: radial-gradient(circle at 100% 0%, rgba(16, 185, 129, 0.30) 0%, transparent 70%);
            pointer-events: none;
        }

        .header-title {
            font-size: 1.8rem;
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
            box-shadow: var(--shadow);
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

        .card-wrapper {
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--eco-card-border);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: all 0.25s ease;
        }

        .card-wrapper:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(16, 185, 129, 0.35);
        }

        .status-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-pending { background: rgba(245, 158, 11, 0.12); color: #d97706; }
        .badge-approved { background: rgba(16, 185, 129, 0.12); color: #047857; }
        .badge-assigned { background: rgba(14, 165, 233, 0.12); color: #0284c7; }
        .badge-in-progress { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
        .badge-completed { background: rgba(16, 185, 129, 0.20); color: #059669; }

        .collector-box {
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid var(--eco-card-border);
            border-radius: 14px;
            padding: 16px;
            margin: 16px 0;
        }

        .btn-custom-outline {
            background: #ffffff;
            border: 1px solid var(--eco-card-border);
            color: var(--text-primary);
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-custom-outline:hover {
            border-color: var(--eco-primary);
            color: var(--eco-primary);
            background: rgba(16, 185, 129, 0.05);
        }

        .btn-custom-primary {
            background: linear-gradient(135deg, var(--eco-primary) 0%, #059669 100%);
            color: #ffffff;
            border: none;
            padding: 9px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-custom-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #ffffff;
        }

        .empty-state {
            background: var(--eco-card-bg);
            border: 1px dashed #cbd5e1;
            border-radius: 22px;
            padding: 60px 24px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .empty-icon {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            margin: 0 auto 18px;
            display: grid;
            place-items: center;
            background: var(--eco-primary-light);
            color: var(--eco-primary);
            font-size: 2.1rem;
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
                font-size: 1.45rem;
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
                <span class="brand-badge">User Hub</span>
            </a>

            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-emerald-light text-emerald px-3 py-2 rounded-pill d-none d-sm-inline-flex align-items-center gap-2" style="background: rgba(16, 185, 129, 0.1); color: #059669; font-weight: 600;">
                    <span class="spinner-grow spinner-grow-sm text-success" role="status" style="width: 8px; height: 8px;"></span>
                    Live Tracking
                </span>

                <div class="dropdown">
                    <button class="btn border-0 dropdown-toggle fw-bold text-dark p-0" data-bs-toggle="dropdown">
                        <i class="ri-user-3-line me-1"></i> <?= e($current_user_name) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="workspace-container">
        <header class="header-banner">
            <div class="row align-items-center g-3">
                <div class="col-lg-7">
                    <h1 class="header-title">My Pickup Requests</h1>
                    <p class="header-subtitle">Track each request, review collector details, and follow your pickup progress in one place.</p>
                </div>
                <div class="col-lg-5">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="stat-pill stat-pill-green">
                                <div class="stat-icon-wrapper"><i class="ri-time-line"></i></div>
                                <div>
                                    <div class="stat-val"><?= (int)$pending_count ?></div>
                                    <div class="stat-lbl">Pending</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-pill stat-pill-blue">
                                <div class="stat-icon-wrapper"><i class="ri-truck-line"></i></div>
                                <div>
                                    <div class="stat-val"><?= (int)($assigned_count + $in_progress_count) ?></div>
                                    <div class="stat-lbl">Active</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="filter-card">
            <form class="row g-3 align-items-center" id="filterForm">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="ri-search-2-line"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Search pickup requests...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="statusSelect" class="form-select search-input">
                        <option value="">Status (All)</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="assigned">Assigned</option>
                        <option value="in progress">In Progress</option>
                        <option value="verified">Verified / Completed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="scrapTypeSelect" class="form-select search-input">
                        <option value="">Scrap Type (All)</option>
                        <option value="plastic">Plastic</option>
                        <option value="metal">Metal</option>
                        <option value="glass">Glass</option>
                        <option value="paper">Paper</option>
                        <option value="e-waste">E-Waste</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-10 mx-auto">
                <?php if (!empty($rows)) : ?>
                    <?php foreach ($rows as $row) :
                        $status = $row['status'] ?? 'Pending';
                        $status_clean = strtolower(trim($status));
                        $scrapType = $row['scrap_type'] ?? 'General';
                        $collectorName = $row['collector_name'] ?? '';
                        $collectorPhone = $row['collector_phone'] ?? '';
                        $collectorVehicle = $row['collector_vehicle'] ?? '';
                        $isAccepted = !empty($collectorName) && !in_array($status_clean, ['pending', 'approved', 'assigned'], true);
                    ?>
                        <div class="card-wrapper" data-status="<?= e($status_clean) ?>" data-type="<?= e(strtolower($scrapType)) ?>" data-search="<?= e(strtolower(
                            ($row['pickup_address'] ?? '') . ' ' .
                            ($row['pickup_pincode'] ?? '') . ' ' .
                            ($row['scrap_type'] ?? '') . ' ' .
                            ($row['status'] ?? '') . ' ' .
                            ($collectorName ?? '')
                        )) ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold text-dark fs-6">📦 Request #REQ-<?= e($row['activity_id'] ?? '') ?></span>
                                <?php if ($status_clean === 'pending') : ?>
                                    <span class="status-badge badge-pending">🟡 Pending</span>
                                <?php elseif ($status_clean === 'approved') : ?>
                                    <span class="status-badge badge-approved">🟢 Approved</span>
                                <?php elseif ($status_clean === 'assigned') : ?>
                                    <span class="status-badge badge-assigned">🟣 Assigned</span>
                                <?php elseif ($status_clean === 'in progress') : ?>
                                    <span class="status-badge badge-in-progress">🔵 In Progress</span>
                                <?php elseif ($status_clean === 'verified' || $status_clean === 'completed') : ?>
                                    <span class="status-badge badge-completed">🟢 Completed</span>
                                <?php endif; ?>
                            </div>

                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <h5 class="fw-bold mb-1">♻ <?= e($scrapType) ?> Scrap</h5>
                                    <p class="text-muted small mb-1">📍 <?= e($row['pickup_address'] ?? '') ?>, PIN: <?= e($row['pickup_pincode'] ?? '') ?></p>
                                </div>
                                <div class="col-md-5 text-md-end">
                                    <div class="fw-bold fs-6"><?= e($row['scrap_weight'] ?? '') ?> kg</div>
                                    <div class="text-muted small">Pickup Date: <?= e($row['preferred_pickup_date'] ?? '') ?> (<?= e($row['pickup_time'] ?? '') ?>)</div>
                                    <?php if (!empty($row['amount'])) : ?>
                                        <div class="fw-bold text-success fs-5">₹ <?= e($row['amount']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($isAccepted) : ?>
                                <div class="collector-box">
                                    <span class="fw-bold d-block text-muted small mb-2">👤 ASSIGNED COLLECTOR</span>
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <div class="fw-bold text-dark"><?= e($collectorName) ?></div>
                                            <div class="small text-muted">
                                                <i class="ri-phone-line me-1"></i><?= e($collectorPhone) ?>
                                                <?php if (!empty($collectorVehicle)) : ?>
                                                    <span class="ms-2">| <i class="ri-car-line ms-1 me-1"></i><?= e($collectorVehicle) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <a href="tel:<?= e($collectorPhone) ?>" class="btn btn-sm btn-outline-success">
                                                <i class="ri-phone-fill"></i> Call Collector
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex gap-2 mt-3 pt-2 border-top border-light flex-wrap">
                                <a href="track_status.php?id=<?= e($row['activity_id'] ?? '') ?>" class="btn-custom-outline">
                                    <i class="ri-eye-line"></i> View Details
                                </a>

                                <?php if ($status_clean === 'assigned' || $status_clean === 'in progress') : ?>
                                    <a href="track_status.php?id=<?= e($row['activity_id'] ?? '') ?>" class="btn-custom-outline">
                                        <i class="ri-map-pin-time-line"></i> Track Status
                                    </a>
                                <?php endif; ?>

                                <?php if ($status_clean === 'in progress' && !empty($row['qr_code'])) : ?>
                                    <a href="../uploads/qr/<?= e($row['qr_code']) ?>" target="_blank" rel="noopener noreferrer" class="btn-custom-primary">
                                        <i class="ri-qr-code-line"></i> View QR Pass
                                    </a>
                                <?php endif; ?>

                                <?php if ($status_clean === 'verified' || $status_clean === 'completed') : ?>
                                    <a href="track_status.php?id=<?= e($row['activity_id'] ?? '') ?>" class="btn-custom-outline">
                                        <i class="ri-file-list-line"></i> View Receipt
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="ri-inbox-line"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">No Requests Found</h5>
                        <p class="text-muted small mb-0">You haven't scheduled any pickup requests yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const searchInput = document.getElementById('searchInput');
        const statusSelect = document.getElementById('statusSelect');
        const scrapTypeSelect = document.getElementById('scrapTypeSelect');

        function filterCards() {
            const searchVal = searchInput.value.toLowerCase().trim();
            const statusVal = statusSelect.value.toLowerCase().trim();
            const typeVal = scrapTypeSelect.value.toLowerCase().trim();

            document.querySelectorAll('.card-wrapper').forEach(card => {
                const text = card.getAttribute('data-search') || '';
                const cardStatus = card.getAttribute('data-status') || '';
                const cardType = card.getAttribute('data-type') || '';

                const matchesSearch = !searchVal || text.includes(searchVal);
                const matchesStatus = !statusVal || cardStatus.includes(statusVal);
                const matchesType = !typeVal || cardType.includes(typeVal);

                card.style.display = (matchesSearch && matchesStatus && matchesType) ? 'block' : 'none';
            });
        }

        searchInput.addEventListener('input', filterCards);
        statusSelect.addEventListener('change', filterCards);
        scrapTypeSelect.addEventListener('change', filterCards);
    </script>
</body>
</html>