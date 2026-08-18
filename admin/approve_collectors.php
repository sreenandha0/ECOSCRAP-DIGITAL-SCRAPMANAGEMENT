<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';


// =====================================================
// ADMIN PROTECTION
// =====================================================

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header('Location: ../login.php');
    exit();
}


// =====================================================
// SAFE OUTPUT FUNCTION
// =====================================================

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


// =====================================================
// LOAD COLLECTORS
// =====================================================

$pendingCollectors = [];
$processedCollectors = [];

$sql = "
    SELECT *
    FROM scrapcollector
    ORDER BY created_at DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        if (
            ($row['verification_status'] ?? '')
            === 'Pending'
        ) {
            $pendingCollectors[] = $row;
        } else {
            $processedCollectors[] = $row;
        }
    }
}


// =====================================================
// CURRENT ADMIN NAME
// =====================================================

$adminName = $_SESSION['name'] ?? 'Admin';

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Collector Verification | EcoScrap Admin
    </title>


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css"
    >


    <style>

        :root {
            --primary: #16a34a;
            --primary-dark: #15803d;
            --primary-light: #dcfce7;
            --background: #f5f7f6;
            --surface: #ffffff;
            --text: #17221b;
            --muted: #718078;
            --border: #e3ebe5;
            --danger: #dc2626;
            --danger-light: #fee2e2;
            --warning: #d97706;
            --warning-light: #fef3c7;
            --shadow: 0 14px 35px rgba(20, 83, 45, .08);
        }


        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            min-height: 100vh;
            background: var(--background);
            color: var(--text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        a {
            color: inherit;
            text-decoration: none;
        }


        button,
        textarea {
            font: inherit;
        }


        /* =================================================
           LAYOUT
        ================================================= */

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }


        .main-content {
            width: calc(100% - 260px);
            margin-left: 260px;
            padding: 30px 38px 60px;
        }


        /* =================================================
           SIDEBAR
        ================================================= */

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1000;
            width: 260px;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            background: #103d29;
            color: #ffffff;
        }


        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px 28px;
        }


        .brand img {
            width: 46px;
            height: 46px;
            padding: 5px;
            object-fit: contain;
            background: #ffffff;
            border-radius: 12px;
        }


        .brand strong {
            display: block;
            font-size: 19px;
        }


        .brand span {
            display: block;
            margin-top: 3px;
            color: #a9d6ba;
            font-size: 11px;
        }


        .nav-title {
            padding: 16px 12px 9px;
            color: #8ec6a2;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }


        .sidebar-nav {
            display: grid;
            gap: 6px;
        }


        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            color: #d7eee0;
            border-radius: 12px;
            font-size: 14px;
            transition: .2s ease;
        }


        .sidebar-nav a i {
            width: 20px;
            font-size: 19px;
        }


        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: #1d6842;
            color: #ffffff;
        }


        .sidebar-bottom {
            margin-top: auto;
        }


        .admin-profile {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px 10px;
            margin-bottom: 10px;
            border-top: 1px solid rgba(255,255,255,.14);
        }


        .admin-avatar {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: #155d35;
            background: #b9e7c6;
            border-radius: 50%;
            font-weight: 800;
        }


        .admin-profile strong {
            display: block;
            font-size: 13px;
        }


        .admin-profile span {
            color: #a9d6ba;
            font-size: 11px;
        }


        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            color: #ffb4b4;
            border-radius: 10px;
            font-size: 13px;
        }


        .logout-link:hover {
            background: rgba(255,255,255,.08);
        }


        /* =================================================
           HEADER
        ================================================= */

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }


        .page-title h1 {
            margin: 0;
            color: #173322;
            font-size: clamp(25px, 3vw, 36px);
            letter-spacing: -.8px;
        }


        .page-title h1 span {
            color: var(--primary);
        }


        .page-title p {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 14px;
        }


        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }


        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            color: var(--primary-dark);
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            transition: .2s ease;
        }


        .back-button:hover {
            color: #ffffff;
            background: var(--primary);
            border-color: var(--primary);
        }


        .mobile-menu {
            display: none;
            width: 44px;
            height: 44px;
            place-items: center;
            color: var(--primary-dark);
            background: var(--primary-light);
            border: 0;
            border-radius: 11px;
            cursor: pointer;
            font-size: 21px;
        }


        /* =================================================
           ALERTS
        ================================================= */

        .system-message {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 14px 17px;
            margin-bottom: 24px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }


        .system-message.success {
            color: #166534;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
        }


        .system-message.error {
            color: #991b1b;
            background: #fee2e2;
            border: 1px solid #fecaca;
        }


        /* =================================================
           SECTION HEADERS
        ================================================= */

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin: 28px 0 16px;
        }


        .section-header h2 {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
            color: #203126;
            font-size: 18px;
        }


        .section-header h2 i {
            color: var(--primary);
            font-size: 21px;
        }


        .badge-count {
            min-width: 25px;
            height: 25px;
            display: inline-grid;
            place-items: center;
            padding: 0 7px;
            color: #a16207;
            background: var(--warning-light);
            border-radius: 20px;
            font-size: 11px;
        }


        .badge-count.neutral {
            color: #64748b;
            background: #e2e8f0;
        }


        /* =================================================
           SEARCH
        ================================================= */

        .search-panel {
            display: flex;
            align-items: center;
            gap: 11px;
            max-width: 440px;
            padding: 13px 15px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: var(--shadow);
        }


        .search-panel i {
            color: var(--primary);
            font-size: 19px;
        }


        .search-panel input {
            width: 100%;
            border: 0;
            outline: 0;
            color: #263a2d;
            background: transparent;
            font-size: 13px;
        }


        /* =================================================
           COLLECTOR CARDS
        ================================================= */

        .cards-queue {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }


        .collector-card {
            padding: 22px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            transition: .2s ease;
        }


        .collector-card:hover {
            border-color: #b8ddc1;
            transform: translateY(-2px);
        }


        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            padding-bottom: 19px;
            margin-bottom: 19px;
            border-bottom: 1px solid #edf2ee;
        }


        .user-identity {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
        }


        .user-avatar {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: var(--primary-dark);
            background: var(--primary-light);
            border-radius: 13px;
            font-size: 20px;
        }


        .user-name {
            overflow: hidden;
            color: #203126;
            font-size: 16px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }


        .status-pending {
            color: #a16207;
            background: var(--warning-light);
        }


        .status-approved {
            color: #15803d;
            background: var(--primary-light);
        }


        .status-rejected {
            color: #b91c1c;
            background: var(--danger-light);
        }


        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px 20px;
        }


        .detail-item {
            min-width: 0;
        }


        .detail-label {
            display: block;
            margin-bottom: 5px;
            color: #93a097;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
        }


        .detail-value {
            display: block;
            overflow: hidden;
            color: #3d5145;
            font-size: 13px;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .detail-value i {
            margin-right: 4px;
            color: var(--primary);
        }


        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #edf2ee;
        }


        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 0 13px;
            border: 1px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 800;
            transition: .2s ease;
        }


        .action-button:hover {
            transform: translateY(-1px);
        }


        .btn-approve {
            color: #ffffff;
            background: var(--primary);
            border-color: var(--primary);
        }


        .btn-approve:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }


        .btn-reject {
            color: #b91c1c;
            background: #fff7f7;
            border-color: #fecaca;
        }


        .btn-reject:hover {
            color: #ffffff;
            background: var(--danger);
            border-color: var(--danger);
        }


        .empty-state {
            grid-column: 1 / -1;
            padding: 55px 25px;
            color: var(--muted);
            background: #ffffff;
            border: 1px dashed #cbdcd0;
            border-radius: 18px;
            text-align: center;
        }


        .empty-state i {
            display: block;
            margin-bottom: 12px;
            color: var(--primary);
            font-size: 48px;
        }


        .empty-state h3 {
            margin: 0 0 7px;
            color: #203126;
            font-size: 17px;
        }


        .empty-state p {
            margin: 0;
            font-size: 13px;
        }


        /* =================================================
           CUSTOM MODALS
        ================================================= */

        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .48);
            backdrop-filter: blur(4px);
        }


        .modal-overlay.active {
            display: flex;
        }


        .modal-box {
            width: min(100%, 480px);
            padding: 27px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 25px 70px rgba(15,23,42,.22);
            animation: modalOpen .18s ease;
        }


        @keyframes modalOpen {
            from {
                opacity: 0;
                transform: translateY(8px) scale(.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }


        .modal-title {
            margin: 0;
            color: #203126;
            font-size: 19px;
            font-weight: 800;
        }


        .modal-desc {
            margin: 11px 0 20px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }


        .modal-textarea {
            width: 100%;
            min-height: 105px;
            padding: 13px;
            resize: vertical;
            color: #263a2d;
            border: 1px solid var(--border);
            border-radius: 11px;
            outline: 0;
            font-size: 13px;
        }


        .modal-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(22,163,74,.11);
        }


        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 9px;
            margin-top: 22px;
        }


        @media (max-width: 1050px) {
            .cards-queue {
                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 850px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 22px 18px 45px;
            }

            .mobile-menu {
                display: grid;
            }

            .back-button span {
                display: none;
            }

            .back-button {
                padding: 12px;
            }
        }


        @media (max-width: 600px) {
            .page-header {
                align-items: flex-start;
            }

            .header-actions {
                align-items: flex-start;
            }

            .page-title p {
                line-height: 1.5;
            }

            .search-panel {
                max-width: none;
            }

            .section-header {
                align-items: flex-start;
            }

            .cards-queue {
                grid-template-columns: 1fr;
            }

            .collector-card {
                padding: 17px;
            }

            .card-top {
                flex-direction: column;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .card-actions {
                flex-direction: column;
            }

            .action-button {
                width: 100%;
            }

            .modal-footer {
                flex-direction: column-reverse;
            }

            .modal-footer .action-button {
                width: 100%;
            }
        }

    </style>

</head>


<body>

<div class="dashboard-layout">


    <!-- =================================================
         SIDEBAR
    ================================================= -->

    <aside class="sidebar" id="sidebar">

        <div class="brand">

            <img
                src="../assets/logo/ecoscrap-logo.png"
                alt="EcoScrap logo"
            >

            <div>
                <strong>EcoScrap</strong>
                <span>Admin workspace</span>
            </div>

        </div>


        <div class="nav-title">
            Workspace
        </div>


        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="ri-dashboard-line"></i>
                Dashboard
            </a>


            <a href="manage.php">
                <i class="ri-recycle-line"></i>
                Scrap Requests
            </a>


            <a
                href="approve_collectors.php"
                class="active"
            >
                <i class="ri-truck-line"></i>
                Collectors
            </a>


            <a href="manageuser.php">
                <i class="ri-group-line"></i>
                Users
            </a>


            <a href="reports.php">
                <i class="ri-bar-chart-line"></i>
                Reports
            </a>


            <a href="profile.php">
                <i class="ri-settings-3-line"></i>
                Profile
            </a>

        </nav>


        <div class="sidebar-bottom">

            <div class="admin-profile">

                <div class="admin-avatar">
                    <?php
                    echo strtoupper(
                        substr($adminName, 0, 1)
                    );
                    ?>
                </div>


                <div>

                    <strong>
                        <?php echo e($adminName); ?>
                    </strong>

                    <span>
                        Administrator
                    </span>

                </div>

            </div>


            <a
                href="../logout.php"
                class="logout-link"
            >
                <i class="ri-logout-box-r-line"></i>
                Logout
            </a>

        </div>

    </aside>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <main class="main-content">

        <header class="page-header">

            <div class="page-title">

                <h1>
                    Collector Verification
                </h1>

                <p>
                    Review and manage new collector registrations.
                </p>

            </div>


            <div class="header-actions">

                <a
                    href="dashboard.php"
                    class="back-button"
                >
                    <i class="ri-arrow-left-line"></i>
                    <span>Dashboard</span>
                </a>


                <button
                    type="button"
                    class="mobile-menu"
                    id="mobileMenu"
                    aria-label="Open menu"
                >
                    <i class="ri-menu-line"></i>
                </button>

            </div>

        </header>


        <?php if (function_exists('displayMessage')): ?>

            <div class="system-message success">
                <i class="ri-information-line"></i>
                <?php displayMessage(); ?>
            </div>

        <?php endif; ?>


        <section class="section-header">

            <h2>
                <i class="ri-time-line"></i>
                Action Required

                <span class="badge-count">
                    <?php echo count($pendingCollectors); ?>
                </span>
            </h2>


            <div class="search-panel">

                <i class="ri-search-line"></i>

                <input
                    type="search"
                    id="searchInput"
                    placeholder="Search name, email, or pincode..."
                    autocomplete="off"
                >

            </div>

        </section>


        <section
            class="cards-queue"
            id="pendingQueue"
        >

            <?php if (count($pendingCollectors) > 0): ?>

                <?php foreach ($pendingCollectors as $collector): ?>

                    <?php
                    $collectorId =
                        (int)($collector['collector_id'] ?? 0);

                    $collectorName =
                        $collector['name'] ?? 'Unknown Collector';

                    $collectorEmail =
                        $collector['email'] ?? 'N/A';

                    $collectorPhone =
                        $collector['phone'] ?? 'N/A';

                    $collectorVehicle =
                        $collector['vehicle_no'] ?? 'N/A';

                    $collectorPincode =
                        $collector['pincode'] ?? 'N/A';

                    $createdAt =
                        !empty($collector['created_at'])
                            ? date(
                                'M d, Y',
                                strtotime(
                                    $collector['created_at']
                                )
                            )
                            : 'N/A';

                    $searchText = strtolower(
                        $collectorName . ' ' .
                        $collectorEmail . ' ' .
                        $collectorPincode . ' ' .
                        $collectorPhone . ' ' .
                        $collectorVehicle
                    );
                    ?>


                    <article
                        class="collector-card"
                        data-search="<?php echo e($searchText); ?>"
                    >

                        <div class="card-top">

                            <div class="user-identity">

                                <div class="user-avatar">
                                    <i class="ri-user-line"></i>
                                </div>


                                <div class="user-name">
                                    <?php echo e($collectorName); ?>
                                </div>

                            </div>


                            <span class="status-badge status-pending">
                                <i class="ri-time-line"></i>
                                Pending Review
                            </span>

                        </div>


                        <div class="details-grid">

                            <div class="detail-item">
                                <span class="detail-label">
                                    Email
                                </span>

                                <span class="detail-value">
                                    <i class="ri-mail-line"></i>
                                    <?php echo e($collectorEmail); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">
                                    Phone
                                </span>

                                <span class="detail-value">
                                    <i class="ri-phone-line"></i>
                                    <?php echo e($collectorPhone); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">
                                    Vehicle
                                </span>

                                <span class="detail-value">
                                    <i class="ri-truck-line"></i>
                                    <?php echo e($collectorVehicle); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">
                                    Pincode
                                </span>

                                <span class="detail-value">
                                    <i class="ri-map-pin-line"></i>
                                    <?php echo e($collectorPincode); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">
                                    Applied
                                </span>

                                <span class="detail-value">
                                    <i class="ri-calendar-line"></i>
                                    <?php echo e($createdAt); ?>
                                </span>
                            </div>

                        </div>


                        <div class="card-actions">

                            <button
                                type="button"
                                class="action-button btn-reject"
                                data-action="reject"
                                data-id="<?php echo $collectorId; ?>"
                                data-name="<?php echo e($collectorName); ?>"
                            >
                                <i class="ri-close-line"></i>
                                Decline
                            </button>


                            <button
                                type="button"
                                class="action-button btn-approve"
                                data-action="approve"
                                data-id="<?php echo $collectorId; ?>"
                                data-name="<?php echo e($collectorName); ?>"
                            >
                                <i class="ri-check-line"></i>
                                Approve Collector
                            </button>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">

                    <i class="ri-checkbox-circle-line"></i>

                    <h3>
                        You're all caught up!
                    </h3>

                    <p>
                        No collector registrations are currently pending review.
                    </p>

                </div>

            <?php endif; ?>

        </section>


        <section class="section-header">

            <h2>

                <i class="ri-folder-open-line"></i>
                Processed Applications

                <span class="badge-count neutral">
                    <?php echo count($processedCollectors); ?>
                </span>

            </h2>

        </section>


        <section
            class="cards-queue"
            id="processedQueue"
        >

            <?php if (count($processedCollectors) > 0): ?>

                <?php foreach ($processedCollectors as $collector): ?>

                    <?php
                    $collectorName =
                        $collector['name'] ?? 'Unknown Collector';

                    $collectorEmail =
                        $collector['email'] ?? 'N/A';

                    $collectorPhone =
                        $collector['phone'] ?? 'N/A';

                    $collectorVehicle =
                        $collector['vehicle_no'] ?? 'N/A';

                    $collectorPincode =
                        $collector['pincode'] ?? 'N/A';

                    $collectorStatus =
                        $collector['verification_status']
                        ?? 'Rejected';

                    $createdAt =
                        !empty($collector['created_at'])
                            ? date(
                                'M d, Y',
                                strtotime(
                                    $collector['created_at']
                                )
                            )
                            : 'N/A';

                    $searchText = strtolower(
                        $collectorName . ' ' .
                        $collectorEmail . ' ' .
                        $collectorPincode . ' ' .
                        $collectorPhone . ' ' .
                        $collectorVehicle
                    );
                    ?>


                    <article
                        class="collector-card"
                        data-search="<?php echo e($searchText); ?>"
                    >

                        <div class="card-top">

                            <div class="user-identity">

                                <div
                                    class="user-avatar"
                                    style="background:#f1f5f9; color:#64748b;"
                                >
                                    <i class="ri-user-line"></i>
                                </div>


                                <div class="user-name">
                                    <?php echo e($collectorName); ?>
                                </div>

                            </div>


                            <?php if ($collectorStatus === 'Approved'): ?>

                                <span class="status-badge status-approved">
                                    <i class="ri-checkbox-circle-fill"></i>
                                    Approved
                                </span>

                            <?php else: ?>

                                <span class="status-badge status-rejected">
                                    <i class="ri-close-circle-fill"></i>
                                    Rejected
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="details-grid">

                            <div class="detail-item">
                                <span class="detail-label">
                                    Email
                                </span>

                                <span class="detail-value">
                                    <i class="ri-mail-line"></i>
                                    <?php echo e($collectorEmail); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">
                                    Phone
                                </span>

                                <span class="detail-value">
                                    <i class="ri-phone-line"></i>
                                    <?php echo e($collectorPhone); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">
                                    Vehicle
                                </span>

                                <span class="detail-value">
                                    <i class="ri-truck-line"></i>
                                    <?php echo e($collectorVehicle); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">
                                    Pincode
                                </span>

                                <span class="detail-value">
                                    <i class="ri-map-pin-line"></i>
                                    <?php echo e($collectorPincode); ?>
                                </span>
                            </div>


                            <div class="detail-item">
                                <span class="detail-label">
                                    Applied
                                </span>

                                <span class="detail-value">
                                    <i class="ri-calendar-line"></i>
                                    <?php echo e($createdAt); ?>
                                </span>
                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">

                    <i
                        class="ri-inbox-line"
                        style="color:#94a3b8;"
                    ></i>

                    <h3>
                        No processed applications
                    </h3>

                    <p>
                        Approved and rejected applications will appear here.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<!-- =================================================
     APPROVE MODAL
================================================= -->

<div
    class="modal-overlay"
    id="approveModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="approveModalTitle"
>

    <div class="modal-box">

        <h3
            class="modal-title"
            id="approveModalTitle"
        >
            Approve Collector?
        </h3>


        <p
            class="modal-desc"
            id="approveModalText"
        >
            Are you sure you want to approve this collector?
        </p>


        <div class="modal-footer">

            <button
                type="button"
                class="action-button btn-reject"
                id="cancelApprove"
            >
                Cancel
            </button>


            <a
                href="#"
                class="action-button btn-approve"
                id="approveConfirmBtn"
            >
                <i class="ri-check-line"></i>
                Confirm Approval
            </a>

        </div>

    </div>

</div>


<!-- =================================================
     REJECT MODAL
================================================= -->

<div
    class="modal-overlay"
    id="rejectModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="rejectModalTitle"
>

    <div class="modal-box">

        <h3
            class="modal-title"
            id="rejectModalTitle"
        >
            Decline Application?
        </h3>


        <p class="modal-desc">
            Provide a reason for declining this application.
            This field is optional.
        </p>


        <textarea
            id="rejectReason"
            class="modal-textarea"
            placeholder="Example: Invalid vehicle registration or outside service area..."
        ></textarea>


        <div class="modal-footer">

            <button
                type="button"
                class="action-button btn-reject"
                id="cancelReject"
            >
                Cancel
            </button>


            <button
                type="button"
                class="action-button btn-reject"
                id="rejectConfirmBtn"
            >
                <i class="ri-close-line"></i>
                Confirm Decline
            </button>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById('sidebar');

        const mobileMenu =
            document.getElementById('mobileMenu');

        const searchInput =
            document.getElementById('searchInput');

        const approveModal =
            document.getElementById('approveModal');

        const rejectModal =
            document.getElementById('rejectModal');

        const approveModalText =
            document.getElementById('approveModalText');

        const approveConfirmBtn =
            document.getElementById('approveConfirmBtn');

        const rejectReason =
            document.getElementById('rejectReason');

        const rejectConfirmBtn =
            document.getElementById('rejectConfirmBtn');

        const cancelApprove =
            document.getElementById('cancelApprove');

        const cancelReject =
            document.getElementById('cancelReject');

        let currentCollectorId = null;


        // -------------------------------------------------
        // Mobile sidebar
        // -------------------------------------------------

        if (mobileMenu && sidebar) {
            mobileMenu.addEventListener(
                'click',
                function () {
                    sidebar.classList.toggle('open');
                }
            );
        }


        // -------------------------------------------------
        // Search cards
        // -------------------------------------------------

        if (searchInput) {

            searchInput.addEventListener(
                'input',
                function () {

                    const query =
                        searchInput.value
                            .toLowerCase()
                            .trim();

                    const cards =
                        document.querySelectorAll(
                            '.collector-card'
                        );

                    cards.forEach(
                        function (card) {

                            const searchableText =
                                (
                                    card.dataset.search || ''
                                ).toLowerCase();

                            card.style.display =
                                searchableText.includes(query)
                                    ? ''
                                    : 'none';
                        }
                    );

                }
            );

        }


        // -------------------------------------------------
        // Approve modal
        // -------------------------------------------------

        document
            .querySelectorAll('[data-action="approve"]')
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            currentCollectorId =
                                button.dataset.id;

                            const collectorName =
                                button.dataset.name
                                || 'this collector';

                            approveModalText.textContent =
                                'Are you sure you want to approve '
                                + collectorName
                                + '? The collector will gain access to the collector portal.';

                            approveConfirmBtn.href =
                                'approve_collector_process.php'
                                + '?id='
                                + encodeURIComponent(
                                    currentCollectorId
                                )
                                + '&action=approve';

                            approveModal.classList.add(
                                'active'
                            );

                        }
                    );

                }
            );


        // -------------------------------------------------
        // Reject modal
        // -------------------------------------------------

        document
            .querySelectorAll('[data-action="reject"]')
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            currentCollectorId =
                                button.dataset.id;

                            rejectReason.value = '';

                            rejectModal.classList.add(
                                'active'
                            );

                        }
                    );

                }
            );


        // -------------------------------------------------
        // Confirm rejection
        // -------------------------------------------------

        if (rejectConfirmBtn) {

            rejectConfirmBtn.addEventListener(
                'click',
                function () {

                    if (!currentCollectorId) {
                        return;
                    }

                    const reason =
                        encodeURIComponent(
                            rejectReason.value.trim()
                        );

                    window.location.href =
                        'approve_collector_process.php'
                        + '?id='
                        + encodeURIComponent(
                            currentCollectorId
                        )
                        + '&action=reject'
                        + '&reason='
                        + reason;

                }
            );

        }


        // -------------------------------------------------
        // Close modals
        // -------------------------------------------------

        function closeModals() {

            approveModal.classList.remove(
                'active'
            );

            rejectModal.classList.remove(
                'active'
            );

            currentCollectorId = null;
        }


        cancelApprove.addEventListener(
            'click',
            closeModals
        );


        cancelReject.addEventListener(
            'click',
            closeModals
        );


        document
            .querySelectorAll('.modal-overlay')
            .forEach(
                function (overlay) {

                    overlay.addEventListener(
                        'click',
                        function (event) {

                            if (
                                event.target === overlay
                            ) {
                                closeModals();
                            }

                        }
                    );

                }
            );


        document.addEventListener(
            'keydown',
            function (event) {

                if (event.key === 'Escape') {
                    closeModals();
                }

            }
        );

    }
);

</script>

</body>

</html>