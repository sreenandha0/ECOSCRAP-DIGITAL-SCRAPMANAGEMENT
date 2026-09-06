<?php
session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ecoscrap_db';

$conn = null;
$db_error = null;

try {
    $conn = new mysqli(
        $db_host,
        $db_user,
        $db_pass,
        $db_name
    );

    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    $db_error = $e->getMessage();
}

if (
    !isset($_SESSION['collector_id']) ||
    ($_SESSION['role'] ?? '') !== 'Collector'
) {
    header('Location: ../login.php');
    exit();
}

$collector_id = filter_var(
    $_SESSION['collector_id'],
    FILTER_VALIDATE_INT
);

if ($collector_id === false || $collector_id <= 0) {
    session_unset();
    session_destroy();

    header('Location: ../login.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function request_value(
    array $row,
    array $keys,
    string $fallback = ''
): string {
    foreach ($keys as $key) {
        if (
            array_key_exists($key, $row) &&
            $row[$key] !== null &&
            $row[$key] !== ''
        ) {
            return (string)$row[$key];
        }
    }

    return $fallback;
}

function status_class(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {
        'assigned', 'approved' =>
            'status-assigned',

        'accepted' =>
            'status-accepted',

        'pickup started', 'in progress' =>
            'status-progress',

        'qr verified' =>
            'status-verified',

        'completed' =>
            'status-completed',

        'rejected', 'cancelled', 'canceled' =>
            'status-danger',

        default =>
            'status-neutral'
    };
}

function status_icon(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {
        'assigned', 'approved' =>
            'ri-inbox-archive-line',

        'accepted' =>
            'ri-checkbox-circle-line',

        'pickup started', 'in progress' =>
            'ri-map-pin-time-line',

        'qr verified' =>
            'ri-qr-scan-2-line',

        'completed' =>
            'ri-check-double-line',

        'rejected', 'cancelled', 'canceled' =>
            'ri-close-circle-line',

        default =>
            'ri-time-line'
    };
}

function status_label(string $status): string
{
    return match (strtolower(trim($status))) {
        'approved' => 'Assigned',
        'in progress' => 'Pickup Started',
        'canceled' => 'Cancelled',
        default => $status !== '' ? $status : 'Assigned'
    };
}

function format_date_value(string $value): string
{
    if ($value === '') {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('d M Y', $timestamp);
}

function format_time_value(string $value): string
{
    if ($value === '') {
        return 'Flexible time';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('h:i A', $timestamp);
}

/*
|--------------------------------------------------------------------------
| AJAX: Scrap Collector availability
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_status'
) {
    header('Content-Type: application/json; charset=utf-8');

    $new_status = trim($_POST['status'] ?? '');

    $allowed_statuses = [
        'Available',
        'Busy',
        'Offline'
    ];

    if (!in_array($new_status, $allowed_statuses, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid availability status.'
        ]);

        exit();
    }

    if (
        !$conn ||
        $conn->connect_error
    ) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection unavailable.'
        ]);

        exit();
    }

    $stmt = $conn->prepare(
        'UPDATE scrapcollector
         SET availability_status = ?
         WHERE collector_id = ?'
    );

    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to prepare status update.'
        ]);

        exit();
    }

    $stmt->bind_param(
        'si',
        $new_status,
        $collector_id
    );

    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'status' => $new_status
    ]);

    exit();
}

/*
|--------------------------------------------------------------------------
| Scrap Collector profile
|--------------------------------------------------------------------------
*/

$collector = [];

if (
    $conn &&
    !$conn->connect_error
) {
    $stmt = $conn->prepare(
        'SELECT *
         FROM scrapcollector
         WHERE collector_id = ?
         LIMIT 1'
    );

    if ($stmt) {
        $stmt->bind_param('i', $collector_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $collector = $result->fetch_assoc() ?: [];

        $stmt->close();
    }
}

if (empty($collector)) {
    $collector = [
        'collector_id' => $collector_id,
        'name' => 'Scrap Collector',
        'vehicle_no' => '',
        'pincode' => '',
        'availability_status' => 'Offline',
        'verification_status' => 'Pending',
        'completed_pickups' => 0
    ];
}

$collector_name = request_value(
    $collector,
    ['name', 'full_name'],
    'Scrap Collector'
);

$collector_pincode = request_value(
    $collector,
    ['pincode', 'pin_code'],
    ''
);

$collector_vehicle = request_value(
    $collector,
    ['vehicle_no', 'vehicle_number'],
    'Vehicle not assigned'
);

$current_availability = request_value(
    $collector,
    ['availability_status'],
    'Available'
);

$name_parts = preg_split(
    '/\s+/',
    trim($collector_name)
);

$initials = '';

foreach (array_slice($name_parts, 0, 2) as $part) {
    $initials .= strtoupper(
        substr($part, 0, 1)
    );
}

$initials = $initials !== ''
    ? $initials
    : 'SC';

/*
|--------------------------------------------------------------------------
| Activity records
|--------------------------------------------------------------------------
*/

$activity_requests = [];

if (
    $conn &&
    !$conn->connect_error
) {
    $activity_sql = '
        SELECT *
        FROM activity
        WHERE collector_id = ?
           OR (
                collector_id IS NULL
                AND pickup_pincode = ?
              )
        ORDER BY activity_id DESC
    ';

    $stmt = $conn->prepare($activity_sql);

    if ($stmt) {
        $stmt->bind_param(
            'is',
            $collector_id,
            $collector_pincode
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $activity_requests[] = $row;
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Dashboard metrics
|--------------------------------------------------------------------------
*/

$metrics = [
    'assigned' => 0,
    'awaiting' => 0,
    'active' => 0,
    'qr_verification' => 0,
    'completed' => 0,
    'total_weight' => 0,
    'monthly_completed' => 0
];

foreach ($activity_requests as $activity) {
    $status = strtolower(
        trim(
            request_value(
                $activity,
                ['status'],
                'Assigned'
            )
        )
    );

    $qr_status = strtolower(
        trim(
            request_value(
                $activity,
                ['qr_status'],
                ''
            )
        )
    );

    $metrics['assigned']++;

    if (
        in_array(
            $status,
            ['approved', 'assigned'],
            true
        ) &&
        empty($activity['collector_id'])
    ) {
        $metrics['awaiting']++;
    }

    if (
        in_array(
            $status,
            ['accepted', 'pickup started', 'in progress'],
            true
        )
    ) {
        $metrics['active']++;
    }

    if (
        $qr_status !== 'used' &&
        in_array(
            $status,
            ['pickup started', 'in progress'],
            true
        )
    ) {
        $metrics['qr_verification']++;
    }

    if ($status === 'completed') {
        $metrics['completed']++;

        $metrics['total_weight'] += (float)request_value(
            $activity,
            ['scrap_weight'],
            '0'
        );

        $request_date = request_value(
            $activity,
            ['request_date', 'pickup_date'],
            ''
        );

        if (
            $request_date !== '' &&
            date('Y-m', strtotime($request_date)) === date('Y-m')
        ) {
            $metrics['monthly_completed']++;
        }
    }
}

if (
    isset($collector['completed_pickups']) &&
    (int)$collector['completed_pickups'] > $metrics['completed']
) {
    $metrics['completed'] =
        (int)$collector['completed_pickups'];
}

/*
|--------------------------------------------------------------------------
| Current active pickup
|--------------------------------------------------------------------------
*/

$current_pickup = null;

foreach ($activity_requests as $activity) {
    $status = strtolower(
        trim(
            request_value(
                $activity,
                ['status'],
                'Assigned'
            )
        )
    );

    if (
        in_array(
            $status,
            [
                'assigned',
                'approved',
                'accepted',
                'pickup started',
                'in progress',
                'qr verified'
            ],
            true
        )
    ) {
        $current_pickup = $activity;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| Today's schedule
|--------------------------------------------------------------------------
*/

$todays_schedule = [];

foreach ($activity_requests as $activity) {
    $date_value = request_value(
        $activity,
        [
            'pickup_date',
            'request_date',
            'scheduled_date'
        ],
        ''
    );

    if (
        $date_value !== '' &&
        date('Y-m-d', strtotime($date_value)) === date('Y-m-d')
    ) {
        $todays_schedule[] = $activity;
    }
}

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifications = [];
$unread_count = 0;

if (
    $conn &&
    !$conn->connect_error
) {
    $notification_sql = '
        SELECT *
        FROM notifications
        WHERE recipient_type = "Collector"
          AND recipient_id = ?
        ORDER BY created_at DESC
    ';

    $stmt = $conn->prepare($notification_sql);

    if ($stmt) {
        $stmt->bind_param('i', $collector_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($notification = $result->fetch_assoc()) {
            $notifications[] = $notification;

            if (
                (int)($notification['is_read'] ?? 0) === 0
            ) {
                $unread_count++;
            }
        }

        $stmt->close();
    }
}

$display_pickups = array_slice(
    $activity_requests,
    0,
    8
);

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
        EcoScrap - Scrap Collector Dashboard
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/scrapcollector.css">
</head>

<body>
    <div class="app-shell">

       <?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside
    id="sidebar"
    class="sidebar"
>
    <div>

        <!-- Brand -->
        <div class="brand">
            <div class="brand-mark">
                <i class="ri-recycle-line"></i>
            </div>

            <div>
                <div class="brand-name">
                    EcoScrap
                </div>

                <div class="brand-role">
                    Scrap Collector Portal
                </div>
            </div>
        </div>


        <!-- Navigation -->
        <nav class="sidebar-nav">

            <!-- Dashboard -->
            <a
                href="dashboard.php"
                class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>"
            >
                <i class="ri-dashboard-line"></i>
                <span>Dashboard</span>
            </a>


            <!-- Assigned Pickups -->
            <a
                href="assigned_requests.php"
                class="nav-link <?php echo $current_page === 'assigned_requests.php' ? 'active' : ''; ?>"
            >
                <i class="ri-inbox-archive-line"></i>

                <span>Assigned Pickups</span>

                <?php if (isset($activity_requests) && count($activity_requests) > 0): ?>
                    <span class="nav-count">
                        <?php echo count($activity_requests); ?>
                    </span>
                <?php endif; ?>
            </a>


            <!-- Active Pickup -->
            <a
                href="active_pickup.php"
                class="nav-link <?php echo $current_page === 'active_pickup.php' ? 'active' : ''; ?>"
            >
                <i class="ri-map-pin-time-line"></i>

                <span>My Active Pickup</span>
            </a>


            <!-- Pickup History -->
            <a
                href="completed.php"
                class="nav-link <?php echo $current_page === 'completed.php' ? 'active' : ''; ?>"
            >
                <i class="ri-history-line"></i>

                <span>Pickup History</span>
            </a>


            <!-- QR Verification -->
            <a
                href="verify_qr.php"
                class="nav-link <?php echo $current_page === 'verify_qr.php' ? 'active' : ''; ?>"
            >
                <i class="ri-qr-scan-2-line"></i>

                <span>QR Verification</span>
            </a>


            <!-- Notifications -->
            <a
                href="notifications.php"
                class="nav-link <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>"
            >
                <i class="ri-notification-3-line"></i>

                <span>Notifications</span>

                <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <span class="nav-count">
                        <?php echo $unread_count; ?>
                    </span>
                <?php endif; ?>
            </a>


            <!-- Profile -->
            <a
                href="profile.php"
                class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>"
            >
                <i class="ri-user-line"></i>

                <span>Profile</span>
            </a>

        </nav>

    </div>


    <!-- Sidebar Bottom -->
    <div class="sidebar-bottom">

        <div class="help-card">

            <i class="ri-customer-service-2-line"></i>

            <div>

                <div class="help-title">
                    Need Help?
                </div>

                <div class="help-text">
                    Need assistance with a pickup?
                </div>

            </div>

        </div>


        <!-- Logout -->
        <a
            href="../logout.php"
            class="logout-link"
        >
            <i class="ri-logout-box-r-line"></i>

            <span>Logout</span>
        </a>

    </div>

</aside>

        <div
            id="mobileOverlay"
            class="mobile-overlay"
            onclick="closeSidebar()"
        ></div>

        <main class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button
                        type="button"
                        class="mobile-menu-button"
                        onclick="toggleSidebar()"
                        aria-label="Open navigation"
                    >
                        <i class="ri-menu-line"></i>
                    </button>

                    <div>
                        <div class="topbar-title">
                            Scrap Collector Dashboard
                        </div>

                        <div class="topbar-subtitle">
                            Manage your assigned scrap pickups
                        </div>
                    </div>
                </div>

                <div class="topbar-right">
                    <div class="availability">
                        <span class="availability-dot"></span>
                        <span id="availabilityText">
                            <?php echo e($current_availability); ?>
                        </span>
                    </div>

                    <div
                        id="notificationWrap"
                        class="notification-wrap"
                    >
                        <button
                            type="button"
                            class="icon-button"
                            onclick="toggleNotifications(event)"
                            aria-label="Notifications"
                        >
                            <i class="ri-notification-3-line"></i>

                            <?php if ($unread_count > 0): ?>
                                <span
                                    id="notificationBadge"
                                    class="notification-badge"
                                ></span>
                            <?php endif; ?>
                        </button>

                        <div
                            id="notificationDropdown"
                            class="notification-dropdown"
                        >
                            <div class="notification-header">
                                <div>
                                    <h3>Notifications</h3>

                                    <p id="unreadText">
                                        <?php echo $unread_count; ?>
                                        unread
                                    </p>
                                </div>
                            </div>

                            <div class="notification-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="empty-state">
                                        <div>
                                            <div class="empty-icon">
                                                <i class="ri-notification-off-line"></i>
                                            </div>

                                            <p>
                                                No notifications yet.
                                            </p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <?php
                                        $is_unread =
                                            (int)($notification['is_read'] ?? 0) === 0;
                                        ?>

                                        <div
                                            class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>"
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
                                                    format_date_value(
                                                        $notification['created_at'] ??
                                                        ''
                                                    )
                                                );
                                                ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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
                                Scrap Collector
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <section class="page-content">
                <?php if ($db_error): ?>
                    <div
                        class="card"
                        style="
                            padding: 16px;
                            margin-bottom: 22px;
                            color: #a94442;
                            background: #fff4f3;
                        "
                    >
                        <strong>Database Notice:</strong>
                        <?php echo e($db_error); ?>
                    </div>
                <?php endif; ?>

                <section class="welcome-section">
                    <div>
                        <div class="eyebrow">
                            Scrap Collector Overview
                        </div>

                        <h1 class="welcome-title">
                            Good Morning,
                            <?php echo e($collector_name); ?>
                            👋
                        </h1>

                        <p class="welcome-description">
                            Here's an overview of your pickup activities
                            and assigned requests.
                        </p>
                    </div>

                    <a
                        href="assigned_requests.php"
                        class="btn btn-primary"
                    >
                        <span>View Assigned Pickups</span>
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </section>

                <section class="stats-grid">
                    <div class="stat-card assigned">
                        <div class="stat-top">
                            <span class="stat-label">
                                Assigned Pickups
                            </span>

                            <span class="stat-icon">
                                <i class="ri-inbox-archive-line"></i>
                            </span>
                        </div>

                        <div class="stat-number">
                            <?php echo $metrics['assigned']; ?>
                        </div>

                        <div class="stat-context">
                            Total pickup requests
                        </div>
                    </div>

                    <div class="stat-card awaiting">
                        <div class="stat-top">
                            <span class="stat-label">
                                Awaiting Acceptance
                            </span>

                            <span class="stat-icon">
                                <i class="ri-time-line"></i>
                            </span>
                        </div>

                        <div class="stat-number">
                            <?php echo $metrics['awaiting']; ?>
                        </div>

                        <div class="stat-context">
                            Pending action
                        </div>
                    </div>

                    <div class="stat-card active">
                        <div class="stat-top">
                            <span class="stat-label">
                                Active Pickups
                            </span>

                            <span class="stat-icon">
                                <i class="ri-map-pin-time-line"></i>
                            </span>
                        </div>

                        <div class="stat-number">
                            <?php echo $metrics['active']; ?>
                        </div>

                        <div class="stat-context">
                            Accepted or in progress
                        </div>
                    </div>

                    <div class="stat-card qr">
                        <div class="stat-top">
                            <span class="stat-label">
                                QR Verification
                            </span>

                            <span class="stat-icon">
                                <i class="ri-qr-code-line"></i>
                            </span>
                        </div>

                        <div class="stat-number">
                            <?php echo $metrics['qr_verification']; ?>
                        </div>

                        <div class="stat-context">
                            Ready for verification
                        </div>
                    </div>

                    <div class="stat-card completed">
                        <div class="stat-top">
                            <span class="stat-label">
                                Completed
                            </span>

                            <span class="stat-icon">
                                <i class="ri-checkbox-circle-line"></i>
                            </span>
                        </div>

                        <div class="stat-number">
                            <?php echo $metrics['completed']; ?>
                        </div>

                        <div class="stat-context">
                            Completed pickups
                        </div>
                    </div>
                </section>

                <section class="dashboard-grid">
                    <div
                        id="active-pickup"
                        class="card active-card"
                    >
                        <div class="card-header">
                            <div>
                                <h2 class="card-title">
                                    Current Active Pickup
                                </h2>

                                <p class="card-subtitle">
                                    Your current pickup in progress
                                </p>
                            </div>

                            <?php if ($current_pickup): ?>
                                <?php
                                $current_status = status_label(
                                    request_value(
                                        $current_pickup,
                                        ['status'],
                                        'Assigned'
                                    )
                                );
                                ?>

                                <span
                                    class="status-pill <?php echo status_class($current_status); ?>"
                                >
                                    <i
                                        class="<?php echo status_icon($current_status); ?>"
                                    ></i>

                                    <?php echo e($current_status); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($current_pickup): ?>
                            <?php
                            $pickup_id = request_value(
                                $current_pickup,
                                ['activity_id'],
                                '0'
                            );

                            $pickup_user = request_value(
                                $current_pickup,
                                [
                                    'user_name',
                                    'name',
                                    'customer_name'
                                ],
                                'User #' .
                                request_value(
                                    $current_pickup,
                                    ['user_id'],
                                    'N/A'
                                )
                            );

                            $pickup_address = request_value(
                                $current_pickup,
                                ['pickup_address', 'address'],
                                'Address not available'
                            );

                            $pickup_pin = request_value(
                                $current_pickup,
                                ['pickup_pincode', 'pincode'],
                                'N/A'
                            );

                            $scrap_type = request_value(
                                $current_pickup,
                                ['scrap_type'],
                                'General Scrap'
                            );

                            $scrap_weight = request_value(
                                $current_pickup,
                                ['scrap_weight'],
                                '0'
                            );

                            $pickup_date = request_value(
                                $current_pickup,
                                [
                                    'pickup_date',
                                    'request_date'
                                ],
                                ''
                            );

                            $pickup_time = request_value(
                                $current_pickup,
                                [
                                    'pickup_time',
                                    'preferred_time'
                                ],
                                ''
                            );

                            $current_status_lower = strtolower(
                                trim(
                                    request_value(
                                        $current_pickup,
                                        ['status'],
                                        'Assigned'
                                    )
                                )
                            );

                            $steps = [
                                'Assigned',
                                'Accepted',
                                'Pickup Started',
                                'QR Verified',
                                'Completed'
                            ];

                            $step_index = match (
                                $current_status_lower
                            ) {
                                'accepted' => 1,
                                'pickup started',
                                'in progress' => 2,
                                'qr verified' => 3,
                                'completed' => 4,
                                default => 0
                            };
                            ?>

                            <div class="active-body">
                                <div class="pickup-overview">
                                    <div class="detail-box">
                                        <div class="detail-label">
                                            User Information
                                        </div>

                                        <div class="detail-value">
                                            <?php echo e($pickup_user); ?>
                                        </div>
                                    </div>

                                    <div class="detail-box">
                                        <div class="detail-label">
                                            Pickup ID
                                        </div>

                                        <div class="detail-value">
                                            #<?php echo e($pickup_id); ?>
                                        </div>
                                    </div>

                                    <div class="detail-box">
                                        <div class="detail-label">
                                            Pickup Address
                                        </div>

                                        <div class="detail-value small">
                                            <?php echo e($pickup_address); ?>
                                        </div>
                                    </div>

                                    <div class="detail-box">
                                        <div class="detail-label">
                                            Pincode
                                        </div>

                                        <div class="detail-value">
                                            <?php echo e($pickup_pin); ?>
                                        </div>
                                    </div>

                                    <div class="detail-box">
                                        <div class="detail-label">
                                            Scrap Details
                                        </div>

                                        <div class="detail-value">
                                            <?php echo e($scrap_type); ?>
                                            ·
                                            <?php echo e($scrap_weight); ?>
                                            Kg
                                        </div>
                                    </div>

                                    <div class="detail-box">
                                        <div class="detail-label">
                                            Pickup Schedule
                                        </div>

                                        <div class="detail-value small">
                                            <?php echo e(
                                                format_date_value(
                                                    $pickup_date
                                                )
                                            ); ?>

                                            ·

                                            <?php echo e(
                                                format_time_value(
                                                    $pickup_time
                                                )
                                            ); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="progress-tracker">
                                    <?php foreach ($steps as $index => $step): ?>
                                        <?php
                                        $step_class = '';

                                        if ($index < $step_index) {
                                            $step_class = 'complete';
                                        } elseif ($index === $step_index) {
                                            $step_class = 'current';
                                        }
                                        ?>

                                        <div
                                            class="progress-step <?php echo $step_class; ?>"
                                        >
                                            <div class="step-circle">
                                                <?php if ($index < $step_index): ?>
                                                    <i class="ri-check-line"></i>
                                                <?php else: ?>
                                                    <?php echo $index + 1; ?>
                                                <?php endif; ?>
                                            </div>

                                            <span class="step-label">
                                                <?php echo e($step); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="active-actions">
                                    <?php if (
                                        in_array(
                                            $current_status_lower,
                                            ['assigned', 'approved'],
                                            true
                                        )
                                    ): ?>
                                        <a
                                            href="accept_pickup.php?id=<?php echo urlencode($pickup_id); ?>"
                                            class="btn btn-primary"
                                        >
                                            <i class="ri-checkbox-circle-line"></i>
                                            <span>Accept Pickup</span>
                                        </a>
                                    <?php elseif (
                                        $current_status_lower === 'accepted'
                                    ): ?>
                                        <a
                                            href="start_pickup.php?id=<?php echo urlencode($pickup_id); ?>"
                                            class="btn btn-primary"
                                        >
                                            <i class="ri-play-circle-line"></i>
                                            <span>Start Pickup</span>
                                        </a>
                                    <?php elseif (
                                        in_array(
                                            $current_status_lower,
                                            [
                                                'pickup started',
                                                'in progress'
                                            ],
                                            true
                                        )
                                    ): ?>
                                        <a
                                            href="verify_qr.php?id=<?php echo urlencode($pickup_id); ?>"
                                            class="btn btn-primary"
                                        >
                                            <i class="ri-qr-scan-2-line"></i>
                                            <span>Verify QR Code</span>
                                        </a>
                                    <?php elseif (
                                        $current_status_lower === 'qr verified'
                                    ): ?>
                                        <a
                                            href="complete_pickup.php?id=<?php echo urlencode($pickup_id); ?>"
                                            class="btn btn-primary"
                                        >
                                            <i class="ri-check-double-line"></i>
                                            <span>Complete Pickup</span>
                                        </a>
                                    <?php endif; ?>

                                    <a
                                        href="assigned_requests.php?id=<?php echo urlencode($pickup_id); ?>"
                                        class="btn btn-secondary"
                                    >
                                        <i class="ri-eye-line"></i>
                                        <span>View Details</span>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div>
                                    <div class="empty-icon">
                                        <i class="ri-truck-line"></i>
                                    </div>

                                    <h3>
                                        No Active Pickup
                                    </h3>

                                    <p>
                                        You currently don't have a pickup
                                        in progress. Check your assigned
                                        pickup requests to get started.
                                    </p>

                                    <a
                                        href="assigned_requests.php"
                                        class="btn btn-primary"
                                    >
                                        <span>View Assigned Pickups</span>
                                        <i class="ri-arrow-right-line"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="side-stack">
                        <div class="card">
                            <div class="card-header">
                                <div>
                                    <h2 class="card-title">
                                        Quick Actions
                                    </h2>

                                    <p class="card-subtitle">
                                        Frequently used Scrap Collector tools
                                    </p>
                                </div>
                            </div>

                            <div class="quick-actions">
                                <a
                                    href="assigned_requests.php"
                                    class="quick-action"
                                >
                                    <span class="quick-icon">
                                        <i class="ri-inbox-archive-line"></i>
                                    </span>

                                    <span class="quick-content">
                                        <span class="quick-title">
                                            Assigned Pickups
                                        </span>

                                        <span class="quick-description">
                                            Review newly assigned pickup requests
                                        </span>
                                    </span>

                                    <i class="ri-arrow-right-s-line quick-arrow"></i>
                                </a>

                                <a
                                    href="verify_qr.php"
                                    class="quick-action"
                                >
                                    <span class="quick-icon">
                                        <i class="ri-qr-scan-2-line"></i>
                                    </span>

                                    <span class="quick-content">
                                        <span class="quick-title">
                                            Scan QR Code
                                        </span>

                                        <span class="quick-description">
                                            Verify a pickup using QR code
                                        </span>
                                    </span>

                                    <i class="ri-arrow-right-s-line quick-arrow"></i>
                                </a>

                                <a
                                    href="completed.php"
                                    class="quick-action"
                                >
                                    <span class="quick-icon">
                                        <i class="ri-history-line"></i>
                                    </span>

                                    <span class="quick-content">
                                        <span class="quick-title">
                                            Pickup History
                                        </span>

                                        <span class="quick-description">
                                            View completed pickup requests
                                        </span>
                                    </span>

                                    <i class="ri-arrow-right-s-line quick-arrow"></i>
                                </a>

                                <a
                                    href="#notifications"
                                    class="quick-action"
                                    onclick="openNotifications(event)"
                                >
                                    <span class="quick-icon">
                                        <i class="ri-notification-3-line"></i>
                                    </span>

                                    <span class="quick-content">
                                        <span class="quick-title">
                                            Notifications
                                        </span>

                                        <span class="quick-description">
                                            Check your latest updates
                                        </span>
                                    </span>

                                    <i class="ri-arrow-right-s-line quick-arrow"></i>
                                </a>
                            </div>
                        </div>

                        <div class="card schedule-card">
                            <div>
                                <h2 class="card-title">
                                    Today's Pickup Schedule
                                </h2>

                                <p class="card-subtitle">
                                    Your assigned pickups for today
                                </p>
                            </div>

                            <?php if (empty($todays_schedule)): ?>
                                <div
                                    style="
                                        padding-top: 28px;
                                        color: var(--text-muted);
                                        font-size: 12px;
                                        text-align: center;
                                    "
                                >
                                    No pickups scheduled for today.
                                </div>
                            <?php else: ?>
                                <div class="schedule-list">
                                    <?php foreach (
                                        array_slice(
                                            $todays_schedule,
                                            0,
                                            4
                                        ) as $schedule
                                    ): ?>
                                        <?php
                                        $schedule_user = request_value(
                                            $schedule,
                                            [
                                                'user_name',
                                                'name',
                                                'customer_name'
                                            ],
                                            'User #' .
                                            request_value(
                                                $schedule,
                                                ['user_id'],
                                                'N/A'
                                            )
                                        );

                                        $schedule_location = request_value(
                                            $schedule,
                                            [
                                                'pickup_pincode',
                                                'pincode'
                                            ],
                                            'Location unavailable'
                                        );

                                        $schedule_time = request_value(
                                            $schedule,
                                            [
                                                'pickup_time',
                                                'preferred_time',
                                                'request_date'
                                            ],
                                            ''
                                        );

                                        $schedule_status = status_label(
                                            request_value(
                                                $schedule,
                                                ['status'],
                                                'Assigned'
                                            )
                                        );
                                        ?>

                                        <div class="schedule-item">
                                            <div class="schedule-time">
                                                <?php echo e(
                                                    format_time_value(
                                                        $schedule_time
                                                    )
                                                ); ?>
                                            </div>

                                            <span class="schedule-dot"></span>

                                            <div class="schedule-details">
                                                <div class="schedule-user">
                                                    <?php echo e(
                                                        $schedule_user
                                                    ); ?>
                                                </div>

                                                <div class="schedule-location">
                                                    <?php echo e(
                                                        $schedule_location
                                                    ); ?>

                                                    ·

                                                    <?php echo e(
                                                        $schedule_status
                                                    ); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="card performance-card">
                    <div class="card-title">
                        Your Collection Activity
                    </div>

                    <div class="card-subtitle">
                        A clear view of your Scrap Collector performance
                    </div>

                    <div class="performance-stats">
                        <div class="performance-stat">
                            <strong>
                                <?php echo $metrics['completed']; ?>
                            </strong>

                            <span>
                                Total pickups completed
                            </span>
                        </div>

                        <div class="performance-stat">
                            <strong>
                                <?php echo number_format(
                                    $metrics['total_weight'],
                                    1
                                ); ?>
                                Kg
                            </strong>

                            <span>
                                Scrap collected
                            </span>
                        </div>

                        <div class="performance-stat">
                            <strong>
                                <?php echo $metrics['monthly_completed']; ?>
                            </strong>

                            <span>
                                Current month pickups
                            </span>
                        </div>
                    </div>
                </section>

                <section class="card table-card">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">
                                Recent Pickup Activity
                            </h2>

                            <p class="card-subtitle">
                                Your latest assigned and completed pickups
                            </p>
                        </div>

                        <a
                            href="assigned_requests.php"
                            class="btn btn-secondary"
                        >
                            <span>View All</span>
                            <i class="ri-arrow-right-line"></i>
                        </a>
                    </div>

                    <div class="table-wrapper">
                        <table class="pickup-table">
                            <thead>
                                <tr>
                                    <th>Pickup ID</th>
                                    <th>User</th>
                                    <th>Scrap Type</th>
                                    <th>Pickup Date</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($display_pickups)): ?>
                                    <tr>
                                        <td
                                            colspan="7"
                                            style="
                                                padding: 45px 20px;
                                                color: var(--text-muted);
                                                text-align: center;
                                            "
                                        >
                                            No pickup activity available.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($display_pickups as $activity): ?>
                                        <?php
                                        $activity_id = request_value(
                                            $activity,
                                            ['activity_id'],
                                            '0'
                                        );

                                        $activity_user = request_value(
                                            $activity,
                                            [
                                                'user_name',
                                                'name',
                                                'customer_name'
                                            ],
                                            'User #' .
                                            request_value(
                                                $activity,
                                                ['user_id'],
                                                'N/A'
                                            )
                                        );

                                        $activity_scrap = request_value(
                                            $activity,
                                            ['scrap_type'],
                                            'General Scrap'
                                        );

                                        $activity_date = request_value(
                                            $activity,
                                            [
                                                'pickup_date',
                                                'request_date'
                                            ],
                                            ''
                                        );

                                        $activity_location = request_value(
                                            $activity,
                                            [
                                                'pickup_pincode',
                                                'pincode'
                                            ],
                                            'N/A'
                                        );

                                        $activity_status = status_label(
                                            request_value(
                                                $activity,
                                                ['status'],
                                                'Assigned'
                                            )
                                        );

                                        $activity_status_lower =
                                            strtolower(
                                                trim($activity_status)
                                            );
                                        ?>

                                        <tr>
                                            <td>
                                                <span class="pickup-id">
                                                    #<?php echo e(
                                                        $activity_id
                                                    ); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="user-cell">
                                                    <?php echo e(
                                                        $activity_user
                                                    ); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php echo e(
                                                    $activity_scrap
                                                ); ?>
                                            </td>

                                            <td>
                                                <?php echo e(
                                                    format_date_value(
                                                        $activity_date
                                                    )
                                                ); ?>
                                            </td>

                                            <td>
                                                <span class="location-cell">
                                                    <?php echo e(
                                                        $activity_location
                                                    ); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span
                                                    class="status-pill <?php echo status_class($activity_status); ?>"
                                                >
                                                    <i
                                                        class="<?php echo status_icon($activity_status); ?>"
                                                    ></i>

                                                    <?php echo e(
                                                        $activity_status
                                                    ); ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php if (
                                                    in_array(
                                                        $activity_status_lower,
                                                        [
                                                            'assigned',
                                                            'approved'
                                                        ],
                                                        true
                                                    )
                                                ): ?>
                                                    <a
                                                        href="accept_pickup.php?id=<?php echo urlencode($activity_id); ?>"
                                                        class="row-action dark"
                                                    >
                                                        Accept
                                                    </a>
                                                <?php elseif (
                                                    $activity_status_lower ===
                                                    'accepted'
                                                ): ?>
                                                    <a
                                                        href="start_pickup.php?id=<?php echo urlencode($activity_id); ?>"
                                                        class="row-action dark"
                                                    >
                                                        Continue
                                                    </a>
                                                <?php elseif (
                                                    in_array(
                                                        $activity_status_lower,
                                                        [
                                                            'pickup started',
                                                            'in progress'
                                                        ],
                                                        true
                                                    )
                                                ): ?>
                                                    <a
                                                        href="verify_qr.php?id=<?php echo urlencode($activity_id); ?>"
                                                        class="row-action dark"
                                                    >
                                                        Verify QR
                                                    </a>
                                                <?php else: ?>
                                                    <a
                                                        href="assigned_requests.php?id=<?php echo urlencode($activity_id); ?>"
                                                        class="row-action"
                                                    >
                                                        View
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <footer class="footer">
                    <span>
                        EcoScrap Scrap Collector Dashboard
                    </span>

                    <span>
                        Scrap Collector ID:
                        <?php echo (int)$collector_id; ?>
                    </span>
                </footer>
            </section>
        </main>
    </div>

    <script>
        const sidebar =
            document.getElementById('sidebar');

        const mobileOverlay =
            document.getElementById('mobileOverlay');

        const notificationDropdown =
            document.getElementById('notificationDropdown');

        const notificationWrap =
            document.getElementById('notificationWrap');

        function toggleSidebar() {
            if (!sidebar || !mobileOverlay) {
                return;
            }

            sidebar.classList.toggle('open');
            mobileOverlay.classList.toggle('show');
        }

        function closeSidebar() {
            if (!sidebar || !mobileOverlay) {
                return;
            }

            sidebar.classList.remove('open');
            mobileOverlay.classList.remove('show');
        }

        function toggleNotifications(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            if (!notificationDropdown) {
                return;
            }

            notificationDropdown.classList.toggle('show');

            if (
                notificationDropdown.classList.contains('show')
            ) {
                markNotificationsAsRead();
            }
        }

        function openNotifications(event) {
            if (event) {
                event.preventDefault();
            }

            if (
                notificationDropdown &&
                !notificationDropdown.classList.contains('show')
            ) {
                notificationDropdown.classList.add('show');
                markNotificationsAsRead();
            }
        }

        document.addEventListener('click', function (event) {
            if (
                notificationWrap &&
                notificationDropdown &&
                !notificationWrap.contains(event.target)
            ) {
                notificationDropdown.classList.remove('show');
            }
        });

        function markNotificationsAsRead() {
            const badge =
                document.getElementById('notificationBadge');

            const unreadText =
                document.getElementById('unreadText');

            if (badge) {
                badge.remove();
            }

            if (unreadText) {
                unreadText.textContent = '0 unread';
            }
        }

        function updateAvailability(status) {
            const formData = new FormData();

            formData.append(
                'action',
                'update_status'
            );

            formData.append(
                'status',
                status
            );

            fetch('dashboard.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    return;
                }

                const availabilityText =
                    document.getElementById(
                        'availabilityText'
                    );

                if (availabilityText) {
                    availabilityText.textContent =
                        data.status;
                }
            })
            .catch(function (error) {
                console.error(
                    'Availability update failed:',
                    error
                );
            });
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth > 991) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>
