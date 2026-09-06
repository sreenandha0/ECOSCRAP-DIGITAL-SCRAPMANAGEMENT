<?php
session_start();

require_once '../includes/db.php';
require_once '../includes/functions.php';

if (
    !isset($_SESSION['collector_id']) ||
    ($_SESSION['role'] ?? '') !== 'Collector'
) {
    redirect('../login.php');
}

$collectorId = (int)$_SESSION['collector_id'];

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Fetch completed pickup records
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        a.activity_id,
        a.scrap_type,
        a.scrap_weight,
        a.amount,
        a.pickup_address,
        a.pickup_pincode,
        a.completed_at,
        a.qr_status,
        u.name AS customer_name,
        u.phone AS customer_phone
    FROM activity AS a
    INNER JOIN user AS u
        ON u.user_id = a.user_id
    WHERE a.collector_id = ?
      AND a.status = 'Completed'
    ORDER BY a.completed_at DESC
");

if (!$stmt) {
    die(
        'Database query preparation failed: ' .
        e($conn->error)
    );
}

$stmt->bind_param(
    'i',
    $collectorId
);

$stmt->execute();

$result = $stmt->get_result();

$pickups = [];
$total_weight = 0.0;
$total_amount = 0.0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pickups[] = $row;

        $total_weight += (float)(
            $row['scrap_weight'] ?? 0
        );

        $total_amount += (float)(
            $row['amount'] ?? 0
        );
    }
}

$stmt->close();

$total_count = count($pickups);

/*
|--------------------------------------------------------------------------
| Collector profile
|--------------------------------------------------------------------------
*/

$collector = [];

$collector_stmt = $conn->prepare("
    SELECT
        collector_id,
        name,
        phone,
        vehicle_no,
        pincode,
        availability_status,
        verification_status,
        completed_pickups
    FROM scrapcollector
    WHERE collector_id = ?
    LIMIT 1
");

if ($collector_stmt) {
    $collector_stmt->bind_param(
        'i',
        $collectorId
    );

    $collector_stmt->execute();

    $collector_result =
        $collector_stmt->get_result();

    if ($collector_result) {
        $collector =
            $collector_result->fetch_assoc() ?: [];
    }

    $collector_stmt->close();
}

$collector_name =
    $collector['name'] ?? 'Collector';

$collector_phone =
    $collector['phone'] ?? 'N/A';

$vehicle_no =
    $collector['vehicle_no'] ?? 'N/A';

$collector_pincode =
    $collector['pincode'] ?? 'N/A';

$availability_status =
    $collector['availability_status'] ?? 'Offline';

$verification_status =
    $collector['verification_status'] ?? 'Pending';

$completed_pickups =
    (int)($collector['completed_pickups'] ?? 0);

$name_parts = preg_split(
    '/\s+/',
    trim($collector_name)
);

$initials = '';

foreach (
    array_slice($name_parts, 0, 2)
    as $part
) {
    $initials .= strtoupper(
        substr($part, 0, 1)
    );
}

$initials = $initials ?: 'C';

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifications = [];
$unread_count = 0;

$notification_stmt = $conn->prepare("
    SELECT
        notification_id,
        title,
        message,
        is_read,
        created_at
    FROM notifications
    WHERE recipient_type = 'Collector'
      AND recipient_id = ?
    ORDER BY created_at DESC
");

if ($notification_stmt) {
    $notification_stmt->bind_param(
        'i',
        $collectorId
    );

    $notification_stmt->execute();

    $notification_result =
        $notification_stmt->get_result();

    if ($notification_result) {
        while (
            $notification =
            $notification_result->fetch_assoc()
        ) {
            $notifications[] = $notification;

            if (
                (int)(
                    $notification['is_read'] ?? 0
                ) === 0
            ) {
                $unread_count++;
            }
        }
    }

    $notification_stmt->close();
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function getScrapIcon(string $type): string
{
    $type = strtolower($type);

    if (strpos($type, 'paper') !== false) {
        return 'ri-newspaper-line';
    }

    if (strpos($type, 'plastic') !== false) {
        return 'ri-cup-line';
    }

    if (strpos($type, 'metal') !== false) {
        return 'ri-hammer-line';
    }

    if (
        strpos($type, 'electronic') !== false ||
        strpos($type, 'e-waste') !== false
    ) {
        return 'ri-computer-line';
    }

    if (strpos($type, 'glass') !== false) {
        return 'ri-goblet-line';
    }

    return 'ri-recycle-line';
}

function formatCompletedDate(?string $date): string
{
    if (!$date) {
        return 'Date unavailable';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return 'Date unavailable';
    }

    return date(
        'd M Y, h:i A',
        $timestamp
    );
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
        Completed Pickups | EcoScrap Collector
    </title>

    <link
        rel="icon"
        type="image/png"
        href="../assets/logo/ecoscrap-logo.png"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"
    >

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
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --eco-light: #82c843;
            --eco-primary: #2e7d32;
            --eco-dark: #004d40;
            --eco-cyan: #00b4d8;

            --page-bg: #f1f5f4;
            --white: #ffffff;
            --text-dark: #0f172a;
            --text: #334155;
            --muted: #64748b;
            --light: #94a3b8;
            --border: #e2e8f0;

            --shadow-sm:
                0 8px 22px rgba(15, 23, 42, 0.06);

            --shadow-md:
                0 18px 40px rgba(15, 23, 42, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            overflow-x: hidden;
            color: var(--text);
            background:
                radial-gradient(
                    rgba(46, 125, 50, 0.07) 1px,
                    transparent 1px
                );
            background-color: var(--page-bg);
            background-size: 16px 16px;
            font-family: 'Inter', sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font-family: inherit;
        }

        button {
            cursor: pointer;
        }

        .app {
            display: flex;
            min-height: 100vh;
        }

       

        /*
        |--------------------------------------------------------------------------
        | Main header
        |--------------------------------------------------------------------------
        */

        .main {
    flex: 1;
    min-width: 0;
    margin-left: 0;
    width: 100%;
}

        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 78px;
            padding: 16px 28px;
            border-bottom: 1px solid #e2e8f0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
        }

        .topbar-title {
            color: var(--text-dark);
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.05em;
        }

        .topbar-subtitle {
            margin-top: 4px;
            color: var(--muted);
            font-size: 10px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 9px;
            border: 1px solid #bde4c2;
            border-radius: 999px;
            color: #166534;
            background: #edfaee;
            font-size: 9px;
            font-weight: 800;
        }

        .notification-wrap {
            position: relative;
        }

        .notification-button {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--muted);
            background: var(--white);
            font-size: 18px;
        }

        .notification-button:hover {
            color: var(--eco-primary);
        }

        .notification-badge {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 7px;
            height: 7px;
            border: 2px solid var(--white);
            border-radius: 50%;
            background: #ef4444;
        }

        .notification-dropdown {
            position: absolute;
            top: 48px;
            right: 0;
            z-index: 100;
            display: none;
            width: 350px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: var(--white);
            box-shadow: var(--shadow-md);
        }

        .notification-dropdown.open {
            display: block;
        }

        .notification-heading {
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }

        .notification-heading h3 {
            color: var(--text-dark);
            font-size: 14px;
        }

        .notification-heading p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 10px;
        }

        .notification-item {
            padding: 13px 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .notification-item.unread {
            background: #eff9ef;
        }

        .notification-item strong {
            color: var(--text-dark);
            font-size: 11px;
        }

        .notification-item p {
            margin-top: 4px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.5;
        }

        .notification-item small {
            display: block;
            margin-top: 6px;
            color: var(--light);
            font-size: 9px;
        }

        .profile-mini {
            display: flex;
            align-items: center;
            gap: 9px;
            padding-left: 12px;
            border-left: 1px solid var(--border);
        }

        .avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 11px;
            color: var(--white);
            background: var(--eco-primary);
            font-size: 12px;
            font-weight: 800;
        }

        .profile-name {
            max-width: 140px;
            overflow: hidden;
            color: var(--text-dark);
            font-size: 11px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .profile-role {
            margin-top: 3px;
            color: var(--muted);
            font-size: 9px;
        }

        .mobile-menu {
            display: none;
            align-items: center;
            justify-content: center;
            width: 37px;
            height: 37px;
            margin-right: 10px;
            border: 0;
            border-radius: 9px;
            color: var(--text-dark);
            background: #eaf6ea;
            font-size: 20px;
        }

        /*
        |--------------------------------------------------------------------------
        | Page content
        |--------------------------------------------------------------------------
        */

        .content {
            max-width: 1500px;
            margin: 0 auto;
            padding: 28px;
        }

        .page-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 21px;
        }

        .eyebrow {
            color: var(--eco-primary);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        .page-title {
            margin-top: 7px;
            color: var(--text-dark);
            font-size: 31px;
            font-weight: 800;
            letter-spacing: -0.05em;
        }

        .page-description {
            max-width: 680px;
            margin-top: 8px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.55;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--muted);
            background: var(--white);
            box-shadow: var(--shadow-sm);
            font-size: 10px;
            font-weight: 800;
        }

        .back-button:hover {
            color: var(--eco-primary);
            background: #f4fbf4;
        }

        /*
        |--------------------------------------------------------------------------
        | Statistics
        |--------------------------------------------------------------------------
        */

        .metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 19px;
        }

        .metric-card {
            position: relative;
            overflow: hidden;
            padding: 17px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: var(--shadow-sm);
        }

        .metric-card::after {
            position: absolute;
            right: -35px;
            bottom: -45px;
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: var(--metric-color);
            opacity: 0.08;
            content: '';
        }

        .metric-green {
            --metric-color: var(--eco-primary);
        }

        .metric-blue {
            --metric-color: var(--eco-cyan);
        }

        .metric-amber {
            --metric-color: #d99014;
        }

        .metric-slate {
            --metric-color: #64748b;
        }

        .metric-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .metric-label {
            color: var(--muted);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.05em;
        }

        .metric-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 37px;
            height: 37px;
            border-radius: 10px;
            color: var(--metric-color);
            background: #f0f8f0;
            font-size: 19px;
        }

        .metric-value {
            position: relative;
            z-index: 1;
            margin-top: 14px;
            color: var(--text-dark);
            font-size: 25px;
            font-weight: 800;
        }

        .metric-value small {
            font-size: 11px;
            font-weight: 800;
        }

        .metric-help {
            position: relative;
            z-index: 1;
            margin-top: 5px;
            color: var(--light);
            font-size: 9px;
        }

        /*
        |--------------------------------------------------------------------------
        | Search and filters
        |--------------------------------------------------------------------------
        */

        .filter-panel {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 14px;
            margin-bottom: 19px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .search-wrapper {
            position: relative;
        }

        .search-wrapper i {
            position: absolute;
            top: 50%;
            left: 13px;
            color: var(--light);
            font-size: 17px;
            transform: translateY(-50%);
        }

        .search-input {
            width: 100%;
            height: 40px;
            padding: 0 13px 0 40px;
            outline: none;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-dark);
            background: #fbfdfc;
            font-size: 11px;
        }

        .search-input:focus {
            border-color: var(--eco-primary);
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.1);
        }

        .category-filters {
            display: flex;
            gap: 6px;
            overflow-x: auto;
        }

        .category-button {
            flex: 0 0 auto;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 9px;
            color: var(--muted);
            background: var(--white);
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .category-button:hover,
        .category-button.active {
            color: var(--white);
            border-color: var(--eco-primary);
            background: var(--eco-primary);
        }

        /*
        |--------------------------------------------------------------------------
        | Completed cards
        |--------------------------------------------------------------------------
        */

        .completed-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fill,
                minmax(290px, 1fr)
            );
            gap: 16px;
        }

        .completed-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 350px;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--white);
            box-shadow: var(--shadow-sm);
            transition: 0.25s ease;
        }

        .completed-card:hover {
            border-color: #bfe2c2;
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        .material-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 9px;
            color: var(--eco-primary);
            background: #ecf8ed;
            font-size: 10px;
            font-weight: 800;
        }

        .completed-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 8px;
            border: 1px solid #bde4c2;
            border-radius: 999px;
            color: #166534;
            background: #edfaee;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .card-info {
            display: grid;
            gap: 12px;
            margin-top: 17px;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .info-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 31px;
            height: 31px;
            border: 1px solid #e6f0e8;
            border-radius: 9px;
            color: var(--eco-primary);
            background: #f8fcf8;
            font-size: 16px;
        }

        .info-label {
            display: block;
            color: var(--light);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .info-value {
            display: block;
            margin-top: 4px;
            overflow: hidden;
            color: var(--text-dark);
            font-size: 11px;
            font-weight: 700;
            line-height: 1.45;
            text-overflow: ellipsis;
        }

        .card-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 16px;
            padding-top: 13px;
            border-top: 1px dashed var(--border);
        }

        .summary-box {
            padding: 10px;
            border-radius: 10px;
            background: #f7faf8;
        }

        .summary-box span {
            display: block;
            color: var(--light);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .summary-box strong {
            display: block;
            margin-top: 5px;
            color: var(--text-dark);
            font-size: 14px;
        }

        .summary-box.amount strong {
            color: var(--eco-primary);
        }

        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 16px;
            padding-top: 13px;
            border-top: 1px dashed var(--border);
        }

        .card-footer small {
            color: var(--light);
            font-size: 9px;
        }

        .details-button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 10px;
            border-radius: 8px;
            color: var(--white);
            background: var(--eco-primary);
            font-size: 9px;
            font-weight: 800;
        }

        .details-button:hover {
            background: #256b29;
        }

        /*
        |--------------------------------------------------------------------------
        | Empty state
        |--------------------------------------------------------------------------
        */

        .empty-state {
            padding: 65px 20px;
            border: 1px dashed #cbd9cf;
            border-radius: 18px;
            text-align: center;
            background: rgba(255, 255, 255, 0.78);
        }

        .empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            border-radius: 20px;
            color: var(--eco-primary);
            background: #eaf7e9;
            font-size: 32px;
        }

        .empty-state h3 {
            color: var(--text-dark);
            font-size: 16px;
        }

        .empty-state p {
            margin-top: 7px;
            color: var(--muted);
            font-size: 11px;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1150px) {
            .metrics {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1000px) {
            .filter-panel {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 850px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
            }

            .mobile-menu {
                display: inline-flex;
            }

            .topbar {
                padding: 14px 20px;
            }

            .content {
                padding: 22px 20px;
            }
        }

        @media (max-width: 650px) {
            .page-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .metrics {
                grid-template-columns: 1fr;
            }

            .topbar-title {
                font-size: 17px;
            }

            .topbar-subtitle,
            .verification-badge,
            .profile-mini > div:last-child {
                display: none;
            }

            .profile-mini {
                padding-left: 3px;
            }

            .notification-dropdown {
                position: fixed;
                top: 68px;
                right: 12px;
                left: 12px;
                width: auto;
            }

            .content {
                padding: 20px 13px;
            }

            .page-title {
                font-size: 27px;
            }
        }

        @media (max-width: 450px) {
            .completed-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="app">

       

        <!-- Main -->
        <main class="main">

            <!-- Header -->
            <header class="topbar">
                <div
                    style="
                        display: flex;
                        align-items: center;
                    "
                >
                    <button
                        type="button"
                        class="mobile-menu"
                        onclick="toggleSidebar()"
                    >
                        <i class="ri-menu-line"></i>
                    </button>

                    <div>
                        <div class="topbar-title">
                            COLLECTOR OVERVIEW
                        </div>

                        <div class="topbar-subtitle">
                            Completed pickup history for
                            <strong>
                                <?php echo e($collector_name); ?>
                            </strong>
                        </div>
                    </div>
                </div>

                <div class="topbar-actions">
                    <span class="verification-badge">
                        <i class="ri-shield-check-line"></i>
                        <?php echo e($verification_status); ?>
                    </span>

                    <div class="notification-wrap">
                        <button
                            type="button"
                            class="notification-button"
                            onclick="toggleNotifications(event)"
                            title="Notifications"
                        >
                            <i class="ri-notification-3-line"></i>

                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"></span>
                            <?php endif; ?>
                        </button>

                        <div
                            id="notificationDropdown"
                            class="notification-dropdown"
                        >
                            <div class="notification-heading">
                                <h3>Notifications</h3>

                                <p>
                                    <?php echo $unread_count; ?>
                                    unread notification(s)
                                </p>
                            </div>

                            <?php if (empty($notifications)): ?>
                                <div
                                    style="
                                        padding: 30px;
                                        color: #64748b;
                                        font-size: 11px;
                                        text-align: center;
                                    "
                                >
                                    No notifications yet.
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <?php
                                    $is_unread =
                                        (int)(
                                            $notification['is_read'] ?? 0
                                        ) === 0;
                                    ?>

                                    <div
                                        class="
                                            notification-item
                                            <?php echo $is_unread ? 'unread' : ''; ?>
                                        "
                                    >
                                        <strong>
                                            <?php echo e(
                                                $notification['title'] ??
                                                'Notification'
                                            ); ?>
                                        </strong>

                                        <p>
                                            <?php echo e(
                                                $notification['message'] ??
                                                ''
                                            ); ?>
                                        </p>

                                        <small>
                                            <?php
                                            echo e(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime(
                                                        $notification['created_at'] ??
                                                        'now'
                                                    )
                                                )
                                            );
                                            ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-mini">
                        <div class="avatar">
                            <?php echo e($initials); ?>
                        </div>

                        <div>
                            <div class="profile-name">
                                <?php echo e($collector_name); ?>
                            </div>

                            <div class="profile-role">
                                <?php echo e($vehicle_no); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <section class="content">

                <!-- Page heading -->
                <div class="page-heading">
                    <div>
                        <div class="eyebrow">
                            Collection History
                        </div>

                        <h1 class="page-title">
                            Completed Pickups
                        </h1>

                        <p class="page-description">
                            Review your completed collection jobs,
                            recovered scrap weight, and total value
                            collected from customers.
                        </p>
                    </div>

                    <a
                        href="dashboard.php"
                        class="back-button"
                    >
                        <i class="ri-arrow-left-line"></i>
                        Back to Dashboard
                    </a>
                </div>

                <!-- Metrics -->
                <div class="metrics">
                    <div class="metric-card metric-green">
                        <div class="metric-top">
                            <span class="metric-label">
                                COMPLETED PICKUPS
                            </span>

                            <span class="metric-icon">
                                <i class="ri-checkbox-circle-line"></i>
                            </span>
                        </div>

                        <div class="metric-value">
                            <?php echo $total_count; ?>
                        </div>

                        <div class="metric-help">
                            Verified collection jobs
                        </div>
                    </div>

                    <div class="metric-card metric-blue">
                        <div class="metric-top">
                            <span class="metric-label">
                                SCRAP RECOVERED
                            </span>

                            <span class="metric-icon">
                                <i class="ri-scales-3-line"></i>
                            </span>
                        </div>

                        <div class="metric-value">
                            <?php echo number_format($total_weight, 2); ?>
                            <small>KG</small>
                        </div>

                        <div class="metric-help">
                            Total collected scrap weight
                        </div>
                    </div>

                    <div class="metric-card metric-amber">
                        <div class="metric-top">
                            <span class="metric-label">
                                VALUE COLLECTED
                            </span>

                            <span class="metric-icon">
                                <i class="ri-money-rupee-circle-line"></i>
                            </span>
                        </div>

                        <div class="metric-value">
                            ₹<?php echo number_format($total_amount, 2); ?>
                        </div>

                        <div class="metric-help">
                            Total amount from completed jobs
                        </div>
                    </div>

                    <div class="metric-card metric-slate">
                        <div class="metric-top">
                            <span class="metric-label">
                                SERVICE PINCODE
                            </span>

                            <span class="metric-icon">
                                <i class="ri-map-pin-line"></i>
                            </span>
                        </div>

                        <div class="metric-value">
                            <?php echo e($collector_pincode); ?>
                        </div>

                        <div class="metric-help">
                            Vehicle:
                            <?php echo e($vehicle_no); ?>
                        </div>
                    </div>
                </div>

                <!-- Search and filter -->
                <div class="filter-panel">
                    <div class="search-wrapper">
                        <i class="ri-search-2-line"></i>

                        <input
                            type="text"
                            id="searchInput"
                            class="search-input"
                            placeholder="
                                Search customer, address, phone,
                                or scrap type...
                            "
                            oninput="filterPickups()"
                        >
                    </div>

                    <div class="category-filters">
                        <button
                            type="button"
                            class="category-button active"
                            onclick="setCategory('all', this)"
                        >
                            All Types
                        </button>

                        <button
                            type="button"
                            class="category-button"
                            onclick="setCategory('paper', this)"
                        >
                            Paper
                        </button>

                        <button
                            type="button"
                            class="category-button"
                            onclick="setCategory('plastic', this)"
                        >
                            Plastic
                        </button>

                        <button
                            type="button"
                            class="category-button"
                            onclick="setCategory('metal', this)"
                        >
                            Metal
                        </button>

                        <button
                            type="button"
                            class="category-button"
                            onclick="setCategory('electronic', this)"
                        >
                            E-Waste
                        </button>
                    </div>
                </div>

                <?php if (!empty($pickups)): ?>
                    <div
                        id="completedGrid"
                        class="completed-grid"
                    >
                        <?php foreach ($pickups as $pickup): ?>
                            <?php
                            $activity_id =
                                (int)(
                                    $pickup['activity_id'] ?? 0
                                );

                            $scrap_type =
                                (string)(
                                    $pickup['scrap_type'] ??
                                    'General Scrap'
                                );

                            $customer_name =
                                (string)(
                                    $pickup['customer_name'] ??
                                    'Unknown Customer'
                                );

                            $customer_phone =
                                (string)(
                                    $pickup['customer_phone'] ??
                                    ''
                                );

                            $pickup_address =
                                (string)(
                                    $pickup['pickup_address'] ??
                                    'Address unavailable'
                                );

                            $pickup_pincode =
                                (string)(
                                    $pickup['pickup_pincode'] ??
                                    'N/A'
                                );

                            $scrap_weight =
                                (float)(
                                    $pickup['scrap_weight'] ??
                                    0
                                );

                            $amount =
                                (float)(
                                    $pickup['amount'] ??
                                    0
                                );

                            $completed_at =
                                $pickup['completed_at'] ??
                                null;

                            $qr_status =
                                (string)(
                                    $pickup['qr_status'] ??
                                    'Used'
                                );

                            $search_text = strtolower(
                                $customer_name . ' ' .
                                $customer_phone . ' ' .
                                $pickup_address . ' ' .
                                $pickup_pincode . ' ' .
                                $scrap_type
                            );
                            ?>

                            <article
                                class="completed-card pickup-item"
                                data-search="<?php echo e($search_text); ?>"
                                data-category="<?php echo e(strtolower($scrap_type)); ?>"
                            >
                                <div>
                                    <div class="card-top">
                                        <span class="material-badge">
                                            <i
                                                class="<?php echo e(
                                                    getScrapIcon($scrap_type)
                                                ); ?>"
                                            ></i>

                                            <?php echo e($scrap_type); ?>
                                        </span>

                                        <span class="completed-badge">
                                            <i class="ri-check-line"></i>
                                            Completed
                                        </span>
                                    </div>

                                    <div class="card-info">
                                        <div class="info-row">
                                            <div class="info-icon">
                                                <i class="ri-user-3-line"></i>
                                            </div>

                                            <div>
                                                <span class="info-label">
                                                    Customer
                                                </span>

                                                <span class="info-value">
                                                    <?php echo e($customer_name); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="info-row">
                                            <div class="info-icon">
                                                <i class="ri-phone-line"></i>
                                            </div>

                                            <div>
                                                <span class="info-label">
                                                    Phone
                                                </span>

                                                <span class="info-value">
                                                    <?php
                                                    echo $customer_phone !== ''
                                                        ? e($customer_phone)
                                                        : 'No phone provided';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="info-row">
                                            <div class="info-icon">
                                                <i class="ri-map-pin-2-line"></i>
                                            </div>

                                            <div>
                                                <span class="info-label">
                                                    Pickup Address
                                                </span>

                                                <span class="info-value">
                                                    <?php echo e($pickup_address); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-summary">
                                        <div class="summary-box">
                                            <span>Weight</span>

                                            <strong>
                                                <?php echo number_format($scrap_weight, 2); ?>
                                                KG
                                            </strong>
                                        </div>

                                        <div class="summary-box amount">
                                            <span>Amount</span>

                                            <strong>
                                                ₹<?php echo number_format($amount, 2); ?>
                                            </strong>
                                        </div>
                                    </div>

                                    <div class="card-summary">
                                        <div class="summary-box">
                                            <span>Completed On</span>

                                            <strong
                                                style="
                                                    font-size: 11px;
                                                "
                                            >
                                                <?php echo e(
                                                    formatCompletedDate(
                                                        $completed_at
                                                    )
                                                ); ?>
                                            </strong>
                                        </div>

                                        <div class="summary-box">
                                            <span>QR Status</span>

                                            <strong
                                                style="
                                                    color: #166534;
                                                    font-size: 11px;
                                                "
                                            >
                                                <?php echo e($qr_status); ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-footer">
                                    <small>
                                        Activity #
                                        <?php echo $activity_id; ?>
                                    </small>

                                    <?php if ($customer_phone !== ''): ?>
                                        <a
                                            href="tel:<?php echo e($customer_phone); ?>"
                                            class="details-button"
                                        >
                                            <i class="ri-phone-line"></i>
                                            Contact
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div
                        id="noSearchResults"
                        class="empty-state"
                        style="display: none; margin-top: 18px;"
                    >
                        <div class="empty-icon">
                            <i class="ri-search-line"></i>
                        </div>

                        <h3>
                            No Matching Pickups
                        </h3>

                        <p>
                            Try another customer name, scrap type,
                            address, or phone number.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="ri-inbox-2-line"></i>
                        </div>

                        <h3>
                            No Completed Pickups Yet
                        </h3>

                        <p>
                            Completed collection jobs will appear here
                            after you verify them.
                        </p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        let selectedCategory = 'all';

        const sidebar =
            document.getElementById('sidebar');

        const notificationDropdown =
            document.getElementById(
                'notificationDropdown'
            );

        function toggleSidebar() {
            sidebar.classList.toggle('open');
        }

        function toggleNotifications(event) {
            event.stopPropagation();

            notificationDropdown.classList.toggle(
                'open'
            );
        }

        document.addEventListener(
            'click',
            function (event) {
                const notificationWrap =
                    document.querySelector(
                        '.notification-wrap'
                    );

                if (
                    notificationWrap &&
                    !notificationWrap.contains(
                        event.target
                    )
                ) {
                    notificationDropdown.classList.remove(
                        'open'
                    );
                }
            }
        );

        function setCategory(category, button) {
            selectedCategory = category;

            document
                .querySelectorAll('.category-button')
                .forEach(function (item) {
                    item.classList.remove('active');
                });

            button.classList.add('active');

            filterPickups();
        }

        function filterPickups() {
            const searchInput =
                document.getElementById(
                    'searchInput'
                );

            const searchValue =
                searchInput.value
                    .toLowerCase()
                    .trim();

            const items =
                document.querySelectorAll(
                    '.pickup-item'
                );

            let visibleCount = 0;

            items.forEach(function (item) {
                const itemSearch =
                    item.dataset.search || '';

                const itemCategory =
                    item.dataset.category || '';

                const searchMatches =
                    itemSearch.includes(
                        searchValue
                    );

                const categoryMatches =
                    selectedCategory === 'all' ||
                    itemCategory.includes(
                        selectedCategory
                    );

                const visible =
                    searchMatches &&
                    categoryMatches;

                item.style.display =
                    visible ? '' : 'none';

                if (visible) {
                    visibleCount++;
                }
            });

            const noSearchResults =
                document.getElementById(
                    'noSearchResults'
                );

            if (noSearchResults) {
                noSearchResults.style.display =
                    visibleCount === 0
                        ? 'block'
                        : 'none';
            }
        }
    </script>
</body>
</html>