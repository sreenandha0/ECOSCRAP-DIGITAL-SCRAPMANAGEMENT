<?php
session_start();

require_once "../includes/db.php";

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Flash Message (optional, if you use it on this page)
|--------------------------------------------------------------------------
*/
$flash = $_SESSION["flash"] ?? "";
unset($_SESSION["flash"]);

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifications = [];
$unreadNotifications = 0;

/*
|--------------------------------------------------------------------------
| Mark All Notifications as Read
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["mark_all_read"])
) {
    $markReadSql = "
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_type = 'User'
          AND recipient_id = ?
          AND is_read = 0
    ";

    $stmt = $conn->prepare($markReadSql);

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: notifications.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch User Notifications
|--------------------------------------------------------------------------
*/

$notificationSql = "
    SELECT
        notification_id,
        notification_type,
        title,
        message,
        reference_id,
        reference_type,
        is_read,
        created_at
    FROM notifications
    WHERE recipient_type = 'User'
      AND recipient_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($notificationSql);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;

        if ((int)$row["is_read"] === 0) {
            $unreadNotifications++;
        }
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Notification Icons
|--------------------------------------------------------------------------
*/

function getNotificationIcon(string $type): string
{
    $type = strtolower(trim($type));

    $icons = [
        "pickup"    => "ri-truck-line",
        "request"   => "ri-file-list-3-line",
        "approved"  => "ri-checkbox-circle-line",
        "assigned"  => "ri-user-follow-line",
        "accepted"  => "ri-check-double-line",
        "rejected"  => "ri-close-circle-line",
        "completed" => "ri-checkbox-circle-fill",
        "verified"  => "ri-shield-check-line",
        "qr"        => "ri-qr-code-line",
        "payment"   => "ri-money-rupee-circle-line",
        "cancelled" => "ri-close-circle-line",
        "info"      => "ri-information-line"
    ];

    return $icons[$type] ?? "ri-notification-3-line";
}

/*
|--------------------------------------------------------------------------
| Notification Color Class
|--------------------------------------------------------------------------
*/

function getNotificationClass(string $type): string
{
    $type = strtolower(trim($type));

    return match ($type) {
        "completed",
        "approved",
        "verified" =>
            "notification-success",

        "pickup",
        "request",
        "assigned",
        "accepted",
        "qr" =>
            "notification-info",

        "payment",
        "info" =>
            "notification-warning",

        "rejected",
        "cancelled" =>
            "notification-danger",

        default =>
            "notification-default"
    };
}

/*
|--------------------------------------------------------------------------
| Notification Date and Time
|--------------------------------------------------------------------------
*/

function formatNotificationDateTime($date): string
{
    if (empty($date)) {
        return "";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return e($date);
    }

    $difference = time() - $timestamp;

    if ($difference < 60) {
        return "Just now";
    }

    if ($difference < 3600) {
        $minutes = floor($difference / 60);

        return $minutes . " minute" .
            ($minutes === 1 ? "" : "s") . " ago";
    }

    if ($difference < 86400) {
        $hours = floor($difference / 3600);

        return $hours . " hour" .
            ($hours === 1 ? "" : "s") . " ago";
    }

    if ($difference < 172800) {
        return "Yesterday";
    }

    return date("d M Y, h:i A", $timestamp);
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

    <title>Notifications | EcoScrap</title>

    <link
        rel="stylesheet"
        href="../assets/css/user.css"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <style>

        /*
        |--------------------------------------------------------------------------
        | Root Colors
        |--------------------------------------------------------------------------
        */

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
            --danger-light: #fee2e2;

            --warning: #d97706;
            --warning-light: #fef3c7;

            --info: #2563eb;
            --info-light: #dbeafe;

            --neutral: #64748b;
            --neutral-light: #f1f5f9;

            --shadow: 0 12px 30px rgba(20, 83, 45, .07);

            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;

            --eco-primary: #16a34a;
            --eco-dark: #0f172a;
            --eco-light: #dcfce7;
        }

        /*
        |--------------------------------------------------------------------------
        | Reset
        |--------------------------------------------------------------------------
        */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
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

        button,
        input,
        textarea,
        select {
            font: inherit;
        }

        /*
        |--------------------------------------------------------------------------
        | Main Content
        |--------------------------------------------------------------------------
        */

        .main-content {
            min-height: 100vh;
            padding: 30px;
        }

        .notifications-page {
            width: min(1100px, 100%);
            margin: 0 auto;
        }

        /*
        |--------------------------------------------------------------------------
        | Page Top (shared style for notifications & pickup request)
        |--------------------------------------------------------------------------
        */

        .page-top {
            max-width: 1520px;
            margin: 0 auto 22px;
        }

        .breadcrumb {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted, var(--muted));
            font-size: 13px;
            margin-bottom: 12px;
        }

        .breadcrumb a {
            color: var(--eco-primary);
            font-weight: 700;
            text-decoration: none;
        }

        .page-title {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: clamp(28px, 3vw, 38px);
            color: var(--eco-dark);
            letter-spacing: -0.7px;
            margin-bottom: 8px;
        }

        .page-desc {
            color: var(--text-muted, var(--muted));
            font-size: 14px;
            line-height: 1.65;
            max-width: 780px;
        }

        /*
        |--------------------------------------------------------------------------
        | Page Alert
        |--------------------------------------------------------------------------
        */

        .user-page-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 1520px;
            margin: 18px auto 0;
            padding: 13px 15px;
            border: 1px solid #bde5c0;
            border-radius: var(--radius-sm);
            background: #effaf0;
            color: #256029;
            font-size: 13px;
            font-weight: 600;
        }

        /*
        |--------------------------------------------------------------------------
        | Impact Card (adapted to notifications page style)
        |--------------------------------------------------------------------------
        */

        .impact-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            min-height: 150px;
            margin: 24px auto 25px;
            padding: 27px 31px;
            border-radius: var(--radius-lg);
            background: linear-gradient(120deg, rgba(0, 77, 64, 0.97), rgba(46, 125, 50, 0.95));
            color: white;
            box-shadow: var(--shadow);
            max-width: 1100px; /* match notifications page width */
        }

        .impact-card::before {
            position: absolute;
            top: -75px;
            right: 13%;
            width: 210px;
            height: 210px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            content: "";
        }

        .impact-card::after {
            position: absolute;
            top: -35px;
            right: 5%;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            content: "";
        }

        .impact-info {
            position: relative;
            z-index: 2;
            max-width: 700px;
        }

        .impact-info .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            color: var(--eco-light);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .impact-info h2 {
            margin-bottom: 7px;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 20px;
        }

        .impact-info p {
            max-width: 560px;
            color: rgba(255,255,255,0.72);
            font-size: 12px;
            line-height: 1.6;
        }

        .impact-progress-wrap {
            position: relative;
            z-index: 2;
            width: 260px;
            flex: 0 0 260px;
        }

        .impact-progress-head {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: rgba(255,255,255,0.75);
            font-size: 11px;
            font-weight: 600;
        }

        .impact-progress-head strong {
            color: white;
        }

        .progress-bar {
            height: 6px;
            background: rgba(255,255,255,0.18);
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar span {
            display: block;
            height: 100%;
            width: 100%;
            background: #86efac;
            border-radius: 999px;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .notifications-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 25px;
            margin-bottom: 25px;
        }

        .page-label {
            margin-bottom: 10px;
            color: var(--primary);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .15em;
        }

        .notifications-header h1 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 850;
            letter-spacing: -.05em;
        }

        .notifications-header p {
            max-width: 680px;
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /*
        |--------------------------------------------------------------------------
        | Mark Read Button
        |--------------------------------------------------------------------------
        */

        .mark-read-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            border: 1px solid #bbf7d0;
            border-radius: 12px;

            padding: 12px 17px;

            background: var(--primary-light);
            color: var(--primary-dark);

            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;

            cursor: pointer;
            transition: all .2s ease;
        }

        .mark-read-btn:hover {
            border-color: var(--primary);
            background: #bbf7d0;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(22, 163, 74, .14);
        }

        .mark-read-btn:active {
            transform: translateY(0);
        }

        /*
        |--------------------------------------------------------------------------
        | Summary Card
        |--------------------------------------------------------------------------
        */

        .notification-summary {
            display: flex;
            align-items: center;
            gap: 14px;

            min-height: 82px;
            margin-bottom: 20px;

            border: 1px solid var(--border);
            border-radius: 19px;

            padding: 17px 20px;

            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .notification-summary-icon {
            width: 48px;
            height: 48px;

            display: grid;
            place-items: center;
            flex: 0 0 48px;

            border-radius: 15px;

            background: var(--primary-light);
            color: var(--primary);

            font-size: 22px;
        }

        .notification-summary span {
            display: block;
            margin-bottom: 3px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 750;
        }

        .notification-summary strong {
            color: var(--text);
            font-size: 24px;
            font-variant-numeric: tabular-nums;
        }

        .unread-summary,
        .read-summary {
            margin-left: auto;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 850;
            white-space: nowrap;
        }

        .unread-summary {
            background: var(--warning-light);
            color: #b45309;
        }

        .read-summary {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        /*
        |--------------------------------------------------------------------------
        | Notifications Container
        |--------------------------------------------------------------------------
        */

        .notifications-list-page {
            overflow: hidden;

            border: 1px solid var(--border);
            border-radius: 21px;

            background: var(--surface);
            box-shadow: var(--shadow);
        }

        /*
        |--------------------------------------------------------------------------
        | Individual Notification
        |--------------------------------------------------------------------------
        */

        .notification-page-item {
            display: flex;
            gap: 17px;

            padding: 23px 25px;

            border-bottom: 1px solid #edf1ee;
            background: var(--surface);

            transition:
                background .2s ease,
                transform .2s ease;
        }

        .notification-page-item:last-child {
            border-bottom: 0;
        }

        .notification-page-item:hover {
            background: #fbfefc;
        }

        .notification-page-item.unread {
            background: #fafffb;
        }

        .notification-page-item.unread:hover {
            background: #f3fcf5;
        }

        /*
        |--------------------------------------------------------------------------
        | Notification Icons
        |--------------------------------------------------------------------------
        */

        .notification-page-icon {
            width: 49px;
            height: 49px;

            display: grid;
            place-items: center;
            flex: 0 0 49px;

            border-radius: 15px;

            font-size: 22px;
        }

        .notification-success .notification-page-icon {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .notification-info .notification-page-icon {
            background: var(--info-light);
            color: var(--info);
        }

        .notification-warning .notification-page-icon {
            background: var(--warning-light);
            color: var(--warning);
        }

        .notification-danger .notification-page-icon {
            background: var(--danger-light);
            color: var(--danger);
        }

        .notification-default .notification-page-icon {
            background: var(--neutral-light);
            color: var(--neutral);
        }

        /*
        |--------------------------------------------------------------------------
        | Notification Content
        |--------------------------------------------------------------------------
        */

        .notification-page-content {
            min-width: 0;
            flex: 1;
        }

        .notification-page-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
        }

        .notification-page-top h3 {
            margin: 0 0 7px;
            color: var(--text);
            font-size: 16px;
            font-weight: 850;
            line-height: 1.35;
        }

        .notification-page-top p {
            max-width: 780px;
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.65;
        }

        /*
        |--------------------------------------------------------------------------
        | Unread Dot
        |--------------------------------------------------------------------------
        */

        .unread-dot {
            width: 9px;
            height: 9px;

            display: block;
            flex: 0 0 9px;

            margin-top: 6px;

            border-radius: 50%;
            background: var(--primary);

            box-shadow:
                0 0 0 4px var(--primary-light);
        }

        /*
        |--------------------------------------------------------------------------
        | Notification Footer
        |--------------------------------------------------------------------------
        */

        .notification-page-footer {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 11px;

            margin-top: 15px;

            color: var(--muted);
            font-size: 12px;
        }

        .notification-page-footer > span:first-child {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .notification-page-footer > span:first-child i {
            color: var(--primary);
            font-size: 15px;
        }

        .notification-type {
            border-radius: 999px;
            padding: 5px 10px;

            background: #f3f6f4;
            color: #5d6b62;

            font-size: 11px;
            font-weight: 800;
        }

        .view-pickup-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;

            color: var(--primary);
            font-size: 12px;
            font-weight: 850;
            text-decoration: none;
            transition: color .2s ease;
        }

        .view-pickup-link:hover {
            color: var(--primary-dark);
        }

        .view-pickup-link i {
            transition: transform .2s ease;
        }

        .view-pickup-link:hover i {
            transform: translateX(3px);
        }

        /*
        |--------------------------------------------------------------------------
        | Empty State
        |--------------------------------------------------------------------------
        */

        .notifications-empty-state {
            display: flex;
            align-items: center;
            flex-direction: column;

            padding: 80px 25px;

            text-align: center;
        }

        .empty-notification-icon {
            width: 84px;
            height: 84px;

            display: grid;
            place-items: center;

            margin-bottom: 21px;

            border-radius: 26px;

            background: var(--primary-light);
            color: var(--primary);

            font-size: 39px;
        }

        .notifications-empty-state h2 {
            margin: 0 0 8px;
            color: var(--text);
            font-size: 23px;
            font-weight: 850;
        }

        .notifications-empty-state p {
            max-width: 430px;
            margin: 0 0 25px;

            color: var(--muted);
            font-size: 14px;
            line-height: 1.65;
        }

        .request-pickup-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            border-radius: 12px;
            padding: 13px 18px;

            background: var(--primary);
            color: #ffffff;

            font-size: 13px;
            font-weight: 800;
            text-decoration: none;

            box-shadow: 0 9px 19px rgba(22, 163, 74, .2);
            transition: all .2s ease;
        }

        .request-pickup-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 12px 22px rgba(22, 163, 74, .25);
        }

        /*
        |--------------------------------------------------------------------------
        | Focus Accessibility
        |--------------------------------------------------------------------------
        */

        .mark-read-btn:focus-visible,
        .request-pickup-btn:focus-visible,
        .view-pickup-link:focus-visible {
            outline: 3px solid rgba(22, 163, 74, .28);
            outline-offset: 3px;
        }

        /*
        |--------------------------------------------------------------------------
        | Tablet
        |--------------------------------------------------------------------------
        */

        @media (max-width: 850px) {
            .main-content {
                padding: 25px 20px;
            }

            .notifications-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .mark-read-btn {
                width: 100%;
            }

            .impact-card {
                flex-direction: column;
                align-items: flex-start;
                min-height: auto;
                padding: 22px;
            }

            .impact-progress-wrap {
                width: 100%;
                flex: none;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */

        @media (max-width: 560px) {
            .main-content {
                padding: 20px 14px;
            }

            .notifications-header {
                margin-bottom: 20px;
            }

            .notifications-header h1 {
                font-size: 30px;
            }

            .notifications-header p {
                font-size: 13px;
            }

            .notification-summary {
                align-items: flex-start;
                min-height: 75px;
                padding: 14px;
            }

            .notification-summary-icon {
                width: 42px;
                height: 42px;
                flex-basis: 42px;
                border-radius: 13px;
                font-size: 20px;
            }

            .notification-summary strong {
                font-size: 21px;
            }

            .unread-summary,
            .read-summary {
                margin-left: auto;
                padding: 6px 9px;
                font-size: 10px;
            }

            .notification-page-item {
                gap: 12px;
                padding: 18px 15px;
            }

            .notification-page-icon {
                width: 41px;
                height: 41px;
                flex-basis: 41px;
                border-radius: 12px;
                font-size: 19px;
            }

            .notification-page-top {
                gap: 10px;
            }

            .notification-page-top h3 {
                margin-bottom: 5px;
                font-size: 14px;
            }

            .notification-page-top p {
                font-size: 13px;
                line-height: 1.55;
            }

            .notification-page-footer {
                gap: 8px;
                margin-top: 12px;
                font-size: 11px;
            }

            .notification-type {
                padding: 4px 8px;
                font-size: 10px;
            }

            .view-pickup-link {
                font-size: 11px;
            }

            .notifications-empty-state {
                padding: 65px 20px;
            }

            .empty-notification-icon {
                width: 72px;
                height: 72px;
                border-radius: 22px;
                font-size: 33px;
            }

            .notifications-empty-state h2 {
                font-size: 20px;
            }

            .notifications-empty-state p {
                font-size: 13px;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Very Small Screens
        |--------------------------------------------------------------------------
        */

        @media (max-width: 380px) {
            .notification-summary {
                gap: 9px;
            }

            .notification-summary span {
                font-size: 10px;
            }

            .unread-summary,
            .read-summary {
                font-size: 9px;
            }

            .notification-page-item {
                padding: 16px 12px;
            }
        }

    </style>

</head>

<body>

    <!--
    |--------------------------------------------------------------------------
    | Your Existing Sidebar/Header
    |--------------------------------------------------------------------------
    -->

    <main class="main-content">

        <div class="notifications-page">

            <!-- Page Top / Breadcrumb (matching your Create Pickup Request page) -->
            <div class="page-top">
                <div class="breadcrumb">
                    <a href="dashboard.php"><i class="ri-arrow-left-line"></i> Back</a>
                    <span>/</span>
                    <span>Notifications</span>
                </div>

                <h1 class="page-title">Notifications</h1>
                <p class="page-desc">
                    Stay updated with your scrap pickup requests and account activity.
                    All notifications are listed below.
                </p>
            </div>

            <?php if (!empty($flash)): ?>
                <div class="user-page-alert">
                    <i class="ri-information-line"></i>
                    <span><?= e($flash) ?></span>
                </div>
            <?php endif; ?>

            <!-- Impact Card (styled to match notification page width & theme) -->
            <section class="impact-card">
                <div class="impact-info">
                    <p class="eyebrow"><i class="ri-recycle-line"></i> Ready to recycle?</p>
                    <h2>Schedule a convenient scrap pickup</h2>
                    <p>Help keep recyclable materials out of landfills by submitting a pickup request in just a few steps.</p>
                </div>

                <div class="impact-progress-wrap">
                    <div class="impact-progress-head">
                        <strong>Request</strong>
                        <span>Ready</span>
                    </div>
                    <div class="progress-bar"><span></span></div>
                </div>
            </section>

            <!-- Notifications Header (original style, now below impact card) -->
            <div class="notifications-header">

                

                <?php if ($unreadNotifications > 0): ?>

                    <form method="POST">

                        <button
                            type="submit"
                            name="mark_all_read"
                            value="1"
                            class="mark-read-btn"
                        >
                            <i class="ri-check-double-line"></i>
                            Mark all as read
                        </button>

                    </form>

                <?php endif; ?>

            </div>

            <div class="notification-summary">

                <div class="notification-summary-icon">
                    <i class="ri-notification-3-line"></i>
                </div>

                <div>

                    <span>
                        Total Notifications
                    </span>

                    <strong>
                        <?= number_format(count($notifications)) ?>
                    </strong>

                </div>

                <?php if ($unreadNotifications > 0): ?>

                    <div class="unread-summary">
                        <?= number_format($unreadNotifications) ?>
                        unread
                    </div>

                <?php else: ?>

                    <div class="read-summary">
                        All caught up
                    </div>

                <?php endif; ?>

            </div>

            <div class="notifications-list-page">

                <?php if (empty($notifications)): ?>

                    <div class="notifications-empty-state">

                        <div class="empty-notification-icon">
                            <i class="ri-notification-off-line"></i>
                        </div>

                        <h2>
                            No notifications yet
                        </h2>

                        <p>
                            Updates about your scrap pickup requests
                            will appear here.
                        </p>

                        <a
                            href="request_pickup.php"
                            class="request-pickup-btn"
                        >
                            <i class="ri-add-line"></i>
                            Request Scrap Pickup
                        </a>

                    </div>

                <?php else: ?>

                    <?php foreach ($notifications as $notification): ?>

                        <?php
                        $notificationType =
                            $notification["notification_type"] ?? "";

                        $isUnread =
                            (int)$notification["is_read"] === 0;

                        $notificationClass =
                            getNotificationClass($notificationType);

                        $referenceType =
                            $notification["reference_type"] ?? "";
                        ?>

                        <div class="
                            notification-page-item
                            <?= $isUnread ? "unread" : "" ?>
                            <?= e($notificationClass) ?>
                        ">

                            <div class="notification-page-icon">

                                <i class="<?= e(
                                    getNotificationIcon(
                                        $notificationType
                                    )
                                ) ?>"></i>

                            </div>

                            <div class="notification-page-content">

                                <div class="notification-page-top">

                                    <div>

                                        <h3>
                                            <?= e(
                                                $notification["title"]
                                            ) ?>
                                        </h3>

                                        <p>
                                            <?= e(
                                                $notification["message"]
                                            ) ?>
                                        </p>

                                    </div>

                                    <?php if ($isUnread): ?>

                                        <span
                                            class="unread-dot"
                                            title="Unread notification"
                                        ></span>

                                    <?php endif; ?>

                                </div>

                                <div class="notification-page-footer">

                                    <span>
                                        <i class="ri-time-line"></i>

                                        <?= e(
                                            formatNotificationDateTime(
                                                $notification["created_at"]
                                            )
                                        ) ?>
                                    </span>

                                    <span class="notification-type">
                                        <?= e(
                                            ucwords(
                                                str_replace(
                                                    "_",
                                                    " ",
                                                    $notificationType
                                                )
                                            )
                                        ) ?>
                                    </span>

                                    <?php if (
                                        !empty(
                                            $notification["reference_id"]
                                        )
                                        &&
                                        in_array(
                                            $referenceType,
                                            [
                                                "activity",
                                                "pickup"
                                            ],
                                            true
                                        )
                                    ): ?>

                                        <a
                                            href="track_status.php?id=<?= (int)$notification['reference_id']; ?>"
                                            class="view-pickup-link"
                                        >
                                            View Pickup
                                            <i class="ri-arrow-right-line"></i>
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </main>

</body>
</html>