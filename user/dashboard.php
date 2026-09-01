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

    <link rel="stylesheet" href="../assets/css/user.css">
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
