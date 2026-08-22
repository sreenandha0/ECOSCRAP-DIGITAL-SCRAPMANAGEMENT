<?php
session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

date_default_timezone_set("Asia/Kolkata");

if (
    !isset($_SESSION["role"], $_SESSION["user_id"], $_SESSION["name"]) ||
    $_SESSION["role"] !== "User"
) {
    header("Location: ../login.php");
    exit;
}

$user_id = filter_var(
    $_SESSION["user_id"],
    FILTER_VALIDATE_INT,
    [
        "options" => [
            "min_range" => 1
        ]
    ]
);

$name = trim((string) $_SESSION["name"]);

if ($user_id === false) {
    $_SESSION = [];
    session_destroy();

    header("Location: ../login.php");
    exit;
}

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function formatDateValue($date): string
{
    if (empty($date)) {
        return "Not scheduled";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return e($date);
    }

    return date("d M Y", $timestamp);
}

function formatTimeValue($time): string
{
    if (empty($time)) {
        return "Flexible time";
    }

    $timestamp = strtotime($time);

    if (!$timestamp) {
        return e($time);
    }

    return date("h:i A", $timestamp);
}

function getStatusClass($status): string
{
    $classes = [
        "Pending" => "status-pending",
        "Approved" => "status-approved",
        "Assigned" => "status-assigned",
        "Accepted" => "status-accepted",
        "In Progress" => "status-progress",
        "Verified" => "status-verified",
        "Completed" => "status-completed",
        "Cancelled" => "status-cancelled",
        "Rejected" => "status-rejected"
    ];

    return $classes[$status] ?? "status-default";
}

function getStatusIcon($status): string
{
    $icons = [
        "Pending" => "ri-time-line",
        "Approved" => "ri-check-line",
        "Assigned" => "ri-truck-line",
        "Accepted" => "ri-user-follow-line",
        "In Progress" => "ri-loader-4-line",
        "Verified" => "ri-shield-check-line",
        "Completed" => "ri-checkbox-circle-line",
        "Cancelled" => "ri-close-circle-line",
        "Rejected" => "ri-error-warning-line"
    ];

    return $icons[$status] ?? "ri-information-line";
}

/*
|--------------------------------------------------------------------------
| Greeting
|--------------------------------------------------------------------------
*/

$currentHour = (int) date("H");

if ($currentHour >= 5 && $currentHour < 12) {
    $greeting = "Good Morning";
} elseif ($currentHour >= 12 && $currentHour < 17) {
    $greeting = "Good Afternoon";
} elseif ($currentHour >= 17 && $currentHour < 21) {
    $greeting = "Good Evening";
} else {
    $greeting = "Good Night";
}

/*
|--------------------------------------------------------------------------
| Profile image
|--------------------------------------------------------------------------
*/

$profileImage = null;

$userSql = "
    SELECT profile_image
    FROM `user`
    WHERE user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($userSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$userResult = $stmt->get_result();

if ($userRow = $userResult->fetch_assoc()) {
    $profileImage = $userRow["profile_image"];
}

$stmt->close();

$profileImageUrl = "../assets/images/default-avatar.png";

if (!empty($profileImage)) {
    $safeProfileImage = basename($profileImage);
    $profilePath = __DIR__ . "/../uploads/profile/" . $safeProfileImage;

    if (is_file($profilePath)) {
        $profileImageUrl =
            "../uploads/profile/" . rawurlencode($safeProfileImage);
    }
}

$firstLetter = strtoupper(substr($name, 0, 1));

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifications = [];
$unreadNotifications = 0;

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
    LIMIT 5
";

$stmt = $conn->prepare($notificationSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$notificationResult = $stmt->get_result();

while ($row = $notificationResult->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();

$unreadNotificationSql = "
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE recipient_type = 'User'
      AND recipient_id = ?
      AND is_read = 0
";

$stmt = $conn->prepare($unreadNotificationSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$unreadResult = $stmt->get_result();

if ($row = $unreadResult->fetch_assoc()) {
    $unreadNotifications = (int) $row["unread_count"];
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$total = 0;
$completed = 0;
$pending = 0;
$assigned = 0;
$approved = 0;
$accepted = 0;

$statsSql = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Completed'), 0) AS completed,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'Assigned'), 0) AS assigned,
        COALESCE(SUM(status = 'Approved'), 0) AS approved,
        COALESCE(SUM(status = 'Accepted'), 0) AS accepted
    FROM activity
    WHERE user_id = ?
";

$stmt = $conn->prepare($statsSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$statsResult = $stmt->get_result();

if ($row = $statsResult->fetch_assoc()) {
    $total = (int) $row["total"];
    $completed = (int) $row["completed"];
    $pending = (int) $row["pending"];
    $assigned = (int) $row["assigned"];
    $approved = (int) $row["approved"];
    $accepted = (int) $row["accepted"];
}

$stmt->close();

$completionRate = $total > 0
    ? min(100, round(($completed / $total) * 100))
    : 0;

/*
|--------------------------------------------------------------------------
| Recent pickups
|--------------------------------------------------------------------------
*/

$recentPickups = [];

$recentSql = "
    SELECT
        activity_id,
        scrap_type,
        scrap_weight,
        pickup_address,
        pickup_pincode,
        preferred_pickup_date,
        pickup_time,
        request_date,
        status,
        qr_status,
        amount,
        completed_at
    FROM activity
    WHERE user_id = ?
    ORDER BY activity_id DESC
    LIMIT 6
";

$stmt = $conn->prepare($recentSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$recentResult = $stmt->get_result();

while ($row = $recentResult->fetch_assoc()) {
    $recentPickups[] = $row;
}

$stmt->close();

/*
|--------------------------------------------------------------------------
| Active pickup
|--------------------------------------------------------------------------
*/

$activePickup = null;

$activeSql = "
    SELECT
        activity_id,
        scrap_type,
        scrap_weight,
        pickup_address,
        pickup_pincode,
        preferred_pickup_date,
        pickup_time,
        request_date,
        status,
        qr_status,
        qr_expiry,
        amount,
        remarks,
        completed_at
    FROM activity
    WHERE user_id = ?
      AND status NOT IN ('Completed', 'Cancelled', 'Rejected')
    ORDER BY activity_id DESC
    LIMIT 1
";

$stmt = $conn->prepare($activeSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$activeResult = $stmt->get_result();
$activePickup = $activeResult->fetch_assoc() ?: null;

$stmt->close();

$activeStatus = $activePickup["status"] ?? "Pending";

$progressStages = [
    "Pending",
    "Approved",
    "Assigned",
    "Accepted",
    "In Progress",
    "Verified",
    "Completed"
];

$activeStageIndex = array_search(
    $activeStatus,
    $progressStages,
    true
);

if ($activeStageIndex === false) {
    $activeStageIndex = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2e7d32">

    <title>Dashboard | EcoScrap</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"
    >

    <style>
        :root {
            --eco-light: #82c843;
            --eco-primary: #2e7d32;
            --eco-primary-dark: #236128;
            --eco-dark: #004d40;
            --eco-accent: #00b4d8;

            --body-bg: #f1f5f4;
            --text-main: #16342f;
            --text-muted: #64748b;
            --text-soft: #94a3b8;
            --border: #e6eeeb;
            --white: #ffffff;

            --shadow-sm: 0 8px 25px rgba(22, 52, 47, 0.06);
            --shadow-md: 0 18px 45px rgba(22, 52, 47, 0.10);

            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;

            --spring: cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(
                    circle at 90% 0%,
                    rgba(130, 200, 67, 0.14),
                    transparent 30%
                ),
                var(--body-bg);
            color: var(--text-main);
            font-family: "DM Sans", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            border: 0;
            cursor: pointer;
            font: inherit;
        }

        .app-shell {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */

        .sidebar {
            position: fixed;
            z-index: 50;
            top: 0;
            bottom: 0;
            left: 0;
            display: flex;
            width: 260px;
            flex-direction: column;
            padding: 25px 17px;
            background: var(--eco-dark);
            color: white;
            transition: transform 0.35s var(--spring);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 5px 12px 35px;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.6px;
        }

        .brand-mark {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 13px;
            background: linear-gradient(
                135deg,
                var(--eco-light),
                #42a845
            );
            color: white;
            font-size: 23px;
            box-shadow: 0 8px 18px rgba(130, 200, 67, 0.25);
        }

        .brand span {
            color: var(--eco-light);
        }

        .side-label {
            padding: 0 13px 12px;
            color: rgba(255,255,255,0.45);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .side-nav {
            display: grid;
            gap: 7px;
        }

        .side-nav a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            border-radius: 13px;
            color: rgba(255,255,255,0.68);
            font-size: 14px;
            font-weight: 600;
            transition: 0.25s ease;
        }

        .side-nav a i {
            font-size: 20px;
        }

        .side-nav a:hover,
        .side-nav a.active {
            background: rgba(255,255,255,0.12);
            color: white;
        }

        .side-nav a.active {
            box-shadow: inset 3px 0 var(--eco-light);
        }

        .sidebar-bottom {
            margin-top: auto;
        }

        .help-card {
            position: relative;
            overflow: hidden;
            margin: 12px 5px 18px;
            padding: 18px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 17px;
            background: rgba(255,255,255,0.08);
        }

        .help-card::after {
            position: absolute;
            right: -20px;
            bottom: -35px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(130, 200, 67, 0.15);
            content: "";
        }

        .help-card i {
            display: block;
            margin-bottom: 10px;
            color: var(--eco-light);
            font-size: 23px;
        }

        .help-card strong {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .help-card p {
            color: rgba(255,255,255,0.55);
            font-size: 11px;
            line-height: 1.5;
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 14px;
            border-radius: 13px;
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            font-weight: 600;
        }

        .logout-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .logout-link i {
            font-size: 19px;
        }

        /* Main */

        .main {
            width: calc(100% - 260px);
            margin-left: 260px;
        }

        .topbar {
            position: sticky;
            z-index: 30;
            top: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 76px;
            padding: 15px 5%;
            border-bottom: 1px solid rgba(230, 238, 235, 0.7);
            background: rgba(241, 245, 244, 0.86);
            backdrop-filter: blur(15px);
        }

        .mobile-menu {
            display: none;
            width: 39px;
            height: 39px;
            border-radius: 11px;
            background: white;
            color: var(--eco-dark);
            font-size: 21px;
        }

        .page-title {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 19px;
        }

        .notification-wrap {
            position: relative;
        }

        .icon-button {
            position: relative;
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: white;
            color: var(--text-main);
            font-size: 21px;
            transition: 0.25s ease;
        }

        .icon-button:hover {
            border-color: var(--eco-light);
            color: var(--eco-primary);
            transform: translateY(-2px);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            display: grid;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            place-items: center;
            border: 2px solid var(--body-bg);
            border-radius: 20px;
            background: #ef5350;
            color: white;
            font-size: 9px;
            font-weight: 800;
        }

        .profile-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-left: 15px;
            border-left: 1px solid var(--border);
        }

        .profile-mini img,
        .profile-fallback {
            width: 39px;
            height: 39px;
            border-radius: 12px;
            object-fit: cover;
        }

        .profile-fallback {
            display: grid;
            place-items: center;
            background: linear-gradient(
                135deg,
                var(--eco-primary),
                var(--eco-light)
            );
            color: white;
            font-size: 15px;
            font-weight: 800;
        }

        .profile-mini-text strong {
            display: block;
            max-width: 130px;
            overflow: hidden;
            color: var(--text-main);
            font-size: 13px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .profile-mini-text small {
            color: var(--text-muted);
            font-size: 11px;
        }

        /* Notification dropdown */

        .notification-dropdown {
            position: absolute;
            top: 52px;
            right: 0;
            display: none;
            width: 350px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: white;
            box-shadow: var(--shadow-md);
        }

        .notification-dropdown.show {
            display: block;
            animation: dropIn 0.2s ease;
        }

        @keyframes dropIn {
            from {
                opacity: 0;
                transform: translateY(-7px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 17px 18px;
            border-bottom: 1px solid var(--border);
        }

        .notification-head strong {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 14px;
        }

        .notification-head a {
            color: var(--eco-primary);
            font-size: 11px;
            font-weight: 700;
        }

        .notification-item {
            display: flex;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid #f0f4f2;
        }

        .notification-item.unread {
            background: #f3fbef;
        }

        .notification-icon {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 10px;
            background: #e8f5e1;
            color: var(--eco-primary);
            font-size: 18px;
        }

        .notification-content strong {
            display: block;
            margin-bottom: 3px;
            font-size: 12px;
        }

        .notification-content p {
            color: var(--text-muted);
            font-size: 11px;
            line-height: 1.4;
        }

        .notification-content time {
            display: block;
            margin-top: 5px;
            color: var(--text-soft);
            font-size: 10px;
        }

        .empty-notification {
            padding: 27px 18px;
            color: var(--text-muted);
            font-size: 12px;
            text-align: center;
        }

        /* Content */

        .content {
            max-width: 1520px;
            margin: auto;
            padding: 42px 5% 50px;
        }

        .welcome {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 25px;
            margin-bottom: 31px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            color: var(--eco-primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .eyebrow i {
            font-size: 16px;
        }

        h1,
        h2,
        h3 {
            font-family: "Plus Jakarta Sans", sans-serif;
        }

        h1 {
            margin-bottom: 8px;
            color: var(--eco-dark);
            font-size: clamp(26px, 3vw, 38px);
            letter-spacing: -1.4px;
        }

        .welcome-text {
            max-width: 580px;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .primary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 19px;
            border-radius: 12px;
            background: var(--eco-primary);
            color: white;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 9px 18px rgba(46, 125, 50, 0.2);
            transition: 0.25s ease;
        }

        .primary-button:hover {
            background: var(--eco-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 13px 25px rgba(46, 125, 50, 0.25);
        }

        .primary-button i {
            font-size: 18px;
        }

        /* Stat cards */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            padding: 21px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: white;
            box-shadow: var(--shadow-sm);
            transition: 0.3s var(--spring);
        }

        .stat-card::after {
            position: absolute;
            right: -26px;
            bottom: -44px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(130, 200, 67, 0.1);
            content: "";
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .stat-icon {
            display: grid;
            width: 41px;
            height: 41px;
            place-items: center;
            border-radius: 12px;
            background: #edf8e9;
            color: var(--eco-primary);
            font-size: 21px;
        }

        .stat-change {
            color: var(--eco-primary);
            font-size: 10px;
            font-weight: 700;
        }

        .stat-card h3 {
            margin-bottom: 4px;
            color: var(--eco-dark);
            font-size: 27px;
            letter-spacing: -1px;
        }

        .stat-card p {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
        }

        /* Main grid */

        .main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(280px, 0.8fr);
            gap: 22px;
            margin-bottom: 25px;
        }

        .panel {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: white;
            box-shadow: var(--shadow-sm);
        }

        .active-panel {
            min-height: 315px;
            padding: 27px;
        }

        .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 23px;
        }

        .panel-heading h2 {
            margin-bottom: 5px;
            color: var(--eco-dark);
            font-size: 17px;
            letter-spacing: -0.5px;
        }

        .panel-heading p {
            color: var(--text-muted);
            font-size: 12px;
        }

        .view-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--eco-primary);
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .active-details {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
            margin-bottom: 29px;
        }

        .detail-box {
            padding: 14px;
            border-radius: 13px;
            background: #f6f9f8;
        }

        .detail-box span {
            display: block;
            margin-bottom: 5px;
            color: var(--text-soft);
            font-size: 10px;
            font-weight: 600;
        }

        .detail-box strong {
            display: block;
            overflow: hidden;
            color: var(--text-main);
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tracker {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
        }

        .tracker-line {
            position: absolute;
            top: 15px;
            right: 6%;
            left: 6%;
            height: 3px;
            background: #e3ebe7;
        }

        .tracker-progress {
            position: absolute;
            top: 15px;
            left: 6%;
            height: 3px;
            background: var(--eco-light);
            transition: width 0.5s ease;
        }

        .tracker-step {
            position: relative;
            z-index: 2;
            display: grid;
            justify-items: center;
            gap: 8px;
            width: 15%;
            color: var(--text-soft);
            font-size: 10px;
            font-weight: 600;
            text-align: center;
        }

        .tracker-dot {
            display: grid;
            width: 31px;
            height: 31px;
            place-items: center;
            border: 3px solid white;
            border-radius: 50%;
            background: #e3ebe7;
            color: transparent;
            box-shadow: 0 0 0 1px #e3ebe7;
            font-size: 14px;
        }

        .tracker-step.done,
        .tracker-step.current {
            color: var(--eco-primary);
        }

        .tracker-step.done .tracker-dot,
        .tracker-step.current .tracker-dot {
            background: var(--eco-light);
            color: white;
            box-shadow: 0 0 0 1px var(--eco-light);
        }

        .tracker-step.current .tracker-dot {
            box-shadow:
                0 0 0 1px var(--eco-light),
                0 0 0 6px rgba(130, 200, 67, 0.17);
        }

        .no-active {
            display: grid;
            min-height: 220px;
            place-items: center;
            padding: 20px;
            text-align: center;
        }

        .no-active-icon {
            display: grid;
            width: 67px;
            height: 67px;
            margin: 0 auto 15px;
            place-items: center;
            border-radius: 20px;
            background: #edf8e9;
            color: var(--eco-primary);
            font-size: 31px;
        }

        .no-active h3 {
            margin-bottom: 7px;
            font-size: 16px;
        }

        .no-active p {
            margin-bottom: 16px;
            color: var(--text-muted);
            font-size: 12px;
        }

        /* Quick actions */

        .quick-panel {
            padding: 27px;
        }

        .quick-list {
            display: grid;
            gap: 11px;
        }

        .quick-action {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px;
            border: 1px solid #edf2ef;
            border-radius: 13px;
            transition: 0.25s ease;
        }

        .quick-action:hover {
            border-color: #cce8be;
            background: #f6fbf4;
            transform: translateX(4px);
        }

        .quick-action i {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 11px;
            background: #edf8e9;
            color: var(--eco-primary);
            font-size: 19px;
        }

        .quick-action strong {
            display: block;
            margin-bottom: 2px;
            font-size: 12px;
        }

        .quick-action span {
            display: block;
            color: var(--text-muted);
            font-size: 10px;
        }

        .quick-action .arrow {
            width: auto;
            height: auto;
            margin-left: auto;
            background: transparent;
            color: var(--text-soft);
            font-size: 16px;
        }

        /* Impact */

        .impact-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            min-height: 150px;
            margin-bottom: 25px;
            padding: 27px 31px;
            border-radius: var(--radius-lg);
            background:
                linear-gradient(
                    120deg,
                    rgba(0, 77, 64, 0.97),
                    rgba(46, 125, 50, 0.95)
                );
            color: white;
            box-shadow: var(--shadow-md);
        }

        .impact-card::before {
            position: absolute;
            top: -75px;
            right: 13%;
            width: 210px;
            height: 210px;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 50%;
            content: "";
        }

        .impact-card::after {
            position: absolute;
            top: -35px;
            right: 5%;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 50%;
            content: "";
        }

        .impact-info {
            position: relative;
            z-index: 2;
        }

        .impact-info .eyebrow {
            color: var(--eco-light);
        }

        .impact-info h2 {
            margin-bottom: 7px;
            font-size: 20px;
        }

        .impact-info p {
            max-width: 500px;
            color: rgba(255,255,255,0.68);
            font-size: 12px;
        }

        .impact-progress-wrap {
            position: relative;
            z-index: 2;
            width: 220px;
            flex: 0 0 220px;
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
            height: 9px;
            overflow: hidden;
            border-radius: 9px;
            background: rgba(255,255,255,0.18);
        }

        .progress-bar span {
            display: block;
            width: <?= (int) $completionRate ?>%;
            height: 100%;
            border-radius: inherit;
            background: var(--eco-light);
        }

        /* Recent table */

        .recent-panel {
            overflow: hidden;
        }

        .recent-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 25px 27px 19px;
        }

        .recent-header h2 {
            margin-bottom: 5px;
            color: var(--eco-dark);
            font-size: 17px;
        }

        .recent-header p {
            color: var(--text-muted);
            font-size: 12px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        th {
            padding: 12px 27px;
            background: #f7faf9;
            color: var(--text-soft);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.7px;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            padding: 16px 27px;
            border-top: 1px solid #eef3f1;
            color: var(--text-muted);
            font-size: 12px;
            vertical-align: middle;
        }

        td strong {
            color: var(--text-main);
            font-size: 12px;
        }

        .request-code {
            color: var(--eco-primary);
            font-size: 11px;
            font-weight: 800;
        }

        .scrap-name {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .scrap-icon {
            display: grid;
            width: 31px;
            height: 31px;
            place-items: center;
            border-radius: 9px;
            background: #edf8e9;
            color: var(--eco-primary);
            font-size: 16px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-pill i {
            font-size: 13px;
        }

        .status-pending {
            background: #fff6df;
            color: #b77900;
        }

        .status-approved {
            background: #eaf7ff;
            color: #16719b;
        }

        .status-assigned {
            background: #edeaff;
            color: #5c4eb2;
        }

        .status-accepted,
        .status-progress {
            background: #fff0e4;
            color: #be671c;
        }

        .status-verified,
        .status-completed {
            background: #e8f7e9;
            color: #2e7d32;
        }

        .status-cancelled,
        .status-rejected {
            background: #ffebeb;
            color: #c62828;
        }

        .status-default {
            background: #eef2f1;
            color: var(--text-muted);
        }

        .table-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--eco-primary);
            font-size: 11px;
            font-weight: 800;
        }

        .empty-table {
            padding: 45px 20px;
            color: var(--text-muted);
            text-align: center;
        }

        .empty-table i {
            display: block;
            margin-bottom: 9px;
            color: var(--eco-light);
            font-size: 32px;
        }

        /* Footer */

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 25px 5%;
            color: var(--text-soft);
            font-size: 11px;
        }

        .footer-links {
            display: flex;
            gap: 18px;
        }

        .footer a:hover {
            color: var(--eco-primary);
        }

        /* Overlay */

        .sidebar-overlay {
            position: fixed;
            z-index: 40;
            inset: 0;
            display: none;
            background: rgba(0, 30, 25, 0.4);
        }

        /* Responsive */

        @media (max-width: 1180px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .stat-card:last-child {
                grid-column: span 1;
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay.show {
                display: block;
            }

            .main {
                width: 100%;
                margin-left: 0;
            }

            .mobile-menu {
                display: block;
            }

            .topbar {
                gap: 12px;
            }

            .page-title {
                margin-right: auto;
            }

            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .content {
                padding: 28px 18px 35px;
            }

            .topbar {
                padding: 12px 18px;
            }

            .profile-mini-text {
                display: none;
            }

            .welcome {
                display: block;
            }

            .welcome .primary-button {
                width: 100%;
                margin-top: 18px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card h3 {
                font-size: 23px;
            }

            .active-panel,
            .quick-panel {
                padding: 20px;
            }

            .active-details {
                grid-template-columns: 1fr 1fr;
            }

            .active-details .detail-box:last-child {
                grid-column: span 2;
            }

            .tracker {
                min-width: 620px;
            }

            .tracker-wrap {
                overflow-x: auto;
                padding: 10px 0 5px;
            }

            .impact-card {
                display: block;
                padding: 24px;
            }

            .impact-progress-wrap {
                width: 100%;
                margin-top: 22px;
            }

            .recent-header {
                padding: 21px 20px 17px;
            }

            th,
            td {
                padding-right: 20px;
                padding-left: 20px;
            }

            .footer {
                display: block;
                padding: 23px 18px;
                line-height: 2;
            }

            .footer-links {
                margin-top: 8px;
            }

            .notification-dropdown {
                position: fixed;
                top: 67px;
                right: 15px;
                left: 15px;
                width: auto;
            }
        }
    </style>
</head>

<body>

<div class="app-shell">

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="brand">
            <span class="brand-mark">
                <i class="ri-leaf-line"></i>
            </span>
            Eco<span>Scrap</span>
        </a>

        <div class="side-label">Main menu</div>

        <nav class="side-nav">
            <a href="dashboard.php" class="active">
                <i class="ri-dashboard-line"></i>
                <span>Dashboard</span>
            </a>

            <a href="create_request.php">
                <i class="ri-add-circle-line"></i>
                <span>Request Pickup</span>
            </a>

            <a href="track_status.php">
                <i class="ri-route-line"></i>
                <span>Track Status</span>
            </a>

            <a href="history.php">
                <i class="ri-history-line"></i>
                <span>Collection History</span>
            </a>
        </nav>

        <div class="side-label" style="margin-top: 28px;">
            Account
        </div>

        <nav class="side-nav">
            <a href="profile.php">
                <i class="ri-user-settings-line"></i>
                <span>My Profile</span>
            </a>

            <a href="../help.php">
                <i class="ri-question-line"></i>
                <span>Help Centre</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="help-card">
                <i class="ri-recycle-line"></i>
                <strong>Keep recycling</strong>
                <p>
                    Every responsible collection helps create a cleaner tomorrow.
                </p>
            </div>

            <a href="../logout.php" class="logout-link">
                <i class="ri-logout-box-r-line"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main">

        <header class="topbar">
            <button
                type="button"
                class="mobile-menu"
                id="mobileMenu"
                aria-label="Open menu"
            >
                <i class="ri-menu-line"></i>
            </button>

            <div class="page-title">
                User Dashboard
            </div>

            <div class="top-actions">

                <div class="notification-wrap">
                    <button
                        type="button"
                        class="icon-button"
                        id="notificationButton"
                        aria-label="Notifications"
                        aria-expanded="false"
                    >
                        <i class="ri-notification-3-line"></i>

                        <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-badge">
                                <?= $unreadNotifications > 9 ? "9+" : $unreadNotifications ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <div
                        class="notification-dropdown"
                        id="notificationDropdown"
                    >
                        <div class="notification-head">
                            <strong>Notifications</strong>
                            <a href="notifications.php">View all</a>
                        </div>

                        <?php if (empty($notifications)): ?>
                            <div class="empty-notification">
                                <i class="ri-notification-off-line"></i>
                                No notifications yet.
                            </div>
                        <?php else: ?>

                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?= ((int) $notification["is_read"] === 0) ? "unread" : "" ?>">
                                    <div class="notification-icon">
                                        <i class="ri-notification-3-line"></i>
                                    </div>

                                    <div class="notification-content">
                                        <strong>
                                            <?= e($notification["title"]) ?>
                                        </strong>

                                        <p>
                                            <?= e($notification["message"]) ?>
                                        </p>

                                        <time>
                                            <?= e(formatDateValue($notification["created_at"])) ?>
                                        </time>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                        <?php endif; ?>
                    </div>
                </div>

                <a href="profile.php" class="profile-mini">
                    <?php if ($profileImageUrl !== "../assets/images/default-avatar.png"): ?>
                        <img
                            src="<?= e($profileImageUrl) ?>"
                            alt="Profile photo"
                        >
                    <?php else: ?>
                        <span class="profile-fallback">
                            <?= e($firstLetter) ?>
                        </span>
                    <?php endif; ?>

                    <span class="profile-mini-text">
                        <strong><?= e($name) ?></strong>
                        <small>EcoScrap User</small>
                    </span>
                </a>

            </div>
        </header>

        <section class="content">

            <section class="welcome">
                <div>
                    <div class="eyebrow">
                        <i class="ri-sun-line"></i>
                        <?= e($greeting) ?>, <?= e($name) ?>
                    </div>

                    <h1>Make every scrap count.</h1>

                    <p class="welcome-text">
                        Manage your scrap pickups, track collections, and
                        contribute to a cleaner and greener environment.
                    </p>
                </div>

                <a href="create_request.php" class="primary-button">
                    <i class="ri-add-line"></i>
                    Request a Pickup
                </a>
            </section>

            <section class="stats-grid">

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">
                            <i class="ri-file-list-3-line"></i>
                        </span>

                        <span class="stat-change">
                            All time
                        </span>
                    </div>

                    <h3><?= $total ?></h3>
                    <p>Total requests</p>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">
                            <i class="ri-time-line"></i>
                        </span>

                        <span class="stat-change">
                            Active
                        </span>
                    </div>

                    <h3><?= $pending ?></h3>
                    <p>Pending requests</p>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">
                            <i class="ri-truck-line"></i>
                        </span>

                        <span class="stat-change">
                            On route
                        </span>
                    </div>

                    <h3><?= $assigned ?></h3>
                    <p>Assigned pickups</p>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">
                            <i class="ri-user-follow-line"></i>
                        </span>

                        <span class="stat-change">
                            Accepted
                        </span>
                    </div>

                    <h3><?= $accepted ?></h3>
                    <p>Accepted pickups</p>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-icon">
                            <i class="ri-checkbox-circle-line"></i>
                        </span>

                        <span class="stat-change">
                            Success
                        </span>
                    </div>

                    <h3><?= $completed ?></h3>
                    <p>Completed pickups</p>
                </div>

            </section>

            <section class="main-grid">

                <div class="panel active-panel">

                    <?php if ($activePickup): ?>

                        <div class="panel-heading">
                            <div>
                                <h2>Current pickup status</h2>
                                <p>
                                    Request #SCR-<?= str_pad((string) $activePickup["activity_id"], 5, "0", STR_PAD_LEFT) ?>
                                </p>
                            </div>

                            <span class="status-pill <?= e(getStatusClass($activeStatus)) ?>">
                                <i class="<?= e(getStatusIcon($activeStatus)) ?>"></i>
                                <?= e($activeStatus) ?>
                            </span>
                        </div>

                        <div class="active-details">
                            <div class="detail-box">
                                <span>Scrap type</span>
                                <strong><?= e($activePickup["scrap_type"]) ?></strong>
                            </div>

                            <div class="detail-box">
                                <span>Weight</span>
                                <strong>
                                    <?= e($activePickup["scrap_weight"]) ?> kg
                                </strong>
                            </div>

                            <div class="detail-box">
                                <span>Pickup date</span>
                                <strong>
                                    <?= e(formatDateValue($activePickup["preferred_pickup_date"])) ?>
                                </strong>
                            </div>

                            <div class="detail-box">
                                <span>Pickup time</span>
                                <strong>
                                    <?= e(formatTimeValue($activePickup["pickup_time"])) ?>
                                </strong>
                            </div>

                            <div class="detail-box" style="grid-column: span 2;">
                                <span>Pickup location</span>
                                <strong>
                                    <?= e($activePickup["pickup_address"]) ?>,
                                    <?= e($activePickup["pickup_pincode"]) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="tracker-wrap">
                            <div class="tracker">

                                <div class="tracker-line"></div>

                                <div
                                    class="tracker-progress"
                                    style="
                                        width:
                                        <?= $activeStageIndex > 0
                                            ? (($activeStageIndex / (count($progressStages) - 1)) * 88)
                                            : 0
                                        ?>%;
                                    "
                                ></div>

                                <?php foreach ($progressStages as $index => $stage): ?>
                                    <div class="tracker-step
                                        <?= $index < $activeStageIndex ? "done" : "" ?>
                                        <?= $index === $activeStageIndex ? "current" : "" ?>
                                    ">
                                        <span class="tracker-dot">
                                            <i class="ri-check-line"></i>
                                        </span>

                                        <span>
                                            <?= e($stage) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>

                            </div>
                        </div>

                    <?php else: ?>

                        <div class="no-active">
                            <div>
                                <div class="no-active-icon">
                                    <i class="ri-inbox-line"></i>
                                </div>

                                <h3>No active pickup</h3>

                                <p>
                                    You do not have any pickup requests in progress.
                                </p>

                                <a href="create_request.php" class="primary-button">
                                    <i class="ri-add-line"></i>
                                    Create Request
                                </a>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="panel quick-panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Quick actions</h2>
                            <p>Everything you need, one click away.</p>
                        </div>
                    </div>

                    <div class="quick-list">

                        <a href="create_request.php" class="quick-action">
                            <i class="ri-add-line"></i>

                            <span>
                                <strong>New pickup request</strong>
                                <span>Schedule a scrap collection</span>
                            </span>

                            <i class="ri-arrow-right-s-line arrow"></i>
                        </a>

                        <a href="track_status.php" class="quick-action">
                            <i class="ri-route-line"></i>

                            <span>
                                <strong>Track my request</strong>
                                <span>Check your current status</span>
                            </span>

                            <i class="ri-arrow-right-s-line arrow"></i>
                        </a>

                        <a href="history.php" class="quick-action">
                            <i class="ri-history-line"></i>

                            <span>
                                <strong>Collection history</strong>
                                <span>Review previous pickups</span>
                            </span>

                            <i class="ri-arrow-right-s-line arrow"></i>
                        </a>

                        <a href="profile.php" class="quick-action">
                            <i class="ri-user-settings-line"></i>

                            <span>
                                <strong>Manage profile</strong>
                                <span>Update your account details</span>
                            </span>

                            <i class="ri-arrow-right-s-line arrow"></i>
                        </a>

                    </div>
                </div>

            </section>

            <section class="impact-card">
                <div class="impact-info">
                    <div class="eyebrow">
                        <i class="ri-leaf-line"></i>
                        Your Eco Impact
                    </div>

                    <h2>Small actions create a cleaner future.</h2>

                    <p>
                        You have completed <?= $completed ?> pickup request(s).
                        Keep recycling responsibly and make a lasting difference.
                    </p>
                </div>

                <div class="impact-progress-wrap">
                    <div class="impact-progress-head">
                        <span>Completion rate</span>
                        <strong><?= $completionRate ?>%</strong>
                    </div>

                    <div
                        class="progress-bar"
                        aria-label="Completion rate: <?= $completionRate ?>%"
                    >
                        <span></span>
                    </div>
                </div>
            </section>

            <section class="panel recent-panel">

                <div class="recent-header">
                    <div>
                        <h2>Recent pickup requests</h2>
                        <p>Your latest scrap collection activity.</p>
                    </div>

                    <a href="history.php" class="view-link">
                        View history
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>

                <div class="table-wrap">

                    <?php if (empty($recentPickups)): ?>

                        <div class="empty-table">
                            <i class="ri-file-list-3-line"></i>
                            <p>No pickup requests found.</p>
                        </div>

                    <?php else: ?>

                        <table>
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>Scrap type</th>
                                    <th>Pickup date</th>
                                    <th>Weight</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($recentPickups as $pickup): ?>

                                    <?php
                                    $status = $pickup["status"];
                                    $requestCode = "SCR-" . str_pad(
                                        (string) $pickup["activity_id"],
                                        5,
                                        "0",
                                        STR_PAD_LEFT
                                    );
                                    ?>

                                    <tr>
                                        <td>
                                            <span class="request-code">
                                                #<?= e($requestCode) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="scrap-name">
                                                <span class="scrap-icon">
                                                    <i class="ri-recycle-line"></i>
                                                </span>

                                                <strong>
                                                    <?= e($pickup["scrap_type"]) ?>
                                                </strong>
                                            </div>
                                        </td>

                                        <td>
                                            <?= e(formatDateValue($pickup["preferred_pickup_date"])) ?>
                                        </td>

                                        <td>
                                            <?= e($pickup["scrap_weight"]) ?> kg
                                        </td>

                                        <td>
                                            <span class="status-pill <?= e(getStatusClass($status)) ?>">
                                                <i class="<?= e(getStatusIcon($status)) ?>"></i>
                                                <?= e($status) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <a
                                                href="track_status.php?id=<?= (int) $pickup["activity_id"] ?>"
                                                class="table-action"
                                            >
                                                View
                                                <i class="ri-arrow-right-s-line"></i>
                                            </a>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php endif; ?>

                </div>
            </section>

        </section>

        <footer class="footer">
            <span>
                © <?= date("Y") ?> EcoScrap. Building a cleaner tomorrow.
            </span>

            <span class="footer-links">
                <a href="../privacy.php">Privacy</a>
                <a href="../help.php">Help</a>
                <a href="../contact.php">Contact</a>
            </span>
        </footer>

    </main>
</div>

<script>
    const notificationButton =
        document.getElementById("notificationButton");

    const notificationDropdown =
        document.getElementById("notificationDropdown");

    const mobileMenu =
        document.getElementById("mobileMenu");

    const sidebar =
        document.getElementById("sidebar");

    const sidebarOverlay =
        document.getElementById("sidebarOverlay");

    notificationButton.addEventListener("click", function (event) {
        event.stopPropagation();

        const isOpen =
            notificationDropdown.classList.toggle("show");

        notificationButton.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );
    });

    document.addEventListener("click", function (event) {
        if (
            !notificationDropdown.contains(event.target) &&
            !notificationButton.contains(event.target)
        ) {
            notificationDropdown.classList.remove("show");
            notificationButton.setAttribute(
                "aria-expanded",
                "false"
            );
        }
    });

    mobileMenu.addEventListener("click", function () {
        sidebar.classList.add("open");
        sidebarOverlay.classList.add("show");
    });

    sidebarOverlay.addEventListener("click", function () {
        sidebar.classList.remove("open");
        sidebarOverlay.classList.remove("show");
    });
</script>

</body>
</html>