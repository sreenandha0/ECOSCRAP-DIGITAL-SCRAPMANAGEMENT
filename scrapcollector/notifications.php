<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['collector_id'])) {
    header("Location: ../login.php");
    exit();
}

$collector_id = (int) $_SESSION['collector_id'];

function redirectToNotifications($filter = 'all') {
    header("Location: notifications.php?filter=" . urlencode($filter));
    exit();
}

if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $notification_id = (int) $_GET['read'];

    $read_stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ?
          AND recipient_type = 'Collector'
          AND recipient_id = ?
    ");
    $read_stmt->bind_param("ii", $notification_id, $collector_id);
    $read_stmt->execute();

    redirectToNotifications($_GET['filter'] ?? 'all');
}

if (isset($_POST['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE recipient_type = 'Collector'
          AND recipient_id = ?
          AND is_read = 0
    ");
    $mark_all_stmt->bind_param("i", $collector_id);
    $mark_all_stmt->execute();

    redirectToNotifications($_GET['filter'] ?? 'all');
}

$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_notifications,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_notifications,
        SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) AS read_notifications
    FROM notifications
    WHERE recipient_type = 'Collector'
      AND recipient_id = ?
");
$stats_stmt->bind_param("i", $collector_id);
$stats_stmt->execute();
$notification_stats = $stats_stmt->get_result()->fetch_assoc();

$total_notifications = (int) ($notification_stats['total_notifications'] ?? 0);
$unread_count = (int) ($notification_stats['unread_notifications'] ?? 0);
$read_count = (int) ($notification_stats['read_notifications'] ?? 0);

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}

$sql = "
    SELECT *
    FROM notifications
    WHERE recipient_type = 'Collector'
      AND recipient_id = ?
";

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}

$sql .= " ORDER BY is_read ASC, created_at DESC";

$notifications_stmt = $conn->prepare($sql);
$notifications_stmt->bind_param("i", $collector_id);
$notifications_stmt->execute();
$notifications = $notifications_stmt->get_result();

$current_page = 'notifications.php';

function getNotificationIcon($type) {
    $type = strtolower(trim((string) $type));

    if (strpos($type, 'assigned') !== false) {
        return 'ri-inbox-archive-line';
    }
    if (strpos($type, 'accepted') !== false) {
        return 'ri-checkbox-circle-line';
    }
    if (strpos($type, 'completed') !== false) {
        return 'ri-checkbox-circle-fill';
    }
    if (strpos($type, 'qr') !== false) {
        return 'ri-qr-code-line';
    }
    if (strpos($type, 'pickup') !== false) {
        return 'ri-truck-line';
    }

    return 'ri-notification-3-line';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | EcoScrap</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/collector.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
</head>
<body>

<nav class="top-navigation">
    <a href="dashboard.php" class="brand-section">
        <img src="../assets/logo/ecoscrap-logo.png" alt="EcoScrap" class="brand-logo">
        <div>
            <div class="brand-name">EcoScrap</div>
            <div class="brand-caption">SCRAP COLLECTOR PORTAL</div>
        </div>
    </a>

    <button type="button" class="navbar-toggle" onclick="toggleNavbar()">
        <i class="ri-menu-line"></i>
    </button>

    <div class="navbar-links" id="navbarLinks">
        <a href="dashboard.php" class="navbar-link">
            <i class="ri-dashboard-line"></i>
            Dashboard
        </a>
    </div>
</nav>

<main class="main">
    <div class="content">
        <div class="page-heading">
            <div>
                <div class="eyebrow">UPDATES & ALERTS</div>
                <h1 class="page-title">Notifications</h1>
                <p class="page-description">
                    Stay updated about pickup assignments, QR verification and important system updates.
                </p>
            </div>
        </div>

        <section class="metrics">
            <div class="metric-card metric-green">
                <div class="metric-top">
                    <span class="metric-label">TOTAL</span>
                    <div class="metric-icon">
                        <i class="ri-notification-3-line"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo $total_notifications; ?></div>
                <div class="metric-help">All notifications</div>
            </div>

            <div class="metric-card metric-amber">
                <div class="metric-top">
                    <span class="metric-label">UNREAD</span>
                    <div class="metric-icon">
                        <i class="ri-mail-unread-line"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo $unread_count; ?></div>
                <div class="metric-help">Require attention</div>
            </div>

            <div class="metric-card metric-blue">
                <div class="metric-top">
                    <span class="metric-label">READ</span>
                    <div class="metric-icon">
                        <i class="ri-checkbox-circle-line"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo $read_count; ?></div>
                <div class="metric-help">Already viewed</div>
            </div>

            <div class="metric-card metric-slate">
                <div class="metric-top">
                    <span class="metric-label">STATUS</span>
                    <div class="metric-icon">
                        <i class="ri-information-line"></i>
                    </div>
                </div>
                <div class="metric-value"><?php echo $unread_count > 0 ? 'New' : 'Clear'; ?></div>
                <div class="metric-help">Notification inbox</div>
            </div>
        </section>

        <div class="notification-toolbar">
            <div class="notification-filters">
                <a href="notifications.php?filter=all" class="notification-filter <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="ri-list-check"></i>
                    All
                </a>

                <a href="notifications.php?filter=unread" class="notification-filter <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                    <i class="ri-mail-unread-line"></i>
                    Unread
                </a>

                <a href="notifications.php?filter=read" class="notification-filter <?php echo $filter === 'read' ? 'active' : ''; ?>">
                    <i class="ri-checkbox-circle-line"></i>
                    Read
                </a>
            </div>

            <?php if ($unread_count > 0): ?>
                <form method="POST">
                    <button type="submit" name="mark_all_read" class="mark-read-button">
                        <i class="ri-check-double-line"></i>
                        Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($notifications->num_rows > 0): ?>
            <section class="notifications-list">
                <?php while ($notification = $notifications->fetch_assoc()): ?>
                    <article class="notification-card <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                        <div class="notification-icon">
                            <i class="<?php echo getNotificationIcon($notification['notification_type']); ?>"></i>
                        </div>

                        <div class="notification-content">
                            <h3 class="notification-title">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </h3>

                            <p class="notification-message">
                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                            </p>

                            <div class="notification-meta">
                                <span>
                                    <i class="ri-time-line"></i>
                                    <?php echo date("d M Y, h:i A", strtotime($notification['created_at'])); ?>
                                </span>

                                <?php if (!empty($notification['notification_type'])): ?>
                                    <span>
                                        <i class="ri-price-tag-3-line"></i>
                                        <?php echo htmlspecialchars($notification['notification_type']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="notification-actions">
                            <?php if ($notification['is_read'] == 0): ?>
                                <a
                                    href="notifications.php?filter=<?php echo urlencode($filter); ?>&read=<?php echo (int) $notification['notification_id']; ?>"
                                    class="read-button"
                                    title="Mark as read"
                                >
                                    <i class="ri-check-line"></i>
                                </a>
                            <?php else: ?>
                                <span class="read-status">Read</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            </section>
        <?php else: ?>
            <section class="empty-state">
                <div class="empty-icon">
                    <i class="ri-notification-off-line"></i>
                </div>
                <h3>No Notifications</h3>
                <p>
                    You don't have any
                    <?php echo htmlspecialchars($filter); ?>
                    notifications right now.
                </p>
            </section>
        <?php endif; ?>
    </div>
</main>

<script>
function toggleNavbar() {
    document.getElementById('navbarLinks').classList.toggle('open');
}
</script>

</body>
</html>