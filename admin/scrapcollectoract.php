<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../includes/db.php';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| COLLECTOR COUNTS
|--------------------------------------------------------------------------
*/

$totalCollectors = 0;
$availableCollectors = 0;
$busyCollectors = 0;
$offlineCollectors = 0;
$pendingCollectors = 0;

$countQuery = "
    SELECT
        COUNT(*) AS total,

        SUM(
            CASE
                WHEN availability_status = 'Available'
                THEN 1 ELSE 0
            END
        ) AS available,

        SUM(
            CASE
                WHEN availability_status = 'Busy'
                THEN 1 ELSE 0
            END
        ) AS busy,

        SUM(
            CASE
                WHEN availability_status = 'Offline'
                THEN 1 ELSE 0
            END
        ) AS offline,

        SUM(
            CASE
                WHEN verification_status = 'Pending'
                THEN 1 ELSE 0
            END
        ) AS pending

    FROM scrapcollector
";

$countResult = $conn->query($countQuery);

if ($countResult) {
    $count = $countResult->fetch_assoc();

    $totalCollectors = (int)($count['total'] ?? 0);
    $availableCollectors = (int)($count['available'] ?? 0);
    $busyCollectors = (int)($count['busy'] ?? 0);
    $offlineCollectors = (int)($count['offline'] ?? 0);
    $pendingCollectors = (int)($count['pending'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| COLLECTOR LIST WITH LATEST PICKUP
|--------------------------------------------------------------------------
|
| This returns only the latest active pickup for each collector.
|
*/

$collectorQuery = "
    SELECT
        c.collector_id,
        c.name,
        c.email,
        c.phone,
        c.pincode,
        c.availability_status,
        c.verification_status,

        a.activity_id,
        a.scrap_type,
        a.scrap_weight,
        a.pickup_address,
        a.pickup_pincode,
        a.preferred_pickup_date,
        a.pickup_time,
        a.status AS pickup_status

    FROM scrapcollector c

    LEFT JOIN activity a
        ON a.activity_id = (
            SELECT a2.activity_id
            FROM activity a2
            WHERE a2.collector_id = c.collector_id
              AND a2.status IN (
                  'Pending',
                  'Approved',
                  'Assigned',
                  'Accepted',
                  'Picked Up',
                  'In Progress',
                  'Completed'
              )
            ORDER BY a2.activity_id DESC
            LIMIT 1
        )

    ORDER BY
        CASE
            WHEN c.availability_status = 'Busy' THEN 1
            WHEN c.availability_status = 'Available' THEN 2
            WHEN c.availability_status = 'Offline' THEN 3
            ELSE 4
        END,

        c.name ASC
";

$collectorResult = $conn->query($collectorQuery);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Scrap Collectors | EcoScrap Admin</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

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
            --shadow: 0 12px 30px rgba(20, 83, 45, .07);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--background);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system,
                "Segoe UI", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .page-container {
            max-width: 1450px;
            margin: 0 auto;
            padding: 30px 20px 50px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .page-title h1 {
            margin: 0;
            font-size: clamp(25px, 3vw, 34px);
            line-height: 1.15;
            letter-spacing: -.8px;
        }

        .page-title h1 span {
            color: var(--primary);
        }

        .page-title p {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .date-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 14px;
            color: #557062;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 12px;
            white-space: nowrap;
        }

        .date-chip i {
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            position: relative;
            min-height: 115px;
            display: flex;
            align-items: center;
            gap: 13px;
            overflow: hidden;
            padding: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 17px;
            box-shadow: var(--shadow);
        }

        .stat-card::after {
            position: absolute;
            right: -22px;
            bottom: -28px;
            width: 85px;
            height: 85px;
            content: "";
            background: var(--primary-light);
            border-radius: 50%;
            opacity: .55;
        }

        .stat-icon {
            position: relative;
            z-index: 1;
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: var(--primary-dark);
            background: var(--primary-light);
            border-radius: 13px;
            font-size: 20px;
        }

        .stat-card.warning .stat-icon {
            color: var(--warning);
            background: #fff7ed;
        }

        .stat-card.busy .stat-icon {
            color: #ea580c;
            background: #ffedd5;
        }

        .stat-card.offline .stat-icon {
            color: #64748b;
            background: #f1f5f9;
        }

        .stat-card span {
            display: block;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .stat-card strong {
            display: block;
            color: #193022;
            font-size: 27px;
            line-height: 1;
        }

        .dashboard-section {
            margin-bottom: 25px;
            padding: 24px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .section-header h2 {
            margin: 0;
            color: #203126;
            font-size: 17px;
        }

        .section-header p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
        }

        .search-box i {
            position: absolute;
            top: 50%;
            left: 14px;
            transform: translateY(-50%);
            color: #8b9891;
        }

        .search-box input,
        .filters select {
            width: 100%;
            height: 44px;
            padding: 0 14px;
            background: #fbfcfb;
            border: 1px solid var(--border);
            border-radius: 11px;
            outline: none;
            color: var(--text);
        }

        .search-box input {
            padding-left: 40px;
        }

        .search-box input:focus,
        .filters select:focus {
            border-color: #9bd3aa;
            box-shadow: 0 0 0 3px #dcfce7;
        }

        .filters select {
            width: 170px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .collector-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1250px;
        }

        .collector-table th {
            padding: 13px 14px;
            text-align: left;
            color: #7b8981;
            background: #f8faf9;
            border-bottom: 1px solid var(--border);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .collector-table td {
            padding: 16px 14px;
            border-bottom: 1px solid #edf1ee;
            vertical-align: middle;
            font-size: 13px;
        }

        .collector-table tr:last-child td {
            border-bottom: 0;
        }

        .collector-table tbody tr:hover {
            background: #fbfdfb;
        }

        .collector-info {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .collector-avatar {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            color: var(--primary-dark);
            background: var(--primary-light);
            border-radius: 50%;
            font-weight: 800;
        }

        .collector-name strong {
            display: block;
            color: #203126;
            font-size: 13px;
        }

        .collector-name span {
            display: block;
            margin-top: 3px;
            color: #96a29b;
            font-size: 10px;
        }

        .contact-info strong {
            display: block;
            font-size: 12px;
        }

        .contact-info span {
            display: block;
            max-width: 180px;
            margin-top: 3px;
            overflow: hidden;
            color: var(--muted);
            font-size: 11px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .status-available {
            color: #15803d;
            background: #dcfce7;
        }

        .status-available .status-dot {
            background: #22c55e;
        }

        .status-busy {
            color: #c2410c;
            background: #ffedd5;
        }

        .status-busy .status-dot {
            background: #f97316;
        }

        .status-offline {
            color: #64748b;
            background: #f1f5f9;
        }

        .status-offline .status-dot {
            background: #94a3b8;
        }

        .status-pending {
            color: #b45309;
            background: #fef3c7;
        }

        .status-pending .status-dot {
            background: #f59e0b;
        }

        .pickup-card {
            min-width: 410px;
            padding: 11px 12px;
            background: #f7fcf8;
            border: 1px solid #dcefe1;
            border-radius: 11px;
        }

        .pickup-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 7px;
        }

        .pickup-id {
            color: var(--primary-dark);
            font-size: 11px;
            font-weight: 800;
        }

        .pickup-type {
            color: #627269;
            font-size: 10px;
        }

        .pickup-address {
            display: flex;
            gap: 7px;
            color: #52635a;
            font-size: 11px;
            line-height: 1.4;
        }

        .pickup-address i {
            margin-top: 2px;
            color: var(--primary);
        }

        .no-pickup {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #9aa69f;
            font-size: 11px;
        }

        /*
        |--------------------------------------------------------------------------
        | PICKUP ROADMAP
        |--------------------------------------------------------------------------
        */

        .pickup-roadmap {
            min-width: 390px;
            margin-top: 12px;
            padding: 14px 12px 11px;
            background: #ffffff;
            border: 1px solid #dcefe1;
            border-radius: 12px;
        }

        .roadmap-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }

        .roadmap-title strong {
            color: #294033;
            font-size: 11px;
        }

        .roadmap-current {
            color: #15803d;
            font-size: 10px;
            font-weight: 800;
        }

        .roadmap-steps {
            display: flex;
            align-items: flex-start;
            width: 100%;
        }

        .roadmap-step {
            position: relative;
            flex: 1;
            min-width: 0;
            text-align: center;
        }

        .roadmap-step:not(:last-child)::after {
            position: absolute;
            top: 10px;
            left: 50%;
            z-index: 0;
            width: 100%;
            height: 3px;
            content: "";
            background: #e5ebe7;
        }

        .roadmap-step.completed:not(:last-child)::after {
            background: #22c55e;
        }

        .roadmap-step.active:not(:last-child)::after {
            background: linear-gradient(
                to right,
                #22c55e 0 50%,
                #e5ebe7 50% 100%
            );
        }

        .roadmap-node {
            position: relative;
            z-index: 1;
            width: 21px;
            height: 21px;
            display: grid;
            place-items: center;
            margin: 0 auto 7px;
            color: #94a3a8;
            background: #ffffff;
            border: 2px solid #d8e1db;
            border-radius: 50%;
            font-size: 9px;
        }

        .roadmap-step.completed .roadmap-node {
            color: #ffffff;
            background: #22c55e;
            border-color: #22c55e;
        }

        .roadmap-step.active .roadmap-node {
            color: #15803d;
            background: #f0fdf4;
            border-color: #16a34a;
            box-shadow: 0 0 0 4px #dcfce7;
        }

        .roadmap-label {
            display: block;
            padding: 0 2px;
            color: #9aa69f;
            font-size: 8px;
            line-height: 1.25;
            word-break: break-word;
        }

        .roadmap-step.completed .roadmap-label,
        .roadmap-step.active .roadmap-label {
            color: #41604d;
            font-weight: 700;
        }

        .roadmap-step.active .roadmap-label {
            color: #15803d;
        }

        .pickup-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 11px;
            padding: 5px 8px;
            color: #15803d;
            background: #dcfce7;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 800;
        }

        .pickup-status-badge i {
            font-size: 8px;
        }

        .action-buttons {
            display: flex;
            gap: 7px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 34px;
            padding: 0 11px;
            border: 1px solid var(--border);
            background: #ffffff;
            border-radius: 9px;
            color: #52635a;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s ease;
        }

        .action-btn:hover {
            color: var(--primary-dark);
            border-color: #a9d9b6;
            background: #f3fbf5;
        }

        .action-btn.track {
            color: #15803d;
            background: #f0fdf4;
            border-color: #bbebc8;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #8b9891;
        }

        .empty-state i {
            display: block;
            margin-bottom: 12px;
            font-size: 36px;
            color: #b8c6bd;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 5px;
            color: #52635a;
        }

        .empty-state p {
            margin: 0;
            font-size: 12px;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 850px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 600px) {
            .page-container {
                padding: 22px 12px 40px;
            }

            .dashboard-section {
                padding: 16px 12px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filters select {
                width: 100%;
            }

            .pickup-card {
                min-width: 390px;
            }

            .pickup-roadmap {
                min-width: 360px;
            }
        }

    </style>

</head>

<body>

<div class="page-container">

    <div class="page-header">

        <div class="page-title">
            <h1>Scrap <span>Collectors</span></h1>
            <p>
                Monitor collector availability and current pickup activities.
            </p>
        </div>

        <div class="date-chip">
            <i class="fa-regular fa-calendar"></i>
            <?php echo date('d M Y'); ?>
        </div>

    </div>

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-truck"></i>
            </div>

            <div>
                <span>Total Collectors</span>
                <strong><?php echo $totalCollectors; ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div>
                <span>Available</span>
                <strong><?php echo $availableCollectors; ?></strong>
            </div>
        </div>

        <div class="stat-card busy">
            <div class="stat-icon">
                <i class="fa-solid fa-route"></i>
            </div>

            <div>
                <span>Ongoing Pickup</span>
                <strong><?php echo $busyCollectors; ?></strong>
            </div>
        </div>

        <div class="stat-card offline">
            <div class="stat-icon">
                <i class="fa-solid fa-power-off"></i>
            </div>

            <div>
                <span>Offline</span>
                <strong><?php echo $offlineCollectors; ?></strong>
            </div>
        </div>

        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fa-solid fa-user-clock"></i>
            </div>

            <div>
                <span>Pending Approval</span>
                <strong><?php echo $pendingCollectors; ?></strong>
            </div>
        </div>

    </div>

    <section class="dashboard-section">

        <div class="section-header">
            <div>
                <h2>Collector Monitoring</h2>
                <p>
                    View collector availability and pickup progress.
                </p>
            </div>
        </div>

        <div class="filters">

            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    id="collectorSearch"
                    placeholder="Search collector, phone or pincode..."
                >
            </div>

            <select id="statusFilter">
                <option value="all">All Status</option>
                <option value="available">Available</option>
                <option value="busy">Ongoing Pickup</option>
                <option value="offline">Offline</option>
                <option value="pending">Pending Approval</option>
            </select>

        </div>

        <div class="table-wrapper">

            <table class="collector-table">

                <thead>
                    <tr>
                        <th>Scrap Collector</th>
                        <th>Contact</th>
                        <th>Service Area</th>
                        <th>Status</th>
                        <th>Pickup Roadmap</th>
                        <th>Pickup Location</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="collectorTableBody">

                <?php if ($collectorResult && $collectorResult->num_rows > 0): ?>

                    <?php while ($collector = $collectorResult->fetch_assoc()): ?>

                        <?php
                        $availability = strtolower(
                            trim($collector['availability_status'] ?? 'Offline')
                        );

                        $approval = strtolower(
                            trim($collector['verification_status'] ?? '')
                        );

                        $hasPickup = !empty($collector['activity_id']);

                        if ($approval === 'pending') {
                            $displayStatus = 'pending';
                            $statusLabel = 'Pending Approval';
                        } elseif ($hasPickup || $availability === 'busy') {
                            $displayStatus = 'busy';
                            $statusLabel = 'Ongoing Pickup';
                        } elseif ($availability === 'available') {
                            $displayStatus = 'available';
                            $statusLabel = 'Available';
                        } else {
                            $displayStatus = 'offline';
                            $statusLabel = 'Offline';
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | PICKUP ROADMAP STATUS
                        |--------------------------------------------------------------------------
                        */

                        $pickupStatus = trim(
                            $collector['pickup_status'] ?? 'Pending'
                        );

                        $pickupSteps = [
                            'Pending',
                            'Approved',
                            'Assigned',
                            'Accepted',
                            'Picked Up',
                            'In Progress',
                            'Completed'
                        ];

                        $statusAliases = [
                            'Pending' => 'Pending',
                            'Approved' => 'Approved',
                            'Assigned' => 'Assigned',
                            'Accepted' => 'Accepted',
                            'Picked Up' => 'Picked Up',
                            'In Progress' => 'In Progress',
                            'Completed' => 'Completed',
                            'Collected' => 'Picked Up',
                            'Delivered' => 'Completed'
                        ];

                        $normalizedPickupStatus =
                            $statusAliases[$pickupStatus] ?? $pickupStatus;

                        $currentPickupStep = array_search(
                            $normalizedPickupStatus,
                            $pickupSteps,
                            true
                        );

                        if ($currentPickupStep === false) {
                            $currentPickupStep = 0;
                        }

                        $name = $collector['name'] ?? 'Unknown Collector';
                        $initial = strtoupper(
                            substr(trim($name), 0, 1)
                        );
                        ?>

                        <tr
                            data-name="<?php echo e(strtolower($name)); ?>"
                            data-phone="<?php echo e(strtolower($collector['phone'] ?? '')); ?>"
                            data-pincode="<?php echo e(strtolower($collector['pincode'] ?? '')); ?>"
                            data-status="<?php echo e($displayStatus); ?>"
                        >

                            <td>
                                <div class="collector-info">

                                    <div class="collector-avatar">
                                        <?php echo e($initial); ?>
                                    </div>

                                    <div class="collector-name">
                                        <strong><?php echo e($name); ?></strong>

                                        <span>
                                            Collector #
                                            <?php echo e($collector['collector_id']); ?>
                                        </span>
                                    </div>

                                </div>
                            </td>

                            <td>
                                <div class="contact-info">

                                    <strong>
                                        <?php echo e($collector['phone'] ?? '—'); ?>
                                    </strong>

                                    <span>
                                        <?php echo e($collector['email'] ?? '—'); ?>
                                    </span>

                                </div>
                            </td>

                            <td>
                                <strong>
                                    <?php echo e($collector['pincode'] ?? '—'); ?>
                                </strong>

                                <div
                                    style="
                                        margin-top:4px;
                                        color:#8b9891;
                                        font-size:10px;
                                    "
                                >
                                    Service Pincode
                                </div>
                            </td>

                            <td>
                                <span
                                    class="status-pill status-<?php echo e($displayStatus); ?>"
                                >
                                    <span class="status-dot"></span>
                                    <?php echo e($statusLabel); ?>
                                </span>
                            </td>

                            <td>

                                <?php if ($hasPickup): ?>

                                    <div class="pickup-card">

                                        <div class="pickup-top">

                                            <span class="pickup-id">
                                                #EC-<?php echo e(
                                                    $collector['activity_id']
                                                ); ?>
                                            </span>

                                            <span class="pickup-type">
                                                <?php echo e(
                                                    $collector['scrap_type']
                                                    ?? 'Scrap'
                                                ); ?>
                                            </span>

                                        </div>

                                        <div
                                            style="
                                                color:#52635a;
                                                font-size:11px;
                                            "
                                        >
                                            <?php
                                            echo e(
                                                !empty(
                                                    $collector['scrap_weight']
                                                )
                                                    ? number_format(
                                                        (float)$collector[
                                                            'scrap_weight'
                                                        ],
                                                        2
                                                    ) . ' kg'
                                                    : 'Weight not specified'
                                            );
                                            ?>
                                        </div>

                                        <div class="pickup-roadmap">

                                            <div class="roadmap-title">

                                                <strong>
                                                    Pickup Progress
                                                </strong>

                                                <span class="roadmap-current">
                                                    <?php echo e(
                                                        $normalizedPickupStatus
                                                    ); ?>
                                                </span>

                                            </div>

                                            <div class="roadmap-steps">

                                                <?php foreach (
                                                    $pickupSteps
                                                    as $stepIndex => $stepName
                                                ): ?>

                                                    <?php
                                                    if (
                                                        $stepIndex
                                                        < $currentPickupStep
                                                    ) {
                                                        $stepClass = 'completed';
                                                        $stepIcon = 'fa-check';
                                                    } elseif (
                                                        $stepIndex
                                                        === $currentPickupStep
                                                    ) {
                                                        $stepClass = 'active';
                                                        $stepIcon =
                                                            'fa-location-dot';
                                                    } else {
                                                        $stepClass = 'upcoming';
                                                        $stepIcon = 'fa-circle';
                                                    }
                                                    ?>

                                                    <div
                                                        class="
                                                            roadmap-step
                                                            <?php echo e(
                                                                $stepClass
                                                            ); ?>
                                                        "
                                                    >

                                                        <div class="roadmap-node">
                                                            <i
                                                                class="
                                                                    fa-solid
                                                                    <?php echo e(
                                                                        $stepIcon
                                                                    ); ?>
                                                                "
                                                            ></i>
                                                        </div>

                                                        <span class="roadmap-label">
                                                            <?php echo e(
                                                                $stepName
                                                            ); ?>
                                                        </span>

                                                    </div>

                                                <?php endforeach; ?>

                                            </div>

                                            <div class="pickup-status-badge">
                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-circle-info
                                                    "
                                                ></i>

                                                Current status:
                                                <?php echo e(
                                                    $normalizedPickupStatus
                                                ); ?>
                                            </div>

                                        </div>

                                    </div>

                                <?php else: ?>

                                    <span class="no-pickup">
                                        <i
                                            class="
                                                fa-solid
                                                fa-circle-minus
                                            "
                                        ></i>
                                        No active pickup
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ($hasPickup): ?>

                                    <div class="pickup-address">

                                        <i
                                            class="
                                                fa-solid
                                                fa-location-dot
                                            "
                                        ></i>

                                        <span>

                                            <?php echo e(
                                                $collector['pickup_address']
                                                ?: 'Pickup address unavailable'
                                            ); ?>

                                            <?php if (
                                                !empty(
                                                    $collector['pickup_pincode']
                                                )
                                            ): ?>

                                                <br>

                                                <small>
                                                    Pincode:
                                                    <?php echo e(
                                                        $collector[
                                                            'pickup_pincode'
                                                        ]
                                                    ); ?>
                                                </small>

                                            <?php endif; ?>

                                        </span>

                                    </div>

                                <?php else: ?>

                                    <span class="no-pickup">—</span>

                                <?php endif; ?>

                            </td>

                            <td>

                                

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">

                            <div class="empty-state">

                                <i class="fa-solid fa-truck"></i>

                                <strong>
                                    No scrap collectors found
                                </strong>

                                <p>
                                    Registered collectors will appear here.
                                </p>

                            </div>

                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

<script>

const searchInput = document.getElementById('collectorSearch');
const statusFilter = document.getElementById('statusFilter');

const rows = document.querySelectorAll(
    '#collectorTableBody tr[data-status]'
);

function filterCollectors() {

    const search = searchInput.value.toLowerCase().trim();
    const status = statusFilter.value;

    rows.forEach(row => {

        const name = row.dataset.name || '';
        const phone = row.dataset.phone || '';
        const pincode = row.dataset.pincode || '';
        const rowStatus = row.dataset.status || '';

        const matchesSearch =
            name.includes(search) ||
            phone.includes(search) ||
            pincode.includes(search);

        const matchesStatus =
            status === 'all' || rowStatus === status;

        row.style.display =
            matchesSearch && matchesStatus ? '' : 'none';
    });
}

searchInput.addEventListener('input', filterCollectors);
statusFilter.addEventListener('change', filterCollectors);

</script>

</body>
</html>