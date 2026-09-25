<?php
// admin/reports.php

session_start();

if (
    !isset($_SESSION['admin_id']) ||
    ($_SESSION['role'] ?? '') !== 'Admin'
) {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db.php';

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function getCount(mysqli $conn, string $query): int
{
    $result = $conn->query($query);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int)($row['total'] ?? 0);
}

function statusClass(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {
        'completed',
        'approved',
        'available' => 'status-success',

        'pending',
        'assigned',
        'accepted',
        'picked up',
        'in progress',
        'busy' => 'status-warning',

        'cancelled',
        'rejected',
        'unavailable',
        'offline' => 'status-danger',

        default => 'status-neutral'
    };
}

/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/

$totalUsers = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM `user`"
);

$totalCollectors = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM scrapcollector"
);

$approvedCollectors = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM scrapcollector
     WHERE verification_status = 'Approved'"
);

$totalActivities = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM activity"
);

$completedPickups = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM activity
     WHERE status = 'Completed'"
);

$pendingPickups = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM activity
     WHERE status = 'Pending'"
);

$cancelledPickups = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM activity
     WHERE status = 'Cancelled'"
);

$inProgressPickups = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM activity
     WHERE status IN (
         'Assigned',
         'Accepted',
         'Picked Up',
         'In Progress'
     )"
);

$completionRate = $totalActivities > 0
    ? round(($completedPickups / $totalActivities) * 100)
    : 0;

$approvalRate = $totalCollectors > 0
    ? round(($approvedCollectors / $totalCollectors) * 100)
    : 0;

/*
|--------------------------------------------------------------------------
| COLLECTORS
|--------------------------------------------------------------------------
*/

$collectorsResult = $conn->query("
    SELECT
        collector_id,
        name,
        email,
        phone,
        vehicle_no,
        pincode,
        availability_status,
        verification_status,
        completed_pickups,
        created_at
    FROM scrapcollector
    ORDER BY created_at DESC
");

/*
|--------------------------------------------------------------------------
| RECENT ACTIVITIES
|--------------------------------------------------------------------------
*/

$recentActivitiesResult = $conn->query("
    SELECT
        a.activity_id,
        u.name AS user_name,
        c.name AS collector_name,
        a.scrap_type,
        a.status,
        a.request_date AS activity_date
    FROM activity a
    LEFT JOIN `user` u
        ON a.user_id = u.user_id
    LEFT JOIN scrapcollector c
        ON a.collector_id = c.collector_id
    ORDER BY a.request_date DESC
    LIMIT 10
");

/*
|--------------------------------------------------------------------------
| MONTHLY ACTIVITY DATA
|--------------------------------------------------------------------------
*/

$monthlyLabels = [];
$monthlyValues = [];

$monthlyResult = $conn->query("
    SELECT
        DATE_FORMAT(request_date, '%b') AS month_name,
        COUNT(*) AS total
    FROM activity
    WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY
        YEAR(request_date),
        MONTH(request_date),
        DATE_FORMAT(request_date, '%b')
    ORDER BY
        YEAR(request_date),
        MONTH(request_date)
");

if ($monthlyResult) {
    while ($month = $monthlyResult->fetch_assoc()) {
        $monthlyLabels[] = $month['month_name'];
        $monthlyValues[] = (int)$month['total'];
    }
}

if (empty($monthlyLabels)) {
    $monthlyLabels = ['No Data'];
    $monthlyValues = [0];
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

    <title>Reports & Analytics | EcoScrap Admin</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0"></script>

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
            --blue: #2563eb;
            --purple: #7c3aed;
            --shadow: 0 12px 30px rgba(20, 83, 45, .07);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system,
                BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .page-wrapper {
            width: min(1440px, calc(100% - 40px));
            margin: 0 auto;
            padding: 28px 0 50px;
        }

        .top-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .header-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 17px;
            padding: 9px 14px;
            color: var(--primary-dark);
            background: #f0fdf4;
            border: 1px solid #bbebc8;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            transition: all .2s ease;
        }

        .back-button i {
            font-size: 13px;
        }

        .back-button:hover {
            color: #ffffff;
            background: var(--primary);
            border-color: var(--primary);
            transform: translateX(-3px);
        }

        .eyebrow {
            margin: 0 0 8px;
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        h1,
        h2,
        h3,
        p {
            margin-top: 0;
        }

        h1 {
            margin-bottom: 7px;
            font-size: clamp(26px, 4vw, 38px);
            letter-spacing: -.04em;
        }

        .header-description {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 12px 17px;
            border: 0;
            border-radius: 12px;
            font-weight: 750;
            cursor: pointer;
            text-decoration: none;
            transition: .2s ease;
        }

        .button-primary {
            color: white;
            background: var(--primary);
            box-shadow: 0 9px 20px rgba(22, 163, 74, .22);
        }

        .button-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .button-light {
            color: var(--text);
            background: white;
            border: 1px solid var(--border);
        }

        .button-light:hover {
            color: var(--primary);
            border-color: var(--primary);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 22px;
        }

        .metric-card {
            position: relative;
            overflow: hidden;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .metric-card::after {
            position: absolute;
            right: -35px;
            bottom: -45px;
            width: 125px;
            height: 125px;
            content: "";
            background: var(--primary-light);
            border-radius: 50%;
            opacity: .6;
        }

        .metric-top {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
        }

        .metric-label {
            margin-bottom: 9px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .metric-value {
            margin-bottom: 7px;
            font-size: 32px;
            line-height: 1;
            letter-spacing: -.05em;
        }

        .metric-note {
            color: var(--muted);
            font-size: 12px;
        }

        .metric-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            flex: 0 0 auto;
            border-radius: 15px;
            font-size: 20px;
        }

        .icon-green {
            color: var(--primary);
            background: var(--primary-light);
        }

        .icon-blue {
            color: var(--blue);
            background: #dbeafe;
        }

        .icon-purple {
            color: var(--purple);
            background: #ede9fe;
        }

        .icon-orange {
            color: var(--warning);
            background: #ffedd5;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.35fr .9fr;
            gap: 20px;
            margin-bottom: 22px;
        }

        .card {
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
            font-size: 18px;
        }

        .card-title i {
            color: var(--primary);
        }

        .card-subtitle {
            margin-bottom: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .chart-container {
            position: relative;
            height: 285px;
        }

        .impact-content {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .impact-chart {
            width: 205px;
            height: 205px;
            flex: 0 0 205px;
        }

        .impact-stat strong {
            display: block;
            color: var(--primary);
            font-size: 46px;
            line-height: 1;
            letter-spacing: -.06em;
        }

        .impact-stat span {
            display: block;
            margin-top: 9px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .impact-list {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }

        .impact-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--muted);
            font-size: 13px;
        }

        .impact-row strong {
            color: var(--text);
        }

        .legend-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            margin-right: 7px;
            border-radius: 50%;
        }

        .green-dot {
            background: var(--primary);
        }

        .orange-dot {
            background: var(--warning);
        }

        .red-dot {
            background: var(--danger);
        }

        .progress-section {
            margin-top: 22px;
        }

        .progress-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .progress-bar {
            width: 100%;
            height: 9px;
            overflow: hidden;
            background: #edf2ef;
            border-radius: 20px;
        }

        .progress-value {
            height: 100%;
            background: linear-gradient(
                90deg,
                var(--primary),
                #4ade80
            );
            border-radius: inherit;
        }

        .full-width {
            margin-bottom: 22px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th {
            padding: 13px 12px;
            color: var(--muted);
            background: #f7faf8;
            font-size: 11px;
            font-weight: 850;
            letter-spacing: .07em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #eef2ef;
            color: #526158;
            font-size: 13px;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #fbfefc;
        }

        .person-name {
            color: var(--text);
            font-weight: 750;
        }

        .small-text {
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-success {
            color: #15803d;
            background: #dcfce7;
        }

        .status-warning {
            color: #b45309;
            background: #fef3c7;
        }

        .status-danger {
            color: #b91c1c;
            background: #fee2e2;
        }

        .status-neutral {
            color: #475569;
            background: #f1f5f9;
        }

        .empty-state {
            padding: 30px !important;
            color: var(--muted);
            text-align: center;
        }

        @media (max-width: 1050px) {
            .metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 620px) {
            .page-wrapper {
                width: min(100% - 24px, 1440px);
                padding-top: 18px;
            }

            .top-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .header-content {
                width: 100%;
            }

            .header-actions,
            .header-actions .button {
                width: 100%;
            }

            .header-actions .button {
                justify-content: center;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .card,
            .metric-card {
                padding: 17px;
                border-radius: 16px;
            }

            .impact-content {
                align-items: flex-start;
                flex-direction: column;
            }

            .impact-chart {
                align-self: center;
            }
        }

        @media print {
            body {
                background: white;
            }

            .page-wrapper {
                width: 100%;
                padding: 0;
            }

            .back-button,
            .header-actions,
            .button {
                display: none !important;
            }

            .card,
            .metric-card {
                box-shadow: none;
                break-inside: avoid;
            }
        }

    </style>

</head>

<body>

<main class="page-wrapper">

    <header class="top-header">

        <div class="header-content">

            <a href="dashboard.php" class="back-button">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Dashboard
            </a>

            <p class="eyebrow">
                EcoScrap administration
            </p>

            <h1>
                Reports & Analytics
            </h1>

            <p class="header-description">
                Monitor users, collectors, pickup activity,
                and environmental impact.
            </p>

        </div>

        <div class="header-actions">

            <button
                class="button button-light"
                onclick="window.location.reload()"
            >
                <i class="fa-solid fa-rotate"></i>
                Refresh
            </button>

            <button
                class="button button-primary"
                onclick="window.print()"
            >
                <i class="fa-solid fa-print"></i>
                Print Report
            </button>

        </div>

    </header>

    <section class="metrics-grid">

        <article class="metric-card">

            <div class="metric-top">

                <div>
                    <div class="metric-label">
                        Total users
                    </div>

                    <h2 class="metric-value">
                        <?= number_format($totalUsers) ?>
                    </h2>

                    <div class="metric-note">
                        <i class="fa-solid fa-arrow-up"></i>
                        Registered platform users
                    </div>
                </div>

                <div class="metric-icon icon-blue">
                    <i class="fa-solid fa-users"></i>
                </div>

            </div>

        </article>

        <article class="metric-card">

            <div class="metric-top">

                <div>
                    <div class="metric-label">
                        Collectors
                    </div>

                    <h2 class="metric-value">
                        <?= number_format($totalCollectors) ?>
                    </h2>

                    <div class="metric-note">
                        <?= number_format($approvedCollectors) ?>
                        verified collectors
                    </div>
                </div>

                <div class="metric-icon icon-green">
                    <i class="fa-solid fa-truck-pickup"></i>
                </div>

            </div>

        </article>

        <article class="metric-card">

            <div class="metric-top">

                <div>
                    <div class="metric-label">
                        Pickup requests
                    </div>

                    <h2 class="metric-value">
                        <?= number_format($totalActivities) ?>
                    </h2>

                    <div class="metric-note">
                        <?= number_format($pendingPickups) ?>
                        currently pending
                    </div>
                </div>

                <div class="metric-icon icon-purple">
                    <i class="fa-solid fa-recycle"></i>
                </div>

            </div>

        </article>

        <article class="metric-card">

            <div class="metric-top">

                <div>
                    <div class="metric-label">
                        Completed pickups
                    </div>

                    <h2 class="metric-value">
                        <?= number_format($completedPickups) ?>
                    </h2>

                    <div class="metric-note">
                        <?= $completionRate ?>% completion rate
                    </div>
                </div>

                <div class="metric-icon icon-orange">
                    <i class="fa-solid fa-circle-check"></i>
                </div>

            </div>

        </article>

    </section>

    <section class="dashboard-grid">

        <article class="card">

            <div class="card-header">

                <div>
                    <h2 class="card-title">
                        <i class="fa-solid fa-chart-column"></i>
                        Pickup activity
                    </h2>

                    <p class="card-subtitle">
                        Requests recorded over the recent six-month period.
                    </p>
                </div>

            </div>

            <div class="chart-container">
                <canvas id="activityChart"></canvas>
            </div>

        </article>

        <article class="card">

            <div class="card-header">

                <div>
                    <h2 class="card-title">
                        <i class="fa-solid fa-leaf"></i>
                        Environmental impact
                    </h2>

                    <p class="card-subtitle">
                        Current pickup completion performance.
                    </p>
                </div>

            </div>

            <div class="impact-content">

                <div class="impact-chart">
                    <canvas id="impactChart"></canvas>
                </div>

                <div class="impact-stat">

                    <strong>
                        <?= $completionRate ?>%
                    </strong>

                    <span>
                        of pickup requests have been successfully completed.
                    </span>

                </div>

            </div>

            <div class="impact-list">

                <div class="impact-row">
                    <span>
                        <span class="legend-dot green-dot"></span>
                        Completed
                    </span>

                    <strong>
                        <?= number_format($completedPickups) ?>
                    </strong>
                </div>

                <div class="impact-row">
                    <span>
                        <span class="legend-dot orange-dot"></span>
                        Pending
                    </span>

                    <strong>
                        <?= number_format($pendingPickups) ?>
                    </strong>
                </div>

                <div class="impact-row">
                    <span>
                        <span class="legend-dot red-dot"></span>
                        Cancelled
                    </span>

                    <strong>
                        <?= number_format($cancelledPickups) ?>
                    </strong>
                </div>

            </div>

            <div class="progress-section">

                <div class="progress-title">
                    <span>Collector verification</span>
                    <strong><?= $approvalRate ?>%</strong>
                </div>

                <div class="progress-bar">
                    <div
                        class="progress-value"
                        style="width: <?= $approvalRate ?>%"
                    ></div>
                </div>

            </div>

        </article>

    </section>

    <section class="card full-width">

        <div class="card-header">

            <div>
                <h2 class="card-title">
                    <i class="fa-solid fa-id-card"></i>
                    Registered collectors
                </h2>

                <p class="card-subtitle">
                    Collector verification, availability,
                    and performance summary.
                </p>
            </div>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Collector</th>
                        <th>Contact</th>
                        <th>Vehicle</th>
                        <th>Pincode</th>
                        <th>Availability</th>
                        <th>Verification</th>
                        <th>Completed</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    $collectorsResult &&
                    $collectorsResult->num_rows > 0
                ): ?>

                    <?php while (
                        $collector = $collectorsResult->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                #<?= e($collector['collector_id']) ?>
                            </td>

                            <td>

                                <div class="person-name">
                                    <?= e($collector['name']) ?>
                                </div>

                                <div class="small-text">
                                    Joined
                                    <?php
                                    echo e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $collector['created_at']
                                            )
                                        )
                                    );
                                    ?>
                                </div>

                            </td>

                            <td>

                                <?= e($collector['email']) ?>

                                <div class="small-text">
                                    <?= e($collector['phone']) ?>
                                </div>

                            </td>

                            <td>
                                <?= e($collector['vehicle_no']) ?>
                            </td>

                            <td>
                                <?= e($collector['pincode']) ?>
                            </td>

                            <td>

                                <span
                                    class="status
                                    <?= e(
                                        statusClass(
                                            $collector[
                                                'availability_status'
                                            ]
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        $collector[
                                            'availability_status'
                                        ]
                                    ) ?>
                                </span>

                            </td>

                            <td>

                                <span
                                    class="status
                                    <?= e(
                                        statusClass(
                                            $collector[
                                                'verification_status'
                                            ]
                                        )
                                    ) ?>"
                                >
                                    <?= e(
                                        $collector[
                                            'verification_status'
                                        ]
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <strong>
                                    <?= e(
                                        $collector['completed_pickups']
                                    ) ?>
                                </strong>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty-state">
                            No collectors found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <section class="card full-width">

        <div class="card-header">

            <div>
                <h2 class="card-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Recent activity logs
                </h2>

                <p class="card-subtitle">
                    The latest pickup requests submitted on the platform.
                </p>
            </div>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Activity ID</th>
                        <th>User</th>
                        <th>Collector</th>
                        <th>Scrap type</th>
                        <th>Status</th>
                        <th>Request date</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    $recentActivitiesResult &&
                    $recentActivitiesResult->num_rows > 0
                ): ?>

                    <?php while (
                        $activity = $recentActivitiesResult->fetch_assoc()
                    ): ?>

                        <?php
                        $activityStatus =
                            $activity['status'] ?? 'Pending';
                        ?>

                        <tr>

                            <td>
                                #<?= e($activity['activity_id']) ?>
                            </td>

                            <td>
                                <span class="person-name">
                                    <?= e(
                                        $activity['user_name']
                                        ?? 'N/A'
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(
                                    $activity['collector_name']
                                    ?? 'Unassigned'
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $activity['scrap_type']
                                    ?? 'General'
                                ) ?>
                            </td>

                            <td>

                                <span
                                    class="status
                                    <?= e(
                                        statusClass($activityStatus)
                                    ) ?>"
                                >
                                    <?= e($activityStatus) ?>
                                </span>

                            </td>

                            <td>
                                <?= e(
                                    $activity['activity_date']
                                    ?? '—'
                                ) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty-state">
                            No activity history found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

<script>

const green = '#16a34a';
const orange = '#d97706';
const red = '#dc2626';
const gridColor = '#e6ece8';
const textColor = '#718078';

const activityLabels =
    <?= json_encode($monthlyLabels) ?>;

const activityValues =
    <?= json_encode($monthlyValues) ?>;

new Chart(
    document.getElementById('activityChart'),
    {
        type: 'bar',

        data: {
            labels: activityLabels,

            datasets: [
                {
                    label: 'Pickup requests',
                    data: activityValues,
                    backgroundColor: green,
                    borderRadius: 9,
                    borderSkipped: false,
                    maxBarThickness: 42
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
                },

                tooltip: {
                    backgroundColor: '#17221b',
                    padding: 12,
                    displayColors: false
                }
            },

            scales: {
                x: {
                    grid: {
                        display: false
                    },

                    ticks: {
                        color: textColor
                    }
                },

                y: {
                    beginAtZero: true,

                    ticks: {
                        precision: 0,
                        color: textColor
                    },

                    grid: {
                        color: gridColor
                    }
                }
            }
        }
    }
);

new Chart(
    document.getElementById('impactChart'),
    {
        type: 'doughnut',

        data: {
            labels: [
                'Completed',
                'Pending',
                'Cancelled'
            ],

            datasets: [
                {
                    data: [
                        <?= $completedPickups ?>,
                        <?= $pendingPickups ?>,
                        <?= $cancelledPickups ?>
                    ],

                    backgroundColor: [
                        green,
                        orange,
                        red
                    ],

                    borderWidth: 0,
                    hoverOffset: 6
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',

            plugins: {
                legend: {
                    display: false
                },

                tooltip: {
                    backgroundColor: '#17221b',
                    padding: 12
                }
            }
        }
    }
);

</script>

</body>
</html>