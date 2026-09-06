<?php
session_start();

require_once '../includes/db.php';

if (!isset($_SESSION['collector_id']) || ($_SESSION['role'] ?? '') !== 'Collector') {
    header("Location: ../login.php");
    exit();
}

$collector_id = (int) $_SESSION['collector_id'];

if ($collector_id <= 0) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$message_type = "";

$notification_stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE recipient_type = 'Collector'
      AND recipient_id = ?
      AND is_read = 0
");
$notification_stmt->bind_param("i", $collector_id);
$notification_stmt->execute();
$notification_result = $notification_stmt->get_result()->fetch_assoc();
$unread_count = (int)($notification_result['unread_count'] ?? 0);
$notification_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_pickup'])) {
    $activity_id = (int)($_POST['activity_id'] ?? 0);

    $start_stmt = $conn->prepare("
        UPDATE activity
        SET status = 'In Progress'
        WHERE activity_id = ?
          AND collector_id = ?
          AND status = 'Accepted'
    ");
    $start_stmt->bind_param("ii", $activity_id, $collector_id);
    $start_stmt->execute();

    if ($start_stmt->affected_rows > 0) {
        $message = "Pickup has been started successfully.";
        $message_type = "success";
    } else {
        $message = "Unable to start this pickup. The pickup may already have been started.";
        $message_type = "error";
    }

    $start_stmt->close();
}

$pickup_stmt = $conn->prepare("
    SELECT a.*
    FROM activity a
    WHERE a.collector_id = ?
      AND a.status IN ('Accepted', 'In Progress')
    ORDER BY
        CASE
            WHEN a.status = 'In Progress' THEN 1
            WHEN a.status = 'Accepted' THEN 2
        END,
        a.preferred_pickup_date ASC
    LIMIT 1
");
$pickup_stmt->bind_param("i", $collector_id);
$pickup_stmt->execute();
$active_pickup = $pickup_stmt->get_result()->fetch_assoc();
$pickup_stmt->close();

$customer = null;

if ($active_pickup) {
    $user_stmt = $conn->prepare("
        SELECT
            name,
            email,
            phone,
            address,
            place,
            district,
            state,
            pincode
        FROM user
        WHERE user_id = ?
        LIMIT 1
    ");
    $user_id = (int)$active_pickup['user_id'];
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $customer = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
}

$current_page = 'active_pickup.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Active Pickup | EcoScrap</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/collector.css">

    <style>
        :root {
            --text-dark: var(--text-main);
            --light: var(--text-soft);
            --muted: var(--text-muted);
            --eco-cyan: var(--eco-accent);
        }

        .top-navigation {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 28px;
            background: rgba(255, 255, 255, 0.94);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(16px);
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 12px;
            background: #fff;
            box-shadow: var(--shadow-sm);
        }

        .brand-name {
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.1;
        }

        .brand-caption {
            margin-top: 3px;
            color: var(--eco-primary);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .navbar-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            color: var(--text-main);
            background: #f1f7f3;
            font-size: 22px;
        }

        .navbar-links {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 12px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
            transition: background 0.25s ease, color 0.25s ease, transform 0.25s ease;
        }

        .navbar-link:hover {
            color: var(--eco-primary);
            background: #edf8ed;
            transform: translateY(-1px);
        }

        .main {
            padding: 28px;
        }

        .content {
            width: min(100%, 1600px);
            margin: 0 auto;
        }

        .page-heading {
            margin-bottom: 18px;
        }

        .eyebrow {
            color: var(--eco-primary);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .page-title {
            margin-top: 8px;
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(24px, 3vw, 34px);
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .page-description {
            max-width: 700px;
            margin-top: 8px;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .pickup-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.9fr);
            gap: 16px;
            align-items: start;
        }

        .active-card {
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .active-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .active-card-title {
            display: flex;
            align-items: center;
            gap: 9px;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 800;
        }

        .active-card-title i {
            color: var(--eco-primary);
            font-size: 20px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-accepted {
            color: #a66d0a;
            border: 1px solid #f2d99d;
            background: #fff9eb;
        }

        .status-progress {
            color: #0369a1;
            border: 1px solid #bae6fd;
            background: #f0f9ff;
        }

        .pickup-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 16px;
        }

        .pickup-info {
            padding: 13px;
            border: 1px solid #edf2ef;
            border-radius: 12px;
            background: #f9fcfa;
        }

        .pickup-info-label {
            color: var(--light);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .pickup-info-value {
            margin-top: 6px;
            color: var(--text-dark);
            font-size: 12px;
            font-weight: 700;
        }

        .address-panel {
            margin-top: 16px;
            padding: 16px;
            border-radius: 14px;
            background: #f7faf8;
            border: 1px solid #edf2ef;
        }

        .address-title {
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--eco-primary);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .address-text {
            margin-top: 9px;
            color: var(--text-dark);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.7;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 18px;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 38px;
            padding: 0 13px;
            border-radius: 9px;
            font-size: 10px;
            font-weight: 800;
        }

        .action-primary {
            border: 0;
            color: var(--white);
            background: var(--eco-primary);
        }

        .action-primary:hover {
            background: #256b29;
        }

        .action-outline {
            border: 1px solid var(--border);
            color: var(--text-dark);
            background: var(--white);
        }

        .action-outline:hover {
            border-color: var(--eco-primary);
            color: var(--eco-primary);
        }

        .action-qr {
            border: 0;
            color: var(--white);
            background: var(--eco-cyan);
        }

        .customer-details {
            display: grid;
            gap: 13px;
            margin-top: 17px;
        }

        .customer-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .customer-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            color: var(--eco-primary);
            background: #edf8ee;
            font-size: 16px;
        }

        .customer-label {
            color: var(--light);
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .customer-value {
            margin-top: 4px;
            color: var(--text-dark);
            font-size: 11px;
            font-weight: 700;
            word-break: break-word;
        }

        .scrap-preview {
            margin-top: 16px;
            overflow: hidden;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #f7faf8;
        }

        .scrap-preview img {
            display: block;
            width: 100%;
            height: 220px;
            object-fit: cover;
        }

        .no-image {
            padding: 45px 15px;
            text-align: center;
            color: var(--muted);
            font-size: 10px;
        }

        .no-image i {
            display: block;
            margin-bottom: 8px;
            color: var(--light);
            font-size: 30px;
        }

        .alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 11px;
            font-size: 10px;
            font-weight: 700;
        }

        .alert-success {
            color: #166534;
            border: 1px solid #bde4c2;
            background: #edfaee;
        }

        .alert-error {
            color: #b42318;
            border: 1px solid #fecaca;
            background: #fff1f1;
        }

        .empty-state {
            max-width: 720px;
            margin: 50px auto 0;
            padding: 36px 24px;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 74px;
            height: 74px;
            margin-bottom: 14px;
            border-radius: 22px;
            color: var(--eco-primary);
            background: #edf8ee;
            font-size: 34px;
        }

        .empty-state h3 {
            color: var(--text-main);
            font-size: 18px;
            font-weight: 800;
        }

        .empty-state p {
            max-width: 420px;
            margin: 8px auto 16px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .start-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 10px;
            color: var(--white);
            background: var(--eco-primary);
            font-size: 12px;
            font-weight: 800;
        }

        .start-button:hover {
            background: #256b29;
        }

        @media (max-width: 900px) {
            .pickup-layout {
                grid-template-columns: 1fr;
            }

            .navbar-toggle {
                display: inline-flex;
            }

            .navbar-links {
                position: absolute;
                top: 74px;
                right: 16px;
                left: 16px;
                display: none;
                flex-direction: column;
                padding: 12px;
                border: 1px solid var(--border);
                border-radius: 16px;
                background: var(--white);
                box-shadow: var(--shadow-md);
            }

            .navbar-links.open {
                display: flex;
            }

            .navbar-link {
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 550px) {
            .pickup-info-grid {
                grid-template-columns: 1fr;
            }

            .main {
                padding: 18px 14px;
            }

            .top-navigation {
                padding: 14px 14px;
            }
        }
    </style>
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
        <a href="dashboard.php" class="navbar-link"><i class="ri-dashboard-line"></i>Dashboard</a>
    </div>
</nav>

<main class="main">
    <div class="content">
        <div class="page-heading">
            <div class="eyebrow">CURRENT COLLECTION</div>
            <h1 class="page-title">My Active Pickup</h1>
            <p class="page-description">View your current assigned scrap pickup, customer details, location and verification status.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($active_pickup): ?>
            <div class="pickup-layout">
                <section class="active-card">
                    <div class="active-card-header">
                        <div class="active-card-title">
                            <i class="ri-truck-line"></i>
                            Pickup Details
                        </div>

                        <?php if ($active_pickup['status'] === 'Accepted'): ?>
                            <span class="status-badge status-accepted">
                                <i class="ri-time-line"></i>
                                Accepted
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-progress">
                                <i class="ri-truck-line"></i>
                                In Progress
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="pickup-info-grid">
                        <div class="pickup-info">
                            <div class="pickup-info-label">Scrap Type</div>
                            <div class="pickup-info-value"><?php echo e($active_pickup['scrap_type'] ?? 'Not available'); ?></div>
                        </div>

                        <div class="pickup-info">
                            <div class="pickup-info-label">Estimated Weight</div>
                            <div class="pickup-info-value"><?php echo e($active_pickup['scrap_weight'] ?? '0'); ?> kg</div>
                        </div>

                        <div class="pickup-info">
                            <div class="pickup-info-label">Pickup Date</div>
                            <div class="pickup-info-value">
                                <?php echo !empty($active_pickup['preferred_pickup_date']) ? date("d M Y", strtotime($active_pickup['preferred_pickup_date'])) : 'Not specified'; ?>
                            </div>
                        </div>

                        <div class="pickup-info">
                            <div class="pickup-info-label">Pickup Time</div>
                            <div class="pickup-info-value"><?php echo e($active_pickup['pickup_time'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>

                    <div class="address-panel">
                        <div class="address-title">
                            <i class="ri-map-pin-line"></i>
                            Pickup Address
                        </div>

                        <div class="address-text">
                            <?php echo nl2br(e($active_pickup['pickup_address'] ?? 'Address not available')); ?><br>
                            Pincode: <?php echo e($active_pickup['pickup_pincode'] ?? 'N/A'); ?>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <?php if ($active_pickup['status'] === 'Accepted'): ?>
                            <form method="POST">
                                <input type="hidden" name="activity_id" value="<?php echo (int)$active_pickup['activity_id']; ?>">
                                <button type="submit" name="start_pickup" class="action-button action-primary">
                                    <i class="ri-play-circle-line"></i>
                                    Start Pickup
                                </button>
                            </form>
                        <?php endif; ?>

                        <a
                            href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode(($active_pickup['pickup_address'] ?? '') . ' ' . ($active_pickup['pickup_pincode'] ?? '')); ?>"
                            target="_blank"
                            class="action-button action-outline"
                        >
                            <i class="ri-map-2-line"></i>
                            Open Map
                        </a>

                        <?php if ($active_pickup['status'] === 'In Progress'): ?>
                            <a href="verify_qr.php?activity_id=<?php echo (int)$active_pickup['activity_id']; ?>" class="action-button action-qr">
                                <i class="ri-qr-scan-2-line"></i>
                                Verify QR
                            </a>
                        <?php endif; ?>
                    </div>
                </section>

                <aside>
                    <section class="active-card">
                        <div class="active-card-header">
                            <div class="active-card-title">
                                <i class="ri-user-line"></i>
                                Customer Details
                            </div>
                        </div>

                        <div class="customer-details">
                            <div class="customer-row">
                                <div class="customer-icon"><i class="ri-user-line"></i></div>
                                <div>
                                    <div class="customer-label">Name</div>
                                    <div class="customer-value"><?php echo e($customer['name'] ?? 'Not available'); ?></div>
                                </div>
                            </div>

                            <div class="customer-row">
                                <div class="customer-icon"><i class="ri-phone-line"></i></div>
                                <div>
                                    <div class="customer-label">Phone</div>
                                    <div class="customer-value"><?php echo e($customer['phone'] ?? 'Not available'); ?></div>
                                </div>
                            </div>

                            <div class="customer-row">
                                <div class="customer-icon"><i class="ri-mail-line"></i></div>
                                <div>
                                    <div class="customer-label">Email</div>
                                    <div class="customer-value"><?php echo e($customer['email'] ?? 'Not available'); ?></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="active-card" style="margin-top: 16px;">
                        <div class="active-card-header">
                            <div class="active-card-title">
                                <i class="ri-image-line"></i>
                                Scrap Image
                            </div>
                        </div>

                        <div class="scrap-preview">
                            <?php if (!empty($active_pickup['scrap_image'])): ?>
                                <img src="../<?php echo e($active_pickup['scrap_image']); ?>" alt="Scrap Image">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="ri-image-line"></i>
                                    No scrap image available.
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </aside>
            </div>
        <?php else: ?>
            <section class="empty-state">
                <div class="empty-icon">
                    <i class="ri-truck-line"></i>
                </div>

                <h3>No Active Pickup</h3>
                <p>You currently do not have an accepted or in-progress pickup.</p>

                <a href="assigned_requests.php" class="start-button">
                    <i class="ri-inbox-archive-line"></i>
                    View Assigned Pickups
                </a>
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