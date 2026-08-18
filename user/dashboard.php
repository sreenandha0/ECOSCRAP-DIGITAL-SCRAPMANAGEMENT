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

if (
    !isset($_SESSION["role"], $_SESSION["user_id"], $_SESSION["name"]) ||
    $_SESSION["role"] !== "User"
) {
    header("Location: ../login.php");
    exit;
}

$user_id = filter_var($_SESSION["user_id"], FILTER_VALIDATE_INT);
$name = trim((string) $_SESSION["name"]);

if (!$user_id) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| User Information
|--------------------------------------------------------------------------
*/

$profileImage = null;

$userSql = "
    SELECT profile_image
    FROM `user`
    WHERE user_id = ?
    LIMIT 1
";

if ($stmt = $conn->prepare($userSql)) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($userRow = $result->fetch_assoc()) {
        $profileImage = $userRow["profile_image"];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Your notifications table contains:
|
| notification_id
| recipient_type
| recipient_id
| notification_type
| title
| message
| reference_id
| reference_type
| is_read
| created_at
|
|--------------------------------------------------------------------------
*/

$notifications = [];
$unreadNotifications = 0;


/*
|--------------------------------------------------------------------------
| Get Latest 5 Notifications
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
    LIMIT 5
";

if ($stmt = $conn->prepare($notificationSql)) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Count Unread Notifications
|--------------------------------------------------------------------------
*/

$unreadNotificationSql = "
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE recipient_type = 'User'
      AND recipient_id = ?
      AND is_read = 0
";

if ($stmt = $conn->prepare($unreadNotificationSql)) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $unreadNotifications = (int) $row["unread_count"];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
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

if ($stmt = $conn->prepare($statsSql)) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        $total = (int) $row["total"];
        $completed = (int) $row["completed"];
        $pending = (int) $row["pending"];
        $assigned = (int) $row["assigned"];
        $approved = (int) $row["approved"];
        $accepted = (int) $row["accepted"];
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Recent Pickups
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

if ($stmt = $conn->prepare($recentSql)) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recentPickups[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Current Active Pickup
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

if ($stmt = $conn->prepare($activeSql)) {

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    $activePickup = $result->fetch_assoc() ?: null;

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Environmental Data
|--------------------------------------------------------------------------
*/

$co2Saved = null;


/*
|--------------------------------------------------------------------------
| Status Styling
|--------------------------------------------------------------------------
*/

$statusClasses = [

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


$statusIcons = [

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


$progressStages = [

    "Pending",

    "Approved",

    "Assigned",

    "Accepted",

    "Completed"
];


$firstLetter = strtoupper(
    substr($name, 0, 1)
);


/*
|--------------------------------------------------------------------------
| Active Pickup Status
|--------------------------------------------------------------------------
*/

$activeStatus = $activePickup["status"] ?? "Pending";

$activeStatusClass =
    $statusClasses[$activeStatus]
    ?? "status-default";


$activeStageIndex = array_search(
    $activeStatus,
    $progressStages,
    true
);


if ($activeStageIndex === false) {

    if (
        $activeStatus === "In Progress"
        ||
        $activeStatus === "Verified"
    ) {

        $activeStageIndex = 3;

    } else {

        $activeStageIndex = 0;
    }
}


/*
|--------------------------------------------------------------------------
| Impact Progress
|--------------------------------------------------------------------------
*/

$impactProgress = $total > 0
    ? min(
        100,
        round(
            ($completed / $total) * 100
        )
    )
    : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <title>EcoScrap Dashboard</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">

    <link
        rel="stylesheet"
        href="../assets/css/userdas.css">

        <style>
        </style>
    </head>

<body>

    <button
        class="mobile-menu-button"
        id="mobileMenuButton"
        type="button"
        aria-label="Open navigation"
        aria-controls="dashboardSidebar"
        aria-expanded="false">
        <i class="ri-menu-line"></i>
    </button>

    <div
        class="sidebar-overlay"
        id="sidebarOverlay"
        aria-hidden="true">
    </div>

    <aside
        class="dashboard-sidebar"
        id="dashboardSidebar">

        <div>
           <a
    href="dashboard.php"
    class="sidebar-brand"
    aria-label="EcoScrap dashboard">

    <img
        src="../assets/logo/ecoscrap-logo.png"
        alt="EcoScrap"
        class="sidebar-logo">
</a>

            <nav
                class="sidebar-navigation"
                aria-label="Main navigation">

                <a
                    href="dashboard.php"
                    class="sidebar-link active"
                    aria-current="page">
                    <i class="ri-dashboard-line"></i>
                    <span>Dashboard</span>
                </a>

                <a
                    href="create_request.php"
                    class="sidebar-link">
                    <i class="ri-add-box-line"></i>
                    <span>New Pickup</span>
                </a>

                <a
                    href="history.php"
                    class="sidebar-link">
                    <i class="ri-truck-line"></i>
                    <span>My Pickups</span>
                </a>

                <a
                    href="history.php"
                    class="sidebar-link">
                    <i class="ri-history-line"></i>
                    <span>Collection History</span>
                </a>

                <a
                    href="qr.php"
                    class="sidebar-link">
                    <i class="ri-qr-code-line"></i>
                    <span>QR Verification</span>
                </a>

                <a
                    href="impact.php"
                    class="sidebar-link">
                    <i class="ri-leaf-line"></i>
                    <span>Environmental Impact</span>
                </a>

                <a
                    href="notifications.php"
                    class="sidebar-link">
                    <i class="ri-notification-3-line"></i>
                    <span>Notifications</span>
                </a>

                <a
                    href="profile.php"
                    class="sidebar-link">
                    <i class="ri-user-line"></i>
                    <span>Profile</span>
                </a>

                <a
                    href="settings.php"
                    class="sidebar-link">
                    <i class="ri-settings-3-line"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-bottom">
            <a
                href="help.php"
                class="sidebar-link">
                <i class="ri-question-line"></i>
                <span>Help &amp; Support</span>
            </a>

            <a
                href="../logout.php"
                class="sidebar-link logout-link">
                <i class="ri-logout-box-r-line"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-container">

            <header
                class="dashboard-header reveal-item"
                style="--delay: 40ms;">

                <div>
                    <span class="header-eyebrow">
                        EcoScrap User Dashboard
                    </span>

                    <h1>
                        Good morning, <?= e($name); ?>
                        <span aria-hidden="true">👋</span>
                    </h1>

                    <p>
                        Ready to make a greener impact today?
                    </p>
                </div>

                <div class="header-actions">
                    <button
                        type="button"
                        class="header-icon-button"
                        aria-label="Search">
                        <i class="ri-search-line"></i>
                    </button>

                    <div class="notification-wrapper">

    <button
        type="button"
        class="header-icon-button notification-button"
        id="notificationBtn"
        aria-label="Notifications"
        aria-expanded="false"
    >
        <i class="ri-notification-3-line"></i>

        <?php if ($unreadNotifications > 0): ?>
            <span class="notification-count">
                <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
            </span>
        <?php endif; ?>

    </button>


    <!-- Notification Dropdown -->
    <div
        class="notification-dropdown"
        id="notificationDropdown"
    >

        <div class="notification-dropdown-header">

            <div>
                <h3>Notifications</h3>

                <span>
                    <?= $unreadNotifications ?> unread
                </span>
            </div>

        </div>


        <div class="notification-dropdown-list">

            <?php if (empty($notifications)): ?>

                <div class="notification-empty">

                    <i class="ri-notification-off-line"></i>

                    <p>No notifications yet</p>

                </div>

            <?php else: ?>

                <?php foreach ($notifications as $notification): ?>

                    <div
                        class="notification-item
                        <?= ((int)$notification['is_read'] === 0)
                            ? 'notification-unread'
                            : '' ?>"
                    >

                        <div class="notification-item-icon">

                            <?php

                            switch ($notification['notification_type']) {

                                case 'new_scrap_request':
                                    echo '<i class="ri-recycle-line"></i>';
                                    break;

                                case 'collector_accepted':
                                    echo '<i class="ri-truck-line"></i>';
                                    break;

                                case 'collector_rejected':
                                    echo '<i class="ri-close-circle-line"></i>';
                                    break;

                                case 'pickup_completed':
                                    echo '<i class="ri-checkbox-circle-line"></i>';
                                    break;

                                case 'pickup_cancelled':
                                    echo '<i class="ri-close-circle-line"></i>';
                                    break;

                                default:
                                    echo '<i class="ri-information-line"></i>';
                                    break;
                            }

                            ?>

                        </div>


                        <div class="notification-item-content">

                            <strong>
                                <?= e($notification['title']) ?>
                            </strong>

                            <p>
                                <?= e($notification['message']) ?>
                            </p>

                            <small>
                                <?= date(
                                    'd M Y, h:i A',
                                    strtotime($notification['created_at'])
                                ) ?>
                            </small>

                        </div>


                        <?php if ((int)$notification['is_read'] === 0): ?>

                            <span class="notification-unread-dot"></span>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

                    <button
                        type="button"
                        class="profile-button"
                        aria-label="User profile"
                        aria-expanded="false">

                        <?php if (!empty($profileImage)): ?>
                            <img
                                src="<?= e($profileImage); ?>"
                                alt=""
                                class="profile-avatar">
                        <?php else: ?>
                            <span
                                class="profile-avatar profile-initial">
                                <?= e($firstLetter); ?>
                            </span>
                        <?php endif; ?>

                        <span class="profile-name">
                            <?= e($name); ?>
                        </span>

                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                </div>
            </header>

            <section
                class="welcome-card bg-pattern reveal-item"
                style="--delay: 90ms;">

                <div class="welcome-content">
                    <span class="welcome-kicker">
                        <i class="ri-leaf-line"></i>
                        Sustainable living starts here
                    </span>

                    <h2>
                        Make your scrap count.
                    </h2>

                    <p>
                        Schedule a pickup and turn recyclable waste into
                        measurable environmental impact.
                    </p>

                    <div class="welcome-actions">
                        <a
                            href="create_request.php"
                            class="eco-button eco-button-primary">

                            <i class="ri-add-line"></i>
                            Schedule Pickup
                        </a>

                        <a
                            href="history.php"
                            class="eco-button eco-button-ghost">

                            View My Pickups
                            <i class="ri-arrow-right-line"></i>
                        </a>
                    </div>
                </div>

                <div
                    class="welcome-art"
                    aria-hidden="true">

                    <div class="orbit orbit-one"></div>
                    <div class="orbit orbit-two"></div>

                    <div class="recycle-symbol">
                        <i class="ri-recycle-line"></i>
                    </div>

                    <span class="art-dot art-dot-one"></span>
                    <span class="art-dot art-dot-two"></span>
                    <span class="art-dot art-dot-three"></span>
                </div>
            </section>

            <section
                class="stats-grid"
                aria-label="Pickup statistics">

                <article
                    class="stat-card glass-card motion-hover reveal-item"
                    style="--delay: 140ms;">

                    <div class="stat-card-heading">
                        <span>Total Pickups</span>

                        <i class="ri-inbox-archive-line stat-icon icon-cyan"></i>
                    </div>

                    <strong
                        class="stat-value count-up"
                        data-value="<?= $total; ?>">
                        0
                    </strong>

                    <p>All your pickup requests</p>
                </article>

                <article
                    class="stat-card glass-card motion-hover reveal-item"
                    style="--delay: 190ms;">

                    <div class="stat-card-heading">
                        <span>Completed Collections</span>

                        <i class="ri-checkbox-circle-line stat-icon icon-primary"></i>
                    </div>

                    <strong
                        class="stat-value count-up"
                        data-value="<?= $completed; ?>">
                        0
                    </strong>

                    <p>Successfully collected</p>
                </article>

                <article
                    class="stat-card glass-card motion-hover reveal-item"
                    style="--delay: 240ms;">

                    <div class="stat-card-heading">
                        <span>Pending Pickups</span>

                        <i class="ri-time-line stat-icon icon-light"></i>
                    </div>

                    <strong
                        class="stat-value count-up"
                        data-value="<?= $pending; ?>">
                        0
                    </strong>

                    <p>Waiting for collection</p>
                </article>

                <article
                    class="stat-card glass-card motion-hover reveal-item"
                    style="--delay: 290ms;">

                    <div class="stat-card-heading">
                        <span>CO₂ Saved</span>

                        <i class="ri-leaf-line stat-icon icon-dark"></i>
                    </div>

                    <strong class="stat-value">
                        <?= $co2Saved !== null
                            ? e($co2Saved) . " kg"
                            : "—"; ?>
                    </strong>

                    <p>
                        <?= $co2Saved !== null
                            ? "Calculated environmental impact"
                            : "No CO₂ data available"; ?>
                    </p>
                </article>
            </section>

            <?php if ($activePickup): ?>
                <section
                    class="active-pickup-card glass-card reveal-item"
                    style="--delay: 340ms;"
                    aria-labelledby="activePickupHeading">

                    <div class="section-heading-row">
                        <div>
                            <span class="section-eyebrow">
                                Current pickup
                            </span>

                            <h2 id="activePickupHeading">
                                Pickup #ECO-<?= e($activePickup["activity_id"]); ?>
                            </h2>
                        </div>

                        <span
                            class="pickup-status <?= e($activeStatusClass); ?>">

                            <span class="status-pulse"></span>
                            <?= e($activeStatus); ?>
                        </span>
                    </div>

                    <div
                        class="pickup-progress"
                        aria-label="Pickup progress">

                        <?php foreach ($progressStages as $index => $stage): ?>
                            <?php
                            $stageDone = $index <= $activeStageIndex;
                            $stageCurrent = $index === $activeStageIndex;
                            ?>

                            <div
                                class="
                                    progress-step
                                    <?= $stageDone ? "done" : ""; ?>
                                    <?= $stageCurrent ? "current" : ""; ?>
                                ">

                                <span class="progress-circle">
                                    <?php if ($stageDone): ?>
                                        <i
                                            class="ri-check-line"
                                            aria-hidden="true">
                                        </i>
                                    <?php else: ?>
                                        <?= $index + 1; ?>
                                    <?php endif; ?>
                                </span>

                                <span><?= e($stage); ?></span>
                            </div>

                            <?php if ($index < count($progressStages) - 1): ?>
                                <span
                                    class="
                                        progress-connector
                                        <?= $index < $activeStageIndex
                                            ? "filled"
                                            : ""; ?>
                                    ">
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="pickup-detail-grid">
                        <div>
                            <span>Scrap type</span>
                            <strong>
                                <?= e($activePickup["scrap_type"]); ?>
                            </strong>
                        </div>

                        <div>
                            <span>Estimated quantity</span>
                            <strong>
                                <?= $activePickup["scrap_weight"] !== null
                                    ? e($activePickup["scrap_weight"]) . " kg"
                                    : "Not specified"; ?>
                            </strong>
                        </div>

                        <div>
                            <span>Pickup date</span>
                            <strong>
                                <?= formatDateValue(
                                    $activePickup["preferred_pickup_date"]
                                ); ?>
                            </strong>
                        </div>

                        <div>
                            <span>Pickup time</span>
                            <strong>
                                <?= !empty($activePickup["pickup_time"])
                                    ? e($activePickup["pickup_time"])
                                    : "Not specified"; ?>
                            </strong>
                        </div>

                        <div class="pickup-address">
                            <span>Pickup address</span>
                            <strong>
                                <?= !empty($activePickup["pickup_address"])
                                    ? e($activePickup["pickup_address"])
                                    : "Address not available"; ?>
                            </strong>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <section
                    class="empty-pickup-card glass-card reveal-item"
                    style="--delay: 340ms;">

                    <div class="empty-icon">
                        <i class="ri-calendar-check-line"></i>
                    </div>

                    <div>
                        <span class="section-eyebrow">
                            Current pickup
                        </span>

                        <h2>No upcoming pickups</h2>

                        <p>
                            You do not have any scheduled pickups yet.
                        </p>
                    </div>

                    <a
                        href="create_request.php"
                        class="eco-button eco-button-primary">

                        Schedule Your First Pickup
                    </a>
                </section>
            <?php endif; ?>

            <section class="dashboard-lower-grid">
                <div
                    class="quick-actions-card glass-card reveal-item"
                    style="--delay: 390ms;">

                    <div class="section-heading-row">
                        <div>
                            <span class="section-eyebrow">
                                Shortcuts
                            </span>

                            <h2>Quick Actions</h2>
                        </div>
                    </div>

                    <div class="quick-actions-grid">
                        <a
                            href="create_request.php"
                            class="quick-action action-green">

                            <i class="ri-calendar-event-line"></i>
                            <strong>Schedule Pickup</strong>
                            <span>Create a new pickup</span>
                        </a>

                        <a
                            href="history.php"
                            class="quick-action action-cyan">

                            <i class="ri-map-pin-line"></i>
                            <strong>Track Pickup</strong>
                            <span>View active requests</span>
                        </a>

                        <a
                            href="qr.php"
                            class="quick-action action-light">

                            <i class="ri-qr-code-line"></i>
                            <strong>QR Code</strong>
                            <span>Verify a collection</span>
                        </a>

                        <a
                            href="history.php"
                            class="quick-action action-dark">

                            <i class="ri-history-line"></i>
                            <strong>Collection History</strong>
                            <span>Review previous pickups</span>
                        </a>
                    </div>
                </div>

                <div
                    class="impact-card glass-card reveal-item"
                    style="--delay: 440ms;">

                    <div class="section-heading-row">
                        <div>
                            <span class="section-eyebrow">
                                Your contribution
                            </span>

                            <h2>Environmental Impact</h2>
                        </div>

                        <i class="ri-leaf-line impact-heading-icon"></i>
                    </div>

                    <div class="impact-content">
                        <div
                            class="impact-ring"
                            style="--progress: <?= $impactProgress; ?>%;">

                            <div>
                                <strong>
                                    <?= $co2Saved !== null
                                        ? e($co2Saved)
                                        : "—"; ?>
                                </strong>

                                <span>kg CO₂</span>
                            </div>
                        </div>

                        <div class="impact-list">
                            <div>
                                <span>Completed pickups</span>
                                <strong><?= $completed; ?></strong>
                            </div>

                            <div>
                                <span>Recycling progress</span>
                                <strong><?= $impactProgress; ?>%</strong>
                            </div>

                            <div>
                                <span>Total pickup requests</span>
                                <strong><?= $total; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="recent-card glass-card reveal-item"
                style="--delay: 490ms;"
                aria-labelledby="recentPickupsHeading">

                <div class="section-heading-row">
                    <div>
                        <span class="section-eyebrow">
                            Activity
                        </span>

                        <h2 id="recentPickupsHeading">
                            Recent Pickups
                        </h2>
                    </div>

                    <a
                        href="history.php"
                        class="view-all-link">

                        View all
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>

                <?php if (!empty($recentPickups)): ?>
                    <div class="table-wrapper custom-scrollbar">
                        <table class="pickup-table">
                            <thead>
                                <tr>
                                    <th>Pickup ID</th>
                                    <th>Scrap Type</th>
                                    <th>Quantity</th>
                                    <th>Pickup Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($recentPickups as $pickup): ?>
                                    <?php
                                    $status = $pickup["status"] ?? "Pending";

                                    $statusClass =
                                        $statusClasses[$status]
                                        ?? "status-default";

                                    $statusIcon =
                                        $statusIcons[$status]
                                        ?? "ri-information-line";
                                    ?>

                                    <tr>
                                        <td data-label="Pickup ID">
                                            <strong>
                                                #ECO-<?= e(
                                                    $pickup["activity_id"]
                                                ); ?>
                                            </strong>
                                        </td>

                                        <td data-label="Scrap Type">
                                            <?= e($pickup["scrap_type"]); ?>
                                        </td>

                                        <td data-label="Quantity">
                                            <?= $pickup["scrap_weight"] !== null
                                                ? e(
                                                    $pickup["scrap_weight"]
                                                ) . " kg"
                                                : "—"; ?>
                                        </td>

                                        <td data-label="Pickup Date">
                                            <?= formatDateValue(
                                                $pickup[
                                                    "preferred_pickup_date"
                                                ]
                                            ); ?>
                                        </td>

                                        <td data-label="Status">
                                            <span
                                                class="
                                                    pickup-status
                                                    <?= e($statusClass); ?>
                                                ">

                                                <i
                                                    class="<?= e(
                                                        $statusIcon
                                                    ); ?>"
                                                    aria-hidden="true">
                                                </i>

                                                <?= e($status); ?>
                                            </span>
                                        </td>

                                        <td data-label="Action">
                                            <a
                                                href="history.php?id=<?= (int) $pickup["activity_id"]; ?>"
                                                class="table-action">

                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-empty-state">
                        <i class="ri-inbox-line"></i>

                        <h3>No pickup history yet</h3>

                        <p>
                            Your recent pickups will appear here.
                        </p>

                        <a
                            href="create_request.php"
                            class="eco-button eco-button-primary">

                            Create Pickup
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <script src="../assets/js/userdas.js"></script>

<script>

document.addEventListener("DOMContentLoaded", function () {

    const notificationBtn =
        document.getElementById("notificationBtn");

    const notificationDropdown =
        document.getElementById("notificationDropdown");


    if (!notificationBtn || !notificationDropdown) {
        console.error("Notification elements not found.");
        return;
    }


    /*
    ---------------------------------------------------------
    OPEN / CLOSE NOTIFICATION DROPDOWN
    ---------------------------------------------------------
    */

    notificationBtn.addEventListener("click", function (event) {

        event.preventDefault();
        event.stopPropagation();

        const isOpen =
            notificationDropdown.classList.contains("show");


        if (isOpen) {

            notificationDropdown.classList.remove("show");

            notificationBtn.setAttribute(
                "aria-expanded",
                "false"
            );

            notificationDropdown.setAttribute(
                "aria-hidden",
                "true"
            );

        } else {

            notificationDropdown.classList.add("show");

            notificationBtn.setAttribute(
                "aria-expanded",
                "true"
            );

            notificationDropdown.setAttribute(
                "aria-hidden",
                "false"
            );

        }

    });


    /*
    ---------------------------------------------------------
    CLICK OUTSIDE
    ---------------------------------------------------------
    */

    document.addEventListener("click", function (event) {

        if (
            !notificationDropdown.contains(event.target) &&
            !notificationBtn.contains(event.target)
        ) {

            notificationDropdown.classList.remove("show");

            notificationBtn.setAttribute(
                "aria-expanded",
                "false"
            );

            notificationDropdown.setAttribute(
                "aria-hidden",
                "true"
            );

        }

    });


    /*
    ---------------------------------------------------------
    ESC KEY
    ---------------------------------------------------------
    */

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {

            notificationDropdown.classList.remove("show");

            notificationBtn.setAttribute(
                "aria-expanded",
                "false"
            );

            notificationDropdown.setAttribute(
                "aria-hidden",
                "true"
            );

        }

    });

});

</script>

</body>
</html>