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
    redirect('../login.php');
}


// =====================================================
// HELPER FUNCTION
// =====================================================

function getCount($conn, $query)
{
    $result = $conn->query($query);

    if (
        $result &&
        $row = $result->fetch_assoc()
    ) {
        return (int)$row['total'];
    }

    return 0;
}


// =====================================================
// DASHBOARD STATISTICS
// =====================================================

$totalUsers = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM user"
);


$totalCollectors = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM scrapcollector"
);


$pendingCollectors = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM scrapcollector
     WHERE verification_status = 'Pending'"
);


$totalRequests = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM activity"
);


// Change Pending to Approved here if Approved
// is your actual pending collection status.
$pendingRequests = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM activity
     WHERE status = 'Pending'"
);


$completedRequests = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM activity
     WHERE status = 'Completed'"
);


// =====================================================
// ADMIN NOTIFICATIONS
// =====================================================

$admin_id = (int)($_SESSION['admin_id'] ?? 1);

$unreadNotifications = 0;
$notifications = [];


// -----------------------------------------------------
// UNREAD NOTIFICATION COUNT
// -----------------------------------------------------

$notificationCountStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE recipient_type = 'Admin'
     AND recipient_id = ?
     AND is_read = 0"
);

if ($notificationCountStmt) {

    $notificationCountStmt->bind_param(
        'i',
        $admin_id
    );

    $notificationCountStmt->execute();

    $countResult =
        $notificationCountStmt->get_result();

    if (
        $countResult &&
        $countRow = $countResult->fetch_assoc()
    ) {
        $unreadNotifications =
            (int)$countRow['total'];
    }

    $notificationCountStmt->close();
}


// -----------------------------------------------------
// LATEST NOTIFICATIONS
// -----------------------------------------------------

$notificationStmt = $conn->prepare(
    "SELECT
        notification_id,
        notification_type,
        title,
        message,
        reference_id,
        reference_type,
        is_read,
        created_at
     FROM notifications
     WHERE recipient_type = 'Admin'
     AND recipient_id = ?
     ORDER BY created_at DESC
     LIMIT 10"
);

if ($notificationStmt) {

    $notificationStmt->bind_param(
        'i',
        $admin_id
    );

    $notificationStmt->execute();

    $notificationResult =
        $notificationStmt->get_result();

    if ($notificationResult) {

        while (
            $notification =
            $notificationResult->fetch_assoc()
        ) {
            $notifications[] =
                $notification;
        }
    }

    $notificationStmt->close();
}


// =====================================================
// RECENT SCRAP REQUESTS
// =====================================================

$recentRequests = [];

$requestStmt = $conn->prepare(
    "SELECT
        a.activity_id,
        a.scrap_type,
        a.scrap_weight,
        a.status,
        a.request_date,
        u.name AS user_name
     FROM activity a
     LEFT JOIN user u
        ON a.user_id = u.user_id
     ORDER BY a.request_date DESC
     LIMIT 5"
);

if ($requestStmt) {

    $requestStmt->execute();

    $requestResult =
        $requestStmt->get_result();

    if ($requestResult) {

        while (
            $request =
            $requestResult->fetch_assoc()
        ) {
            $recentRequests[] =
                $request;
        }
    }

    $requestStmt->close();
}


// =====================================================
// RECENT COLLECTORS
// =====================================================

$recentCollectors = [];

$collectorStmt = $conn->prepare(
    "SELECT
        collector_id,
        name,
        email,
        verification_status,
        availability_status,
        created_at
     FROM scrapcollector
     ORDER BY created_at DESC
     LIMIT 5"
);

if ($collectorStmt) {

    $collectorStmt->execute();

    $collectorResult =
        $collectorStmt->get_result();

    if ($collectorResult) {

        while (
            $collector =
            $collectorResult->fetch_assoc()
        ) {
            $recentCollectors[] =
                $collector;
        }
    }

    $collectorStmt->close();
}

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
        Admin Dashboard | EcoScrap
    </title>


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
            --border: #e6ece8;
            --danger: #dc2626;
            --warning: #d97706;
            --shadow: 0 12px 30px rgba(20, 83, 45, .07);
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


        button {
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
            padding: 30px 38px 50px;
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
            transition: transform .25s ease;
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
            letter-spacing: -.4px;
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
            background: #b9e7c6;
            color: #155d35;
            border-radius: 50%;
            font-weight: 800;
        }


        .admin-details strong {
            display: block;
            font-size: 13px;
        }


        .admin-details span {
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
           PAGE HEADER
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
            font-size: clamp(25px, 3vw, 34px);
            line-height: 1.15;
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
            gap: 12px;
        }


        .date-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 14px;
            color: #557062;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 12px;
        }


        .date-chip i {
            color: var(--primary);
            font-size: 17px;
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
           NOTIFICATIONS
        ================================================= */

        .notification-wrapper {
            position: relative;
        }


        .notification-button {
            position: relative;
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            color: #3d5145;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            font-size: 21px;
            transition: .2s ease;
        }


        .notification-button:hover {
            color: var(--primary);
            border-color: #b7dfc2;
            transform: translateY(-1px);
        }


        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            display: grid;
            place-items: center;
            color: #ffffff;
            background: #ef4444;
            border: 2px solid #ffffff;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
        }


        .notification-dropdown {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            z-index: 100;
            width: 390px;
            display: none;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(15,23,42,.16);
        }


        .notification-dropdown.show {
            display: block;
        }


        .notification-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 17px 18px;
            border-bottom: 1px solid var(--border);
        }


        .notification-header h3 {
            margin: 0;
            font-size: 15px;
        }


        .notification-header span {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 11px;
        }


        .mark-read-btn {
            border: 0;
            color: var(--primary);
            background: transparent;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }


        .notification-list {
            max-height: 410px;
            overflow-y: auto;
        }


        .notification-item {
            position: relative;
            display: flex;
            gap: 12px;
            padding: 15px 18px;
            border-bottom: 1px solid #f0f4f1;
            cursor: pointer;
        }


        .notification-item:hover {
            background: #f8fbf9;
        }


        .notification-item.unread {
            background: #f0fdf4;
        }


        .notification-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: var(--primary-dark);
            background: var(--primary-light);
            border-radius: 11px;
            font-size: 18px;
        }


        .notification-content {
            min-width: 0;
            padding-right: 10px;
        }


        .notification-content strong {
            display: block;
            margin-bottom: 4px;
            color: #203126;
            font-size: 13px;
        }


        .notification-content p {
            margin: 0 0 5px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.45;
        }


        .notification-content small {
            color: #9aa8a0;
            font-size: 10px;
        }


        .unread-dot {
            position: absolute;
            top: 19px;
            right: 15px;
            width: 7px;
            height: 7px;
            background: var(--primary);
            border-radius: 50%;
        }


        .no-notifications {
            padding: 40px 20px;
            color: #9aa8a0;
            text-align: center;
        }


        .no-notifications i {
            display: block;
            margin-bottom: 9px;
            font-size: 30px;
        }


        .no-notifications p {
            margin: 0;
            font-size: 13px;
        }


        /* =================================================
           STATISTICS
        ================================================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }


        .stat-card {
            position: relative;
            min-height: 120px;
            display: flex;
            align-items: center;
            gap: 13px;
            overflow: hidden;
            padding: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 17px;
            box-shadow: var(--shadow);
        }


        .stat-card::after {
            position: absolute;
            right: -22px;
            bottom: -28px;
            width: 85px;
            height: 85px;
            content: "";
            background: var(--primary-light);
            border-radius: 50%;
            opacity: .55;
        }


        .stat-icon {
            position: relative;
            z-index: 1;
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: var(--primary-dark);
            background: var(--primary-light);
            border-radius: 13px;
            font-size: 21px;
        }


        .stat-card span {
            display: block;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }


        .stat-card strong {
            display: block;
            color: #193022;
            font-size: 27px;
            line-height: 1;
        }


        /* =================================================
           TABLE SECTIONS
        ================================================= */

        .dashboard-section {
            margin-bottom: 25px;
            padding: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }


        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
        }


        .section-header h2 {
            margin: 0;
            color: #203126;
            font-size: 17px;
        }


        .section-header p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
        }


        .view-all {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }


        .view-all:hover {
            color: var(--primary);
        }


        .table-container {
            overflow-x: auto;
        }


        table {
            width: 100%;
            min-width: 640px;
            border-collapse: collapse;
        }


        th {
            padding: 12px 14px;
            color: #829188;
            background: #f8faf9;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .65px;
            text-align: left;
            text-transform: uppercase;
        }


        td {
            padding: 16px 14px;
            color: #405247;
            border-bottom: 1px solid #edf2ee;
            font-size: 13px;
        }


        tbody tr:last-child td {
            border-bottom: 0;
        }


        tbody tr:hover {
            background: #fbfdfb;
        }


        td:first-child {
            color: #203126;
            font-weight: 700;
        }


        .status {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: capitalize;
        }


        .status.pending {
            color: #a16207;
            background: #fef3c7;
        }


        .status.approved,
        .status.accepted,
        .status.completed {
            color: #15803d;
            background: #dcfce7;
        }


        .status.rejected,
        .status.cancelled {
            color: #b91c1c;
            background: #fee2e2;
        }


        .status.in-progress {
            color: #0369a1;
            background: #e0f2fe;
        }


        .empty-row {
            padding: 25px !important;
            color: var(--muted) !important;
            text-align: center;
            font-weight: 500 !important;
        }


        /* =================================================
           RESPONSIVE DESIGN
        ================================================= */

        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }


        @media (max-width: 850px) {

            .sidebar {
                transform: translateX(-100%);
            }


            .sidebar.open {
                transform: translateX(0);
            }


            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 22px 18px 40px;
            }


            .mobile-menu {
                display: grid;
            }


            .date-chip {
                display: none;
            }
        }


        @media (max-width: 600px) {

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 11px;
            }


            .stat-card {
                min-height: 105px;
                padding: 14px;
            }


            .stat-icon {
                width: 37px;
                height: 37px;
                font-size: 18px;
            }


            .stat-card strong {
                font-size: 22px;
            }


            .stat-card span {
                font-size: 10px;
            }


            .dashboard-section {
                padding: 17px 14px;
                border-radius: 15px;
            }


            .section-header {
                align-items: flex-start;
            }


            .notification-dropdown {
                position: fixed;
                top: 76px;
                left: 14px;
                right: 14px;
                width: auto;
            }
        }

    </style>

</head>


<body>

<div class="dashboard-layout">


    <?php
    require_once __DIR__ . '/navbar.php';
    ?>


    <main class="main-content">


        <header class="page-header">

            <div class="page-title">

                <h1>
                    Welcome,
                    <span>
                        <?php
                        echo htmlspecialchars(
                            $_SESSION['name'] ?? 'Admin',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </span>
                    👋
                </h1>


                <p>
                    Monitor your recycling network and manage daily activity.
                </p>

            </div>


            <div class="header-actions">

                <div class="date-chip">
                    <i class="ri-calendar-line"></i>
                    <?php echo date('M d, Y'); ?>
                </div>


                <div class="notification-wrapper">

                    <button
                        type="button"
                        class="notification-button"
                        id="notificationButton"
                        aria-label="Notifications"
                    >

                        <i class="ri-notification-3-line"></i>


                        <?php
                        if ($unreadNotifications > 0):
                        ?>

                            <span class="notification-badge">

                                <?php
                                echo $unreadNotifications > 99
                                    ? '99+'
                                    : $unreadNotifications;
                                ?>

                            </span>

                        <?php endif; ?>

                    </button>


                    <div
                        class="notification-dropdown"
                        id="notificationDropdown"
                    >

                        <div class="notification-header">

                            <div>
                                <h3>
                                    Notifications
                                </h3>

                                <span>
                                    <?php
                                    echo $unreadNotifications;
                                    ?>
                                    unread
                                </span>
                            </div>


                            <?php
                            if ($unreadNotifications > 0):
                            ?>

                                <button
                                    type="button"
                                    id="markAllRead"
                                    class="mark-read-btn"
                                >
                                    Mark all as read
                                </button>

                            <?php endif; ?>

                        </div>


                        <div class="notification-list">

                            <?php
                            if (count($notifications) > 0):
                            ?>

                                <?php
                                foreach (
                                    $notifications
                                    as $notification
                                ):
                                ?>

                                    <?php

                                    $icon =
                                        'ri-notification-3-line';

                                    switch (
                                        $notification[
                                            'notification_type'
                                        ]
                                    ) {
                                        case 'collector_registered':
                                            $icon =
                                                'ri-user-add-line';
                                            break;

                                        case 'collector_approved':
                                            $icon =
                                                'ri-user-follow-line';
                                            break;

                                        case 'collector_rejected':
                                            $icon =
                                                'ri-user-unfollow-line';
                                            break;

                                        case 'request_created':
                                            $icon =
                                                'ri-inbox-line';
                                            break;

                                        case 'request_accepted':
                                            $icon =
                                                'ri-checkbox-circle-line';
                                            break;

                                        case 'request_rejected':
                                            $icon =
                                                'ri-close-circle-line';
                                            break;

                                        case 'request_completed':
                                            $icon =
                                                'ri-checkbox-circle-fill';
                                            break;
                                    }

                                    ?>

                                    <div
                                        class="notification-item
                                        <?php
                                        echo $notification['is_read'] == 0
                                            ? 'unread'
                                            : '';
                                        ?>"
                                        data-id="<?php
                                        echo (int)$notification[
                                            'notification_id'
                                        ];
                                        ?>"
                                    >

                                        <div class="notification-icon">

                                            <i
                                                class="<?php
                                                echo $icon;
                                                ?>"
                                            ></i>

                                        </div>


                                        <div class="notification-content">

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $notification['title'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                            </strong>


                                            <p>
                                                <?php
                                                echo htmlspecialchars(
                                                    $notification['message'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                                ?>
                                            </p>


                                            <small>
                                                <?php
                                                echo date(
                                                    'M d, Y • h:i A',
                                                    strtotime(
                                                        $notification[
                                                            'created_at'
                                                        ]
                                                    )
                                                );
                                                ?>
                                            </small>

                                        </div>


                                        <?php
                                        if ($notification['is_read'] == 0):
                                        ?>

                                            <span class="unread-dot"></span>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="no-notifications">

                                    <i
                                        class="ri-notification-off-line"
                                    ></i>

                                    <p>
                                        No notifications yet
                                    </p>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


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


        <section class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="ri-user-line"></i>
                </div>

                <div>
                    <span>Total Users</span>
                    <strong>
                        <?php echo $totalUsers; ?>
                    </strong>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="ri-truck-line"></i>
                </div>

                <div>
                    <span>Total Collectors</span>
                    <strong>
                        <?php echo $totalCollectors; ?>
                    </strong>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="ri-user-search-line"></i>
                </div>

                <div>
                    <span>Pending Collectors</span>
                    <strong>
                        <?php echo $pendingCollectors; ?>
                    </strong>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="ri-recycle-line"></i>
                </div>

                <div>
                    <span>Total Requests</span>
                    <strong>
                        <?php echo $totalRequests; ?>
                    </strong>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="ri-time-line"></i>
                </div>

                <div>
                    <span>Pending Requests</span>
                    <strong>
                        <?php echo $pendingRequests; ?>
                    </strong>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="ri-checkbox-circle-line"></i>
                </div>

                <div>
                    <span>Completed Pickups</span>
                    <strong>
                        <?php echo $completedRequests; ?>
                    </strong>
                </div>

            </div>

        </section>


        <section class="dashboard-section">

            <div class="section-header">

                <div>
                    <h2>
                        Pending Collector Approvals
                    </h2>

                    <p>
                        Collectors waiting for administrator verification.
                    </p>
                </div>


                <a
                    href="approve_collectors.php"
                    class="view-all"
                >
                    View all
                    <i class="ri-arrow-right-line"></i>
                </a>

            </div>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Collector</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Registered</th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php

                        $hasPending = false;

                        foreach (
                            $recentCollectors
                            as $collector
                        ):

                            if (
                                $collector[
                                    'verification_status'
                                ] !== 'Pending'
                            ) {
                                continue;
                            }

                            $hasPending = true;

                        ?>

                            <tr>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $collector['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </td>


                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $collector['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </td>


                                <td>
                                    <span class="status pending">
                                        Pending
                                    </span>
                                </td>


                                <td>
                                    <?php
                                    echo date(
                                        'M d, Y',
                                        strtotime(
                                            $collector['created_at']
                                        )
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>


                        <?php
                        if (!$hasPending):
                        ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="empty-row"
                                >
                                    No pending collector approvals.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <section class="dashboard-section">

            <div class="section-header">

                <div>
                    <h2>
                        Recent Scrap Requests
                    </h2>

                    <p>
                        Latest activity from EcoScrap users.
                    </p>
                </div>


                <a
                    href="manage.php"
                    class="view-all"
                >
                    View all
                    <i class="ri-arrow-right-line"></i>
                </a>

            </div>


            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>User</th>
                            <th>Scrap Type</th>
                            <th>Weight</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php
                        if (count($recentRequests) > 0):
                        ?>

                            <?php
                            foreach (
                                $recentRequests
                                as $request
                            ):
                            ?>

                                <tr>

                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $request['user_name']
                                            ?? 'Unknown',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $request['scrap_type'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                    </td>


                                    <td>
                                        <?php
                                        echo htmlspecialchars(
                                            $request['scrap_weight'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                        ?>
                                        kg
                                    </td>


                                    <td>

                                        <span
                                            class="status <?php
                                            echo strtolower(
                                                str_replace(
                                                    ' ',
                                                    '-',
                                                    $request['status']
                                                )
                                            );
                                            ?>"
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $request['status'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );
                                            ?>
                                        </span>

                                    </td>


                                    <td>
                                        <?php
                                        echo date(
                                            'M d, Y',
                                            strtotime(
                                                $request['request_date']
                                            )
                                        );
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td
                                    colspan="5"
                                    class="empty-row"
                                >
                                    No scrap requests found.
                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const notificationButton =
            document.getElementById(
                'notificationButton'
            );


        const notificationDropdown =
            document.getElementById(
                'notificationDropdown'
            );


        const markAllRead =
            document.getElementById(
                'markAllRead'
            );


        const mobileMenu =
            document.getElementById(
                'mobileMenu'
            );


        const sidebar =
            document.getElementById(
                'sidebar'
            );


        if (
            notificationButton &&
            notificationDropdown
        ) {

            notificationButton.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    notificationDropdown
                        .classList
                        .toggle('show');

                }
            );


            notificationDropdown.addEventListener(
                'click',
                function (event) {
                    event.stopPropagation();
                }
            );


            document.addEventListener(
                'click',
                function () {
                    notificationDropdown
                        .classList
                        .remove('show');
                }
            );

        }


        if (
            mobileMenu &&
            sidebar
        ) {

            mobileMenu.addEventListener(
                'click',
                function () {

                    sidebar
                        .classList
                        .toggle('open');

                }
            );

        }


        if (markAllRead) {

            markAllRead.addEventListener(
                'click',
                function () {

                    fetch(
                        'mark_notifications_read.php',
                        {
                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded'
                            },

                            body:
                                'action=mark_all'
                        }
                    )

                    .then(
                        response =>
                            response.json()
                    )

                    .then(
                        data => {

                            if (data.success) {
                                location.reload();
                            } else {
                                alert(
                                    'Unable to mark notifications as read.'
                                );
                            }

                        }
                    )

                    .catch(
                        error => {
                            console.error(
                                'Notification error:',
                                error
                            );
                        }
                    );

                }
            );

        }

    }
);

</script>

</body>

</html>