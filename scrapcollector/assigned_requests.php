<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (
    !isset($_SESSION['collector_id']) ||
    ($_SESSION['role'] ?? '') !== 'Collector'
) {
    redirect("../login.php");
}

$collector_id = (int)$_SESSION['collector_id'];

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
| AJAX: Update collector availability
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_status'
) {
    header('Content-Type: application/json');

    $status = trim(
        (string)($_POST['status'] ?? '')
    );

    $allowed_statuses = [
        'Available',
        'Busy',
        'Offline'
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid availability status.'
        ]);
        exit;
    }

    $status_stmt = $conn->prepare(
        "UPDATE scrapcollector
         SET availability_status = ?
         WHERE collector_id = ?"
    );

    if (!$status_stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to prepare status query.'
        ]);
        exit;
    }

    $status_stmt->bind_param(
        "si",
        $status,
        $collector_id
    );

    $success = $status_stmt->execute();

    $status_stmt->close();

    echo json_encode([
        'success' => $success,
        'status' => $status,
        'message' => $success
            ? 'Availability status updated.'
            : 'Could not update availability status.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Get collector profile
|--------------------------------------------------------------------------
*/

$collector = [];

$collector_stmt = $conn->prepare(
    "SELECT
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
     LIMIT 1"
);

if ($collector_stmt) {
    $collector_stmt->bind_param(
        "i",
        $collector_id
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

$notification_stmt = $conn->prepare(
    "SELECT
        notification_id,
        title,
        message,
        is_read,
        created_at
     FROM notifications
     WHERE recipient_type = 'Collector'
       AND recipient_id = ?
     ORDER BY created_at DESC"
);

if ($notification_stmt) {
    $notification_stmt->bind_param(
        "i",
        $collector_id
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
                (int)($notification['is_read'] ?? 0) === 0
            ) {
                $unread_count++;
            }
        }
    }

    $notification_stmt->close();
}

/*
|--------------------------------------------------------------------------
| Get assigned requests
|--------------------------------------------------------------------------
*/

$requests = [];
$total_weight = 0.0;

$sql = "
    SELECT
        a.activity_id,
        a.user_id,
        a.collector_id,
        a.scrap_type,
        a.scrap_weight,
        a.scrap_image,
        a.pickup_address,
        a.pickup_pincode,
        a.preferred_pickup_date,
        a.pickup_time,
        a.request_date,
        a.status,
        a.qr_status,
        a.amount,
        u.name AS customer_name,
        u.phone AS customer_phone
    FROM activity AS a
    INNER JOIN user AS u
        ON a.user_id = u.user_id
    WHERE a.collector_id = ?
      AND a.status = 'Assigned'
    ORDER BY a.request_date DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(
        "Unable to prepare pickup query: " .
        e($conn->error)
    );
}

$stmt->bind_param(
    "i",
    $collector_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;

        $total_weight += (float)(
            $row['scrap_weight'] ?? 0
        );
    }
}

$stmt->close();

$total_count = count($requests);

/*
|--------------------------------------------------------------------------
| Scrap icon helper
|--------------------------------------------------------------------------
*/

function scrapIcon(string $scrap_type): string
{
    $type = strtolower($scrap_type);

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
        Assigned Requests | EcoScrap Collector
    </title>

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

        /*
        |--------------------------------------------------------------------------
        | Layout
        |--------------------------------------------------------------------------
        */

        .app {
    min-height: 100vh;
    width: 100%;
}


        .logo-mark {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 13px;
            color: var(--white);
            background:
                linear-gradient(
                    135deg,
                    var(--eco-light),
                    var(--eco-primary)
                );
            box-shadow:
                0 7px 18px rgba(46, 125, 50, 0.22);
            font-size: 24px;
        }

        .logo-name {
            color: var(--text-dark);
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .logo-subtitle {
            margin-top: 3px;
            color: #059669;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.13em;
        }

       

        

        /*
        |--------------------------------------------------------------------------
        | Main area
        |--------------------------------------------------------------------------
        */

        .main {
    width: 100%;
    min-width: 0;
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

        .availability {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 11px;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--muted);
            background: var(--white);
            font-size: 10px;
            font-weight: 700;
        }

        .availability-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--eco-light);
            box-shadow: 0 0 0 4px #e5f5e1;
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
        | Content
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
            max-width: 670px;
            margin-top: 8px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.55;
        }

        .status-toggle {
            display: flex;
            gap: 3px;
            padding: 4px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .status-toggle button {
            padding: 8px 10px;
            border: 0;
            border-radius: 8px;
            color: var(--muted);
            background: transparent;
            font-size: 10px;
            font-weight: 800;
        }

        .status-toggle button:hover {
            background: #f1f5f2;
        }

        .status-toggle .active-available {
            color: var(--white);
            background: var(--eco-primary);
        }

        .status-toggle .active-busy {
            color: var(--white);
            background: #d99014;
        }

        .status-toggle .active-offline {
            color: var(--white);
            background: #64748b;
        }

        /*
        |--------------------------------------------------------------------------
        | Metric cards
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
        | Filters
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
        | Request cards
        |--------------------------------------------------------------------------
        */

        .request-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .request-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 450px;
            padding: 19px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--white);
            box-shadow: var(--shadow-sm);
            transition: 0.25s ease;
        }

        .request-card:hover {
            border-color: #bfe2c2;
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .card-top {
            display: flex;
            align-items: center;
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

        .assigned-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 8px;
            border: 1px solid #f2d99d;
            border-radius: 999px;
            color: #a66d0a;
            background: #fff9eb;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .request-info {
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

        .request-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 11px;
            margin-top: 15px;
            padding-top: 13px;
            border-top: 1px dashed var(--border);
        }

        .weight-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 5px 7px;
            border-radius: 6px;
            color: var(--eco-primary);
            background: #e9f7e9;
            font-size: 10px;
            font-weight: 800;
        }

        .quick-actions {
            display: flex;
            gap: 7px;
            margin-top: 16px;
            padding-top: 13px;
            border-top: 1px dashed var(--border);
        }

        .quick-action {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            gap: 5px;
            min-height: 34px;
            border: 1px solid var(--border);
            border-radius: 9px;
            color: var(--muted);
            background: var(--white);
            font-size: 9px;
            font-weight: 800;
        }

        .quick-action:hover {
            color: var(--eco-primary);
            background: #f2faf2;
        }

        .card-actions {
            display: grid;
            gap: 7px;
            margin-top: 13px;
        }

        .start-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            width: 100%;
            min-height: 41px;
            border: 0;
            border-radius: 10px;
            color: var(--white);
            background:
                linear-gradient(
                    135deg,
                    var(--eco-primary),
                    #236128
                );
            box-shadow:
                0 7px 16px rgba(46, 125, 50, 0.2);
            font-size: 11px;
            font-weight: 800;
        }

        .start-button:hover {
            background: #256b29;
        }

        .reject-button {
            min-height: 36px;
            border: 1px solid #edb8b8;
            border-radius: 9px;
            color: #bd5555;
            background: #fff9f9;
            font-size: 10px;
            font-weight: 800;
        }

        .reject-button:hover {
            color: var(--white);
            background: #bd5555;
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
        | Modal
        |--------------------------------------------------------------------------
        */

        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 500;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 77, 64, 0.42);
            backdrop-filter: blur(5px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            width: min(100%, 430px);
            overflow: hidden;
            border-radius: 18px;
            background: var(--white);
            box-shadow: var(--shadow-md);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            color: var(--white);
            background:
                linear-gradient(
                    135deg,
                    var(--eco-dark),
                    var(--eco-primary)
                );
        }

        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 15px;
        }

        .modal-close {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 7px;
            color: var(--white);
            background: rgba(255, 255, 255, 0.14);
            font-size: 17px;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-description {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .modal-details {
            display: grid;
            gap: 10px;
            margin-top: 15px;
            padding: 14px;
            border-radius: 12px;
            background: #f6faf7;
        }

        .modal-detail {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 11px;
        }

        .modal-detail span {
            color: var(--muted);
        }

        .modal-detail strong {
            color: var(--text-dark);
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            margin-top: 17px;
        }

        .modal-actions button {
            flex: 1;
            min-height: 40px;
            border: 0;
            border-radius: 9px;
            font-size: 10px;
            font-weight: 800;
        }

        .cancel-button {
            color: var(--muted);
            background: #edf2ef;
        }

        .confirm-button {
            color: var(--white);
            background: var(--eco-primary);
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive layout
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1150px) {
            .metrics {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1000px) {
            .request-grid {
                grid-template-columns: 1fr;
            }

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
            .availability,
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

        @media (max-width: 480px) {
            .request-details {
                grid-template-columns: 1fr;
            }

            .card-top {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="app">

        

        <!-- Main content -->
        <main class="main">

            <!-- Header -->
            <header class="topbar">
                <div style="display: flex; align-items: center;">
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
                            Logged in as
                            <strong>
                                <?php echo e($collector_name); ?>
                            </strong>
                            · ID
                            <?php echo $collector_id; ?>
                        </div>
                    </div>
                </div>

                <div class="topbar-actions">
                    <div class="availability">
                        <span class="availability-dot"></span>

                        <span id="availabilityText">
                            <?php echo e($availability_status); ?>
                        </span>
                    </div>

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
                            Pickup Management
                        </div>

                        <h1 class="page-title">
                            Assigned Pickup Requests
                        </h1>

                        <p class="page-description">
                            Review customer details, pickup locations,
                            scrap weights, and manage your assigned jobs.
                        </p>
                    </div>

                    <div class="status-toggle">
                        <button
                            type="button"
                            id="statusAvailable"
                            onclick="updateAvailability('Available')"
                            class="<?php echo $availability_status === 'Available' ? 'active-available' : ''; ?>"
                        >
                            Available
                        </button>

                        <button
                            type="button"
                            id="statusBusy"
                            onclick="updateAvailability('Busy')"
                            class="<?php echo $availability_status === 'Busy' ? 'active-busy' : ''; ?>"
                        >
                            Busy
                        </button>

                        <button
                            type="button"
                            id="statusOffline"
                            onclick="updateAvailability('Offline')"
                            class="<?php echo $availability_status === 'Offline' ? 'active-offline' : ''; ?>"
                        >
                            Offline
                        </button>
                    </div>
                </div>

                <!-- Metrics -->
                <div class="metrics">
                    <div class="metric-card metric-green">
                        <div class="metric-top">
                            <span class="metric-label">
                                ASSIGNED REQUESTS
                            </span>

                            <span class="metric-icon">
                                <i class="ri-truck-line"></i>
                            </span>
                        </div>

                        <div class="metric-value">
                            <?php echo $total_count; ?>
                        </div>

                        <div class="metric-help">
                            Requests waiting for action
                        </div>
                    </div>

                    <div class="metric-card metric-blue">
                        <div class="metric-top">
                            <span class="metric-label">
                                ESTIMATED WEIGHT
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
                            Total scrap weight in queue
                        </div>
                    </div>

                    <div class="metric-card metric-amber">
                        <div class="metric-top">
                            <span class="metric-label">
                                TARGET PINCODE
                            </span>

                            <span class="metric-icon">
                                <i class="ri-map-pin-line"></i>
                            </span>
                        </div>

                        <div class="metric-value">
                            <?php echo e($collector_pincode); ?>
                        </div>

                        <div class="metric-help">
                            Assigned service area
                        </div>
                    </div>

                    <div class="metric-card metric-slate">
                        <div class="metric-top">
                            <span class="metric-label">
                                COMPLETED PICKUPS
                            </span>

                            <span class="metric-icon">
                                <i class="ri-checkbox-circle-line"></i>
                            </span>
                        </div>

                        <div class="metric-value">
                            <?php echo $completed_pickups; ?>
                        </div>

                        <div class="metric-help">
                            Verification:
                            <?php echo e($verification_status); ?>
                        </div>
                    </div>
                </div>

                <!-- Search and filters -->
                <div class="filter-panel">
                    <div class="search-wrapper">
                        <i class="ri-search-2-line"></i>

                        <input
                            type="text"
                            id="searchInput"
                            class="search-input"
                            placeholder="Search customer, phone, address, or scrap type..."
                            oninput="filterRequests()"
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

                <?php if (!empty($requests)): ?>
                    <div
                        id="requestGrid"
                        class="request-grid"
                    >
                        <?php foreach ($requests as $row): ?>
                            <?php
                            $activity_id =
                                (int)(
                                    $row['activity_id'] ?? 0
                                );

                            $scrap_type =
                                (string)(
                                    $row['scrap_type'] ??
                                    'General Scrap'
                                );

                            $customer_name =
                                (string)(
                                    $row['customer_name'] ??
                                    'Unknown Customer'
                                );

                            $customer_phone =
                                (string)(
                                    $row['customer_phone'] ??
                                    ''
                                );

                            $pickup_address =
                                (string)(
                                    $row['pickup_address'] ??
                                    'Address unavailable'
                                );

                            $pickup_pincode =
                                (string)(
                                    $row['pickup_pincode'] ??
                                    'N/A'
                                );

                            $scrap_weight =
                                (float)(
                                    $row['scrap_weight'] ??
                                    0
                                );

                            $pickup_date =
                                (string)(
                                    $row['preferred_pickup_date'] ??
                                    'Not specified'
                                );

                            $pickup_time =
                                (string)(
                                    $row['pickup_time'] ??
                                    ''
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
                                class="request-card request-item"
                                data-search="<?php echo e($search_text); ?>"
                                data-category="<?php echo e(strtolower($scrap_type)); ?>"
                            >
                                <div>
                                    <div class="card-top">
                                        <span class="material-badge">
                                            <i
                                                class="<?php echo e(
                                                    scrapIcon($scrap_type)
                                                ); ?>"
                                            ></i>

                                            <?php echo e($scrap_type); ?>
                                        </span>

                                        <span class="assigned-badge">
                                            <i class="ri-time-line"></i>
                                            Assigned
                                        </span>
                                    </div>

                                    <div class="request-info">
                                        <div class="info-row">
                                            <div class="info-icon">
                                                <i class="ri-user-3-line"></i>
                                            </div>

                                            <div>
                                                <span class="info-label">
                                                    Customer Name
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
                                                    Phone Contact
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

                                    <div class="request-details">
                                        <div class="info-row">
                                            <div class="info-icon">
                                                <i class="ri-scales-3-line"></i>
                                            </div>

                                            <div>
                                                <span class="info-label">
                                                    Scrap Weight
                                                </span>

                                                <span class="weight-badge">
                                                    <?php echo number_format($scrap_weight, 2); ?>
                                                    KG
                                                </span>
                                            </div>
                                        </div>

                                        <div class="info-row">
                                            <div class="info-icon">
                                                <i class="ri-calendar-event-line"></i>
                                            </div>

                                            <div>
                                                <span class="info-label">
                                                    Pickup Date
                                                </span>

                                                <span class="info-value">
                                                    <?php echo e($pickup_date); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="info-row">
                                            <div class="info-icon">
                                                <i class="ri-time-line"></i>
                                            </div>

                                            <div>
                                                <span class="info-label">
                                                    Pickup Time
                                                </span>

                                                <span class="info-value">
                                                    <?php
                                                    echo $pickup_time !== ''
                                                        ? e($pickup_time)
                                                        : 'Flexible';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="info-row">
                                            <div class="info-icon">
                                                <i class="ri-map-pin-line"></i>
                                            </div>

                                            <div>
                                                <span class="info-label">
                                                    Pincode
                                                </span>

                                                <span class="info-value">
                                                    <?php echo e($pickup_pincode); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="quick-actions">
                                        <?php if ($customer_phone !== ''): ?>
                                            <a
                                                href="tel:<?php echo e($customer_phone); ?>"
                                                class="quick-action"
                                            >
                                                <i class="ri-phone-fill"></i>
                                                Call
                                            </a>
                                        <?php endif; ?>

                                        <a
                                            href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($pickup_address); ?>"
                                            class="quick-action"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <i class="ri-direction-line"></i>
                                            Navigate
                                        </a>

                                        <a
                                            href="verify_qr.php?id=<?php echo $activity_id; ?>"
                                            class="quick-action"
                                        >
                                            <i class="ri-qr-scan-2-line"></i>
                                            QR
                                        </a>
                                    </div>
                                </div>

                                <div class="card-actions">
                                    <form
                                        id="pickupForm<?php echo $activity_id; ?>"
                                        action="accept_pickup.php"
                                        method="post"
                                    >
                                        <input
                                            type="hidden"
                                            name="activity_id"
                                            value="<?php echo $activity_id; ?>"
                                        >

                                        <button
                                            type="button"
                                            class="start-button"
                                            onclick="openStartModal(
                                                <?php echo $activity_id; ?>,
                                                '<?php echo e($customer_name); ?>',
                                                '<?php echo e($scrap_type); ?>',
                                                '<?php echo number_format($scrap_weight, 2); ?>'
                                            )"
                                        >
                                            <i class="ri-play-circle-fill"></i>
                                            Start Pickup Job
                                        </button>
                                    </form>

                                    <form
                                        action="reject_pickup.php"
                                        method="post"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to reject this pickup request?'
                                            );
                                        "
                                    >
                                        <input
                                            type="hidden"
                                            name="activity_id"
                                            value="<?php echo $activity_id; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="reject-button"
                                            style="width: 100%;"
                                        >
                                            <i class="ri-close-circle-line"></i>
                                            Reject Pickup
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="ri-inbox-2-line"></i>
                        </div>

                        <h3>
                            No Assigned Pickup Requests
                        </h3>

                        <p>
                            You currently have no pickup requests assigned
                            to your account.
                        </p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Start pickup modal -->
    <div
        id="startModal"
        class="modal-overlay"
    >
        <div class="modal">
            <div class="modal-header">
                <h3>
                    <i class="ri-truck-fill"></i>
                    Confirm Start Pickup
                </h3>

                <button
                    type="button"
                    class="modal-close"
                    onclick="closeStartModal()"
                >
                    <i class="ri-close-line"></i>
                </button>
            </div>

            <div class="modal-body">
                <p class="modal-description">
                    Confirm the pickup details before starting this job.
                </p>

                <div class="modal-details">
                    <div class="modal-detail">
                        <span>Customer</span>
                        <strong id="modalCustomer">
                            -
                        </strong>
                    </div>

                    <div class="modal-detail">
                        <span>Scrap Type</span>
                        <strong id="modalScrapType">
                            -
                        </strong>
                    </div>

                    <div class="modal-detail">
                        <span>Estimated Weight</span>
                        <strong id="modalWeight">
                            -
                        </strong>
                    </div>
                </div>

                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-button"
                        onclick="closeStartModal()"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="confirm-button"
                        onclick="submitPickupForm()"
                    >
                        <i class="ri-play-fill"></i>
                        Begin Job
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedCategory = 'all';
        let selectedActivityId = null;

        const sidebar =
            document.getElementById('sidebar');

        const notificationDropdown =
            document.getElementById(
                'notificationDropdown'
            );

        const startModal =
            document.getElementById('startModal');

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

            filterRequests();
        }

        function filterRequests() {
            const searchInput =
                document.getElementById(
                    'searchInput'
                );

            const searchValue =
                searchInput.value
                    .toLowerCase()
                    .trim();

            document
                .querySelectorAll('.request-item')
                .forEach(function (card) {
                    const cardSearch =
                        card.dataset.search || '';

                    const cardCategory =
                        card.dataset.category || '';

                    const searchMatches =
                        cardSearch.includes(
                            searchValue
                        );

                    const categoryMatches =
                        selectedCategory === 'all' ||
                        cardCategory.includes(
                            selectedCategory
                        );

                    card.style.display =
                        searchMatches &&
                        categoryMatches
                            ? ''
                            : 'none';
                });
        }

        function openStartModal(
            activityId,
            customer,
            scrapType,
            weight
        ) {
            selectedActivityId = activityId;

            document.getElementById(
                'modalCustomer'
            ).textContent = customer;

            document.getElementById(
                'modalScrapType'
            ).textContent = scrapType;

            document.getElementById(
                'modalWeight'
            ).textContent = weight + ' KG';

            startModal.classList.add('show');
        }

        function closeStartModal() {
            selectedActivityId = null;

            startModal.classList.remove(
                'show'
            );
        }

        function submitPickupForm() {
            if (!selectedActivityId) {
                return;
            }

            const form =
                document.getElementById(
                    'pickupForm' +
                    selectedActivityId
                );

            if (form) {
                form.submit();
            }
        }

        startModal.addEventListener(
            'click',
            function (event) {
                if (event.target === startModal) {
                    closeStartModal();
                }
            }
        );

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

            fetch('assigned_requests.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data.success) {
                        alert(
                            data.message ||
                            'Unable to update status.'
                        );

                        return;
                    }

                    document.getElementById(
                        'availabilityText'
                    ).textContent = data.status;

                    updateStatusButtons(data.status);
                })
                .catch(function (error) {
                    console.error(error);

                    alert(
                        'Could not update availability status.'
                    );
                });
        }

        function updateStatusButtons(status) {
            const available =
                document.getElementById(
                    'statusAvailable'
                );

            const busy =
                document.getElementById(
                    'statusBusy'
                );

            const offline =
                document.getElementById(
                    'statusOffline'
                );

            available.className = '';
            busy.className = '';
            offline.className = '';

            if (status === 'Available') {
                available.className =
                    'active-available';
            }

            if (status === 'Busy') {
                busy.className =
                    'active-busy';
            }

            if (status === 'Offline') {
                offline.className =
                    'active-offline';
            }
        }
    </script>
</body>
</html>