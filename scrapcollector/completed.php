<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['collector_id']) || ($_SESSION['role'] ?? '') !== 'Collector') {
    redirect('../login.php');
}

$collectorId = (int) $_SESSION['collector_id'];

// Fetch completed activity records joined with user details
$stmt = $conn->prepare("
    SELECT 
        a.activity_id, 
        a.scrap_type, 
        a.scrap_weight, 
        a.amount, 
        a.pickup_address, 
        a.completed_at, 
        u.name AS customer_name, 
        u.phone AS customer_phone 
    FROM activity a 
    INNER JOIN user u ON u.user_id = a.user_id 
    WHERE a.collector_id = ? AND a.status = 'Completed' 
    ORDER BY a.completed_at DESC
");

$stmt->bind_param('i', $collectorId);
$stmt->execute();
$result = $stmt->get_result();

// Store results in array for statistical summaries & dual view rendering
$pickups = [];
$total_weight = 0;
$total_amount = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pickups[] = $row;
        $total_weight += (float)($row['scrap_weight'] ?? 0);
        $total_amount += (float)($row['amount'] ?? 0);
    }
}
$total_count = count($pickups);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Pickups | EcoScrap Collector Hub</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Design System CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        :root {
            --eco-primary: #10b981;
            --eco-primary-hover: #059669;
            --eco-primary-light: rgba(16, 185, 129, 0.12);
            --eco-secondary: #0ea5e9;
            --eco-dark: #0f172a;
            --eco-card-bg: rgba(255, 255, 255, 0.9);
            --eco-card-border: rgba(226, 232, 240, 0.85);
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

        /* Ambient Background Blur Glows */
        .ambient-blur {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.45;
        }

        .blur-1 {
            width: 500px;
            height: 500px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.22) 0%, transparent 70%);
        }

        .blur-2 {
            width: 450px;
            height: 450px;
            bottom: -50px;
            left: -100px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.18) 0%, transparent 70%);
        }

        /* Top Navigation Header */
        .app-navbar {
            background: rgba(255, 255, 255, 0.88);
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

        /* Header Banner & Metrics */
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
            background: radial-gradient(circle at 100% 0%, rgba(16, 185, 129, 0.28) 0%, transparent 70%);
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

        /* Stat Cards */
        .stat-pill {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 14px 18px;
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

        .stat-pill-emerald .stat-icon-wrapper {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .stat-pill-blue .stat-icon-wrapper {
            background: rgba(14, 165, 233, 0.2);
            color: #38bdf8;
        }

        .stat-pill-amber .stat-icon-wrapper {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
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

        /* Filter Toolbar */
        .filter-card {
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--eco-card-border);
            border-radius: 18px;
            padding: 16px 20px;
            margin-bottom: 24px;
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

        /* Modern Table Card */
        .table-glass-card {
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--eco-card-border);
            border-radius: 20px;
            box-shadow: var(--eco-shadow);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .custom-table {
            margin-bottom: 0;
            width: 100%;
        }

        .custom-table thead tr {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .custom-table th {
            padding: 16px 20px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border: none;
        }

        .custom-table td {
            padding: 18px 20px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-primary);
            font-size: 0.92rem;
        }

        .custom-table tbody tr:last-child td {
            border-bottom: none;
        }

        .custom-table tbody tr {
            transition: background 0.2s ease;
        }

        .custom-table tbody tr:hover {
            background-color: rgba(241, 245, 249, 0.6);
        }

        /* Scrap Badges */
        .scrap-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            background: #f1f5f9;
            color: var(--text-primary);
            border: 1px solid #e2e8f0;
        }

        .badge-verified-completed {
            background: rgba(16, 185, 129, 0.12);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.25);
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .payout-text {
            font-weight: 800;
            color: #059669;
            font-size: 0.98rem;
        }

        /* Mobile Card Grid (Visible on mobile screens) */
        .mobile-cards-wrapper {
            display: none;
        }

        .mobile-completed-card {
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--eco-card-border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--eco-shadow);
            margin-bottom: 16px;
        }

        /* Empty State Styling */
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

        /* Custom Receipt Modal */
        .modal-content-custom {
            border-radius: 24px;
            border: none;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .modal-header-custom {
            background: var(--eco-dark);
            color: #ffffff;
            padding: 20px 24px;
            border: none;
        }

        .receipt-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            padding: 20px;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }

        .receipt-row:last-child {
            border-bottom: none;
        }

        /* Floating Navigation for Mobile */
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

        @media (max-width: 868px) {
            .desktop-table-wrapper {
                display: none;
            }
            .mobile-cards-wrapper {
                display: block;
            }
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
        }
    </style>
</head>

<body>

    <!-- Ambient background glows -->
    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <nav class="app-navbar py-3">
        <div class="container-fluid max-width-1140 px-4 d-flex align-items-center justify-content-between">
            <a href="dashboard.php" class="brand-logo">
                <img src="../assets/logo/ecoscrap-logo.png" alt="EcoScrap Logo" class="brand-logo-img">
                <span>EcoScrap</span>
                <span class="brand-badge">Collector Hub</span>
            </a>
            
            <div class="d-flex align-items-center gap-2">
                <a href="assigned_pickups.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="ri-time-line"></i> Active Queue
                </a>
                <a href="dashboard.php" class="btn btn-light btn-sm border rounded-pill px-3 fw-semibold d-flex align-items-center gap-1">
                    <i class="ri-arrow-left-line"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content Workspace -->
    <main class="workspace-container">

        <header class="header-banner">
            <div class="row align-items-center gy-4">
                <div class="col-lg-5">
                    <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-20 px-3 py-1.5 rounded-pill mb-2 fw-semibold fs-7">
                        <i class="ri-shield-check-line align-middle me-1"></i> Verified History Log
                    </span>
                    <h1 class="header-title">Completed Pickups</h1>
                    <p class="header-subtitle">Review your successfully collected and verified scrap transactions.</p>
                </div>
                <div class="col-lg-7">
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="stat-pill stat-pill-emerald">
                                <div class="stat-icon-wrapper">
                                    <i class="ri-checkbox-circle-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-val"><?= number_format($total_count) ?></div>
                                    <div class="stat-lbl">Pickups</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-pill stat-pill-blue">
                                <div class="stat-icon-wrapper">
                                    <i class="ri-scales-3-line"></i>
                                </div>
                                <div>
                                    <div class="stat-val"><?= number_format($total_weight, 1) ?> <small style="font-size: 0.75rem;">KG</small></div>
                                    <div class="stat-lbl">Collected</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-pill stat-pill-amber">
                                <div class="stat-icon-wrapper">
                                    <i class="ri-hand-coin-line"></i>
                                </div>
                                <div>
                                    <div class="stat-val">₹<?= number_format($total_amount, 0) ?></div>
                                    <div class="stat-lbl">Payouts</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="filter-card">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="search-box">
                        <i class="ri-search-line"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="Search by ID, customer name, phone, address...">
                    </div>
                </div>
                <div class="col-md-7 d-flex align-items-center gap-2 overflow-x-auto pb-1 pb-md-0">
                    <span class="text-muted fs-7 fw-semibold me-1 d-none d-lg-inline">Category:</span>
                    <button type="button" class="category-pill-btn active" data-category="ALL">All</button>
                    <button type="button" class="category-pill-btn" data-category="Paper">Paper</button>
                    <button type="button" class="category-pill-btn" data-category="Plastic">Plastic</button>
                    <button type="button" class="category-pill-btn" data-category="Metal">Metal</button>
                    <button type="button" class="category-pill-btn" data-category="E-Waste">E-Waste</button>
                </div>
            </div>
        </section>

        <?php if (!empty($pickups)): ?>
            
            <!-- Desktop Data Table View -->
            <div class="desktop-table-wrapper table-glass-card">
                <div class="table-responsive">
                    <table class="table custom-table align-middle" id="pickupTable">
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Customer Details</th>
                                <th>Scrap Details</th>
                                <th>Weight</th>
                                <th>Payout Amount</th>
                                <th>Completion Date</th>
                                <th>Status</th>
                                <th class="text-end">Receipt</th>
                            </tr>
                        </thead>
                        <tbody id="completedTableBody">
                            <?php foreach ($pickups as $p): 
                                $scrapType = $p['scrap_type'] ?? 'General Scrap';
                                $iconClass = 'ri-recycle-line';
                                if (stripos($scrapType, 'paper') !== false) $iconClass = 'ri-newspaper-line';
                                elseif (stripos($scrapType, 'plastic') !== false) $iconClass = 'ri-cup-line';
                                elseif (stripos($scrapType, 'metal') !== false) $iconClass = 'ri-hammer-line';
                                elseif (stripos($scrapType, 'e-waste') !== false || stripos($scrapType, 'electronic') !== false) $iconClass = 'ri-computer-line';
                            ?>
                                <tr class="pickup-row" 
                                    data-id="#<?= (int)$p['activity_id'] ?>"
                                    data-customer="<?= htmlspecialchars(strtolower($p['customer_name'] ?? '')) ?>"
                                    data-phone="<?= htmlspecialchars($p['customer_phone'] ?? '') ?>"
                                    data-category="<?= htmlspecialchars($scrapType) ?>"
                                    data-address="<?= htmlspecialchars(strtolower($p['pickup_address'] ?? '')) ?>">
                                    <td>
                                        <div class="fw-bold text-dark">#PKP-<?= sprintf('%04d', $p['activity_id']) ?></div>
                                        <small class="text-muted">ID: <?= (int)$p['activity_id'] ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($p['customer_name'] ?? 'Customer') ?></div>
                                        <small class="text-muted"><i class="ri-phone-line me-1"></i><?= htmlspecialchars($p['customer_phone'] ?? 'N/A') ?></small>
                                    </td>
                                    <td>
                                        <span class="scrap-chip">
                                            <i class="<?= $iconClass ?> text-success"></i>
                                            <?= htmlspecialchars($scrapType) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?= number_format((float)($p['scrap_weight'] ?? 0), 2) ?></span> <span class="text-muted fs-7">KG</span>
                                    </td>
                                    <td>
                                        <span class="payout-text">₹<?= number_format((float)($p['amount'] ?? 0), 2) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark fs-7">
                                            <?= !empty($p['completed_at']) ? date('d M Y', strtotime($p['completed_at'])) : '—' ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= !empty($p['completed_at']) ? date('h:i A', strtotime($p['completed_at'])) : '' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge-verified-completed">
                                            <i class="ri-checkbox-circle-fill"></i> Verified
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light border rounded-circle shadow-sm btn-view-receipt" 
                                                style="width: 36px; height: 36px;"
                                                data-id="PKP-<?= sprintf('%04d', $p['activity_id']) ?>"
                                                data-customer="<?= htmlspecialchars($p['customer_name'] ?? 'Customer') ?>"
                                                data-phone="<?= htmlspecialchars($p['customer_phone'] ?? 'N/A') ?>"
                                                data-address="<?= htmlspecialchars($p['pickup_address'] ?? 'N/A') ?>"
                                                data-type="<?= htmlspecialchars($scrapType) ?>"
                                                data-weight="<?= number_format((float)($p['scrap_weight'] ?? 0), 2) ?>"
                                                data-amount="₹<?= number_format((float)($p['amount'] ?? 0), 2) ?>"
                                                data-date="<?= !empty($p['completed_at']) ? date('d M Y, h:i A', strtotime($p['completed_at'])) : '—' ?>"
                                                title="View Digital Receipt">
                                            <i class="ri-file-list-3-line text-secondary"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div class="mobile-cards-wrapper" id="mobileCardsContainer">
                <?php foreach ($pickups as $p): 
                    $scrapType = $p['scrap_type'] ?? 'General Scrap';
                    $iconClass = 'ri-recycle-line';
                    if (stripos($scrapType, 'paper') !== false) $iconClass = 'ri-newspaper-line';
                    elseif (stripos($scrapType, 'plastic') !== false) $iconClass = 'ri-cup-line';
                    elseif (stripos($scrapType, 'metal') !== false) $iconClass = 'ri-hammer-line';
                    elseif (stripos($scrapType, 'e-waste') !== false || stripos($scrapType, 'electronic') !== false) $iconClass = 'ri-computer-line';
                ?>
                    <div class="mobile-completed-card pickup-row"
                        data-id="#<?= (int)$p['activity_id'] ?>"
                        data-customer="<?= htmlspecialchars(strtolower($p['customer_name'] ?? '')) ?>"
                        data-phone="<?= htmlspecialchars($p['customer_phone'] ?? '') ?>"
                        data-category="<?= htmlspecialchars($scrapType) ?>"
                        data-address="<?= htmlspecialchars(strtolower($p['pickup_address'] ?? '')) ?>">
                        
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="scrap-chip">
                                <i class="<?= $iconClass ?> text-success"></i>
                                <?= htmlspecialchars($scrapType) ?>
                            </span>
                            <span class="badge-verified-completed">
                                <i class="ri-checkbox-circle-fill"></i> Verified
                            </span>
                        </div>

                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($p['customer_name'] ?? 'Customer') ?></h6>
                            <span class="fw-extrabold text-success fs-5">₹<?= number_format((float)($p['amount'] ?? 0), 2) ?></span>
                        </div>

                        <p class="text-muted fs-7 mb-3">
                            <i class="ri-map-pin-line me-1 text-primary"></i> <?= htmlspecialchars($p['pickup_address'] ?? 'N/A') ?>
                        </p>

                        <div class="row g-2 pt-2 border-top border-dashed fs-7 text-muted">
                            <div class="col-6">
                                <i class="ri-scales-3-line me-1"></i> Weight: <strong class="text-dark"><?= number_format((float)($p['scrap_weight'] ?? 0), 2) ?> KG</strong>
                            </div>
                            <div class="col-6 text-end">
                                <i class="ri-calendar-line me-1"></i> <?= !empty($p['completed_at']) ? date('d M Y', strtotime($p['completed_at'])) : '—' ?>
                            </div>
                        </div>

                        <div class="mt-3 pt-2 d-flex gap-2">
                            <a href="tel:<?= htmlspecialchars($p['customer_phone'] ?? '') ?>" class="btn btn-sm btn-light border flex-grow-1 rounded-3 text-secondary fw-semibold">
                                <i class="ri-phone-line text-success me-1"></i> Call
                            </a>
                            <button class="btn btn-sm btn-outline-success flex-grow-1 rounded-3 fw-semibold btn-view-receipt"
                                    data-id="PKP-<?= sprintf('%04d', $p['activity_id']) ?>"
                                    data-customer="<?= htmlspecialchars($p['customer_name'] ?? 'Customer') ?>"
                                    data-phone="<?= htmlspecialchars($p['customer_phone'] ?? 'N/A') ?>"
                                    data-address="<?= htmlspecialchars($p['pickup_address'] ?? 'N/A') ?>"
                                    data-type="<?= htmlspecialchars($scrapType) ?>"
                                    data-weight="<?= number_format((float)($p['scrap_weight'] ?? 0), 2) ?>"
                                    data-amount="₹<?= number_format((float)($p['amount'] ?? 0), 2) ?>"
                                    data-date="<?= !empty($p['completed_at']) ? date('d M Y, h:i A', strtotime($p['completed_at'])) : '—' ?>">
                                <i class="ri-file-list-3-line me-1"></i> Receipt
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="noMatchAlert" class="empty-alert my-4 d-none">
                <div class="empty-icon-circle">
                    <i class="ri-search-line"></i>
                </div>
                <h5 class="fw-bold">No Matching Records Found</h5>
                <p class="mb-0 text-muted fs-7">Try adjusting your search terms or category filter.</p>
            </div>

        <?php else: ?>
            <div class="empty-alert">
                <div class="empty-icon-circle">
                    <i class="ri-inbox-line"></i>
                </div>
                <h5 class="fw-bold">No Completed Pickups Yet</h5>
                <p class="mb-0">Verified pickup jobs will appear here automatically once QR verification is finalized.</p>
            </div>
        <?php endif; ?>

    </main>

    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-custom">
                <div class="modal-header modal-header-custom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <img src="../assets/logo/ecoscrap-logo.png" alt="EcoScrap Logo" style="height:28px; width:auto;">
                        <h6 class="modal-title fw-bold text-white mb-0">Verified Pickup Receipt</h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-3">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1.5 rounded-pill fw-semibold fs-7 mb-2">
                            <i class="ri-checkbox-circle-fill me-1"></i> Transaction Complete & Disbursed
                        </span>
                        <h3 class="fw-extrabold text-emerald mb-0" id="receiptModalAmount">₹0.00</h3>
                        <small class="text-muted" id="receiptModalId">#PKP-0000</small>
                    </div>

                    <div class="receipt-box mb-3">
                        <div class="receipt-row">
                            <span class="text-muted">Customer Name</span>
                            <strong id="receiptModalCustomer" class="text-dark">—</strong>
                        </div>
                        <div class="receipt-row">
                            <span class="text-muted">Phone Number</span>
                            <span id="receiptModalPhone" class="fw-semibold text-dark">—</span>
                        </div>
                        <div class="receipt-row">
                            <span class="text-muted">Category</span>
                            <strong id="receiptModalType" class="text-dark">—</strong>
                        </div>
                        <div class="receipt-row">
                            <span class="text-muted">Scrap Weight</span>
                            <span id="receiptModalWeight" class="fw-bold text-dark">—</span>
                        </div>
                        <div class="receipt-row">
                            <span class="text-muted">Completion Time</span>
                            <span id="receiptModalDate" class="text-dark fs-7">—</span>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border">
                        <small class="text-muted d-block fw-semibold uppercase mb-1 fs-8">Pickup Address</small>
                        <p class="mb-0 fs-7 text-dark fw-medium" id="receiptModalAddress">—</p>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top-0 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 flex-grow-1 fw-semibold" onclick="window.print()">
                        <i class="ri-printer-line me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-emerald btn-sm rounded-3 flex-grow-1 text-white fw-bold" style="background: var(--eco-primary);" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="mobile-bottom-nav d-md-none">
        <a href="dashboard.php" class="nav-item-btn">
            <i class="ri-dashboard-3-line"></i>
            <span>Home</span>
        </a>
        <a href="assigned_pickups.php" class="nav-item-btn">
            <i class="ri-time-line"></i>
            <span>Assigned</span>
        </a>
        <a href="completed_pickups.php" class="nav-item-btn active">
            <i class="ri-checkbox-circle-line"></i>
            <span>Completed</span>
        </a>
        <a href="../logout.php" class="nav-item-btn text-danger">
            <i class="ri-logout-box-r-line"></i>
            <span>Logout</span>
        </a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const categoryBtns = document.querySelectorAll('.category-pill-btn');
            const rows = document.querySelectorAll('.pickup-row');
            const noMatchAlert = document.getElementById('noMatchAlert');

            let currentCategory = 'ALL';

            function filterRows() {
                const query = searchInput.value.toLowerCase().trim();
                let visibleCount = 0;

                rows.forEach(row => {
                    const id = row.getAttribute('data-id').toLowerCase();
                    const customer = row.getAttribute('data-customer');
                    const phone = row.getAttribute('data-phone');
                    const category = row.getAttribute('data-category');
                    const address = row.getAttribute('data-address');

                    const matchesSearch = !query || 
                        id.includes(query) || 
                        customer.includes(query) || 
                        phone.includes(query) || 
                        address.includes(query) ||
                        category.toLowerCase().includes(query);

                    const matchesCategory = (currentCategory === 'ALL') || 
                        category.toLowerCase().includes(currentCategory.toLowerCase());

                    if (matchesSearch && matchesCategory) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (noMatchAlert) {
                    if (visibleCount === 0 && rows.length > 0) {
                        noMatchAlert.classList.remove('d-none');
                    } else {
                        noMatchAlert.classList.add('d-none');
                    }
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterRows);
            }

            categoryBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    categoryBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentCategory = this.getAttribute('data-category');
                    filterRows();
                });
            });

            // Receipt Modal Populator
            const receiptModalEl = document.getElementById('receiptModal');
            if (receiptModalEl) {
                const receiptModal = new bootstrap.Modal(receiptModalEl);
                document.querySelectorAll('.btn-view-receipt').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('receiptModalId').innerText = this.getAttribute('data-id');
                        document.getElementById('receiptModalCustomer').innerText = this.getAttribute('data-customer');
                        document.getElementById('receiptModalPhone').innerText = this.getAttribute('data-phone');
                        document.getElementById('receiptModalAddress').innerText = this.getAttribute('data-address');
                        document.getElementById('receiptModalType').innerText = this.getAttribute('data-type');
                        document.getElementById('receiptModalWeight').innerText = this.getAttribute('data-weight') + ' KG';
                        document.getElementById('receiptModalAmount').innerText = this.getAttribute('data-amount');
                        document.getElementById('receiptModalDate').innerText = this.getAttribute('data-date');
                        receiptModal.show();
                    });
                });
            }
        });
    </script>
</body>

</html>
<?php 
if (isset($stmt)) {
    $stmt->close(); 
}
?>