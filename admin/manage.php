<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';


// =====================================================
// ADMIN PROTECTION
// =====================================================

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header('Location: ../login.php');
    exit();
}


// =====================================================
// SAFE HTML OUTPUT
// =====================================================

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


// =====================================================
// STATUS COUNTERS
// =====================================================

$counts = [
    'Pending' => 0,
    'Approved' => 0,
    'Assigned' => 0,
    'Accepted' => 0,
    'In Progress' => 0,
    'Verified' => 0,
    'Completed' => 0,
    'Rejected' => 0
];

$countSql = "
    SELECT status, COUNT(*) AS total
    FROM activity
    GROUP BY status
";

$countResult = $conn->query($countSql);

if ($countResult) {
    while ($countRow = $countResult->fetch_assoc()) {
        $currentStatus = $countRow['status'] ?? '';

        if (array_key_exists($currentStatus, $counts)) {
            $counts[$currentStatus] =
                (int)$countRow['total'];
        }
    }
}


// =====================================================
// SEARCH AND FILTER
// =====================================================

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');


// Allow only known statuses
$allowedStatuses = [
    'Pending',
    'Approved',
    'Assigned',
    'Accepted',
    'In Progress',
    'Verified',
    'Completed',
    'Rejected'
];

if (
    $status !== '' &&
    !in_array($status, $allowedStatuses, true)
) {
    $status = '';
}


// =====================================================
// REQUEST QUERY
// =====================================================

$sql = "
    SELECT
        activity.*,
        user.name AS user_name,
        user.phone AS user_phone,
        user.address AS user_address,
        scrapcollector.name AS collector_name,
        scrapcollector.phone AS collector_phone,
        scrapcollector.vehicle_no
    FROM activity
    INNER JOIN user
        ON activity.user_id = user.user_id
    LEFT JOIN scrapcollector
        ON activity.collector_id = scrapcollector.collector_id
    WHERE 1 = 1
";

$params = [];
$types = '';


// Search filter
if ($search !== '') {
    $sql .= "
        AND (
            user.name LIKE ?
            OR activity.scrap_type LIKE ?
            OR activity.pickup_pincode LIKE ?
            OR scrapcollector.name LIKE ?
        )
    ";

    $searchTerm = '%' . $search . '%';

    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    $types .= 'ssss';
}


// Status filter
if ($status !== '') {
    $sql .= "
        AND activity.status = ?
    ";

    $params[] = $status;
    $types .= 's';
}


$sql .= "
    ORDER BY activity.request_date DESC
";


$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Unable to prepare request query.');
}


if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}


if (!$stmt->execute()) {
    die('Unable to load pickup requests.');
}


$requests = $stmt->get_result();

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
        Manage Pickup Requests | EcoScrap
    </title>


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
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/manage.css"
    >


    <style>

        :root {
            --primary: #16a34a;
            --primary-dark: #15803d;
            --primary-light: #dcfce7;
            --text: #17221b;
            --muted: #718078;
            --surface: #ffffff;
            --background: #f5f7f6;
            --border: #e3ebe5;
            --danger: #dc2626;
            --warning: #d97706;
            --shadow: 0 15px 35px rgba(20, 83, 45, .08);
        }


        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background: var(--background);
            font-family: Inter, sans-serif;
        }


        .ambient-blur {
            position: fixed;
            z-index: -1;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .25;
            pointer-events: none;
        }


        .blur-1 {
            top: -150px;
            left: -100px;
            background: #bbf7d0;
        }


        .blur-2 {
            right: -150px;
            bottom: -150px;
            background: #d9f99d;
        }


        .workspace-container {
            width: min(1380px, calc(100% - 48px));
            margin: 0 auto;
            padding: 35px 0 60px;
        }


        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }


        .page-title h1 {
            margin: 0;
            color: #173322;
            font-size: clamp(25px, 3vw, 36px);
            font-weight: 800;
            letter-spacing: -.8px;
        }


        .page-title p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 14px;
        }


        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 17px;
            color: var(--primary-dark);
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            transition: .2s ease;
        }


        .btn-back:hover {
            color: #ffffff;
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-1px);
        }


        .alert {
            border: 0;
            border-radius: 13px;
            box-shadow: var(--shadow);
        }


        .counters-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }


        .counter-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 120px;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 17px;
            box-shadow: var(--shadow);
        }


        .counter-info span {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }


        .counter-info h2 {
            margin: 0;
            color: #183324;
            font-size: 30px;
            font-weight: 800;
        }


        .counter-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            font-size: 23px;
        }


        .icon-pending {
            color: #b45309;
            background: #fef3c7;
        }


        .icon-approved {
            color: #15803d;
            background: #dcfce7;
        }


        .icon-assigned {
            color: #0369a1;
            background: #e0f2fe;
        }


        .icon-completed {
            color: #7e22ce;
            background: #f3e8ff;
        }


        .glass-card {
            margin-bottom: 24px;
            padding: 20px;
            background: rgba(255,255,255,.82);
            border: 1px solid var(--border);
            border-radius: 17px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }


        .filter-grid {
            display: grid;
            grid-template-columns: minmax(250px, 1.5fr) minmax(180px, .8fr) auto auto;
            align-items: center;
            gap: 12px;
        }


        .form-control-eco,
        .form-select-eco {
            width: 100%;
            min-height: 46px;
            padding: 0 14px;
            color: #263a2d;
            background: #ffffff;
            border: 1px solid #dce7df;
            border-radius: 11px;
            outline: 0;
            font-size: 13px;
        }


        .form-control-eco:focus,
        .form-select-eco:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(22,163,74,.11);
        }


        .btn-search,
        .btn-reset {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 17px;
            border-radius: 11px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }


        .btn-search {
            color: #ffffff;
            background: var(--primary);
            border: 1px solid var(--primary);
            cursor: pointer;
        }


        .btn-search:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }


        .btn-reset {
            color: #52645a;
            background: #ffffff;
            border: 1px solid var(--border);
        }


        .btn-reset:hover {
            color: var(--primary-dark);
            border-color: #a9d7b5;
        }


        .requests-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }


        .request-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 350px;
            padding: 22px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            transition: .2s ease;
        }


        .request-card:hover {
            border-color: #b8ddc1;
            transform: translateY(-2px);
        }


        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 20px;
        }


        .scrap-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #183324;
            font-size: 17px;
            font-weight: 800;
        }


        .scrap-title i {
            color: var(--primary);
            font-size: 21px;
        }


        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }


        .badge-pending {
            color: #a16207;
            background: #fef3c7;
        }


        .badge-approved {
            color: #15803d;
            background: #dcfce7;
        }


        .badge-assigned,
        .badge-accepted {
            color: #0369a1;
            background: #e0f2fe;
        }


        .badge-completed,
        .badge-verified {
            color: #7e22ce;
            background: #f3e8ff;
        }


        .badge-rejected {
            color: #b91c1c;
            background: #fee2e2;
        }


        .badge-default {
            color: #475569;
            background: #f1f5f9;
        }


        .detail-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 20px;
        }


        .detail-item {
            min-width: 0;
        }


        .detail-label {
            display: block;
            margin-bottom: 5px;
            color: #93a097;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .45px;
            text-transform: uppercase;
        }


        .detail-value {
            display: block;
            overflow: hidden;
            color: #33463a;
            font-size: 13px;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .collector-value {
            color: var(--primary-dark);
        }


        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 20px;
            margin-top: 22px;
            border-top: 1px solid #edf2ee;
        }


        .btn-act {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 35px;
            padding: 0 11px;
            border: 1px solid transparent;
            border-radius: 9px;
            font-size: 11px;
            font-weight: 700;
            transition: .2s ease;
        }


        .btn-act:hover {
            transform: translateY(-1px);
        }


        .btn-act-view {
            color: #475569;
            background: #f8fafc;
            border-color: #e2e8f0;
        }


        .btn-act-approve {
            color: #15803d;
            background: #f0fdf4;
            border-color: #bbf7d0;
        }


        .btn-act-reject {
            color: #b91c1c;
            background: #fff7f7;
            border-color: #fecaca;
        }


        .btn-act-assign {
            color: #0369a1;
            background: #f0f9ff;
            border-color: #bae6fd;
        }


        .btn-act-qr {
            color: #7e22ce;
            background: #faf5ff;
            border-color: #e9d5ff;
        }


        .btn-act-disabled {
            color: #94a3b8;
            background: #f8fafc;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }


        .empty-alert {
            grid-column: 1 / -1;
            padding: 45px 20px;
            color: var(--muted);
            background: #ffffff;
            border: 1px dashed #cddbd1;
            border-radius: 17px;
            text-align: center;
        }


        .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 18px;
            box-shadow: 0 25px 70px rgba(15,23,42,.22);
        }


        .modal-header {
            color: #ffffff;
            background: #103d29;
            border-bottom: 0;
        }


        .modal-title {
            font-size: 17px;
            font-weight: 800;
        }


        .modal-body {
            padding: 24px;
        }


        .modal-detail {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 0;
            border-bottom: 1px solid #edf2ee;
            font-size: 13px;
        }


        .modal-detail:last-child {
            border-bottom: 0;
        }


        .modal-detail span:first-child {
            color: #87958c;
            font-weight: 600;
        }


        .modal-detail span:last-child {
            color: #263a2d;
            font-weight: 700;
            text-align: right;
        }


        @media (max-width: 1000px) {
            .counters-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .requests-grid {
                grid-template-columns: 1fr;
            }
        }


        @media (max-width: 600px) {
            .workspace-container {
                width: min(100% - 28px, 1380px);
                padding-top: 22px;
            }

            .page-header {
                align-items: flex-start;
            }

            .page-title p {
                line-height: 1.5;
            }

            .btn-back span {
                display: none;
            }

            .btn-back {
                padding: 12px;
            }

            .counters-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .counter-card {
                min-height: 100px;
                padding: 14px;
            }

            .counter-info h2 {
                font-size: 24px;
            }

            .counter-icon {
                width: 38px;
                height: 38px;
                font-size: 18px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .request-card {
                padding: 17px;
            }

            .card-top {
                flex-direction: column;
            }

            .detail-list {
                grid-template-columns: 1fr;
            }

            .detail-item[style*="span 2"] {
                grid-column: span 1 !important;
            }

            .modal-detail {
                flex-direction: column;
                gap: 4px;
            }

            .modal-detail span:last-child {
                text-align: left;
            }
        }

    </style>

</head>


<body>

    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>


    <main class="workspace-container">


        <header class="page-header">

            <div class="page-title">

                <h1>
                    Manage Pickup Requests
                </h1>

                <p>
                    Filter, assign collectors, and monitor scrap recycling activity in real time.
                </p>

            </div>


            <a
                href="dashboard.php"
                class="btn-back"
            >
                <i class="ri-arrow-left-line"></i>
                <span>Dashboard</span>
            </a>

        </header>


        <?php if (isset($_SESSION['success'])): ?>

            <div
                class="alert alert-success alert-dismissible fade show mb-4"
                role="alert"
            >
                <i class="ri-checkbox-circle-line me-1"></i>

                <?php
                echo e($_SESSION['success']);
                ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>

            <?php unset($_SESSION['success']); ?>

        <?php endif; ?>


        <?php if (isset($_SESSION['error'])): ?>

            <div
                class="alert alert-danger alert-dismissible fade show mb-4"
                role="alert"
            >
                <i class="ri-error-warning-line me-1"></i>

                <?php
                echo e($_SESSION['error']);
                ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>


        <section class="counters-grid">

            <div class="counter-card">

                <div class="counter-info">
                    <span>Pending</span>
                    <h2>
                        <?php echo $counts['Pending']; ?>
                    </h2>
                </div>

                <div class="counter-icon icon-pending">
                    <i class="ri-time-line"></i>
                </div>

            </div>


            <div class="counter-card">

                <div class="counter-info">
                    <span>Approved</span>
                    <h2>
                        <?php echo $counts['Approved']; ?>
                    </h2>
                </div>

                <div class="counter-icon icon-approved">
                    <i class="ri-checkbox-circle-line"></i>
                </div>

            </div>


            <div class="counter-card">

                <div class="counter-info">
                    <span>Assigned</span>
                    <h2>
                        <?php echo $counts['Assigned']; ?>
                    </h2>
                </div>

                <div class="counter-icon icon-assigned">
                    <i class="ri-user-follow-line"></i>
                </div>

            </div>


            <div class="counter-card">

                <div class="counter-info">
                    <span>Completed</span>
                    <h2>
                        <?php echo $counts['Completed']; ?>
                    </h2>
                </div>

                <div class="counter-icon icon-completed">
                    <i class="ri-check-double-line"></i>
                </div>

            </div>

        </section>


        <section class="glass-card">

            <form
                method="GET"
                class="filter-grid"
            >

                <div>
                    <input
                        type="text"
                        name="search"
                        class="form-control-eco"
                        placeholder="Search by user, scrap type, pincode, or collector..."
                        value="<?php echo e($search); ?>"
                    >
                </div>


                <div>

                    <select
                        name="status"
                        class="form-select-eco"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <?php foreach ($allowedStatuses as $availableStatus): ?>

                            <option
                                value="<?php echo e($availableStatus); ?>"
                                <?php
                                echo $status === $availableStatus
                                    ? 'selected'
                                    : '';
                                ?>
                            >
                                <?php echo e($availableStatus); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <button
                        type="submit"
                        class="btn-search"
                    >
                        <i class="ri-search-line"></i>
                        Search
                    </button>

                </div>


                <div>

                    <a
                        href="manage.php"
                        class="btn-reset"
                    >
                        <i class="ri-refresh-line"></i>
                        Reset
                    </a>

                </div>

            </form>

        </section>


        <section class="requests-grid">

            <?php if ($requests && $requests->num_rows > 0): ?>

                <?php while ($row = $requests->fetch_assoc()): ?>

                    <?php

                    $requestStatus =
                        $row['status'] ?? '';

                    switch ($requestStatus) {

                        case 'Pending':
                            $badgeClass = 'badge-pending';
                            $statusIcon = 'ri-time-line';
                            break;

                        case 'Approved':
                            $badgeClass = 'badge-approved';
                            $statusIcon = 'ri-checkbox-circle-line';
                            break;

                        case 'Assigned':
                            $badgeClass = 'badge-assigned';
                            $statusIcon = 'ri-user-follow-line';
                            break;

                        case 'Accepted':
                            $badgeClass = 'badge-accepted';
                            $statusIcon = 'ri-user-star-line';
                            break;

                        case 'Completed':
                            $badgeClass = 'badge-completed';
                            $statusIcon = 'ri-check-double-line';
                            break;

                        case 'Rejected':
                            $badgeClass = 'badge-rejected';
                            $statusIcon = 'ri-close-circle-line';
                            break;

                        default:
                            $badgeClass = 'badge-default';
                            $statusIcon = 'ri-information-line';
                    }

                    ?>


                    <article class="request-card">

                        <div>

                            <div class="card-top">

                                <span class="scrap-title">
                                    <i class="ri-recycle-line"></i>
                                    <?php
                                    echo e(
                                        $row['scrap_type'] ?? 'Unknown Scrap'
                                    );
                                    ?>
                                </span>


                                <span
                                    class="badge <?php echo $badgeClass; ?>"
                                >
                                    <i
                                        class="<?php echo $statusIcon; ?>"
                                    ></i>

                                    <?php
                                    echo e($requestStatus);
                                    ?>
                                </span>

                            </div>


                            <div class="detail-list">

                                <div class="detail-item">
                                    <span class="detail-label">
                                        User Name
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo e(
                                            $row['user_name'] ?? 'N/A'
                                        );
                                        ?>
                                    </span>
                                </div>


                                <div class="detail-item">
                                    <span class="detail-label">
                                        Phone
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo e(
                                            $row['user_phone'] ?? 'N/A'
                                        );
                                        ?>
                                    </span>
                                </div>


                                <div class="detail-item">
                                    <span class="detail-label">
                                        Estimated Weight
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo e(
                                            $row['scrap_weight'] ?? '0'
                                        );
                                        ?>
                                        KG
                                    </span>
                                </div>


                                <div class="detail-item">
                                    <span class="detail-label">
                                        Pickup Pincode
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo e(
                                            $row['pickup_pincode'] ?? 'N/A'
                                        );
                                        ?>
                                    </span>
                                </div>


                                <?php if (!empty($row['collector_name'])): ?>

                                    <div class="detail-item">

                                        <span class="detail-label">
                                            Collector
                                        </span>

                                        <span
                                            class="detail-value collector-value"
                                        >
                                            <i class="ri-user-received-line"></i>

                                            <?php
                                            echo e(
                                                $row['collector_name']
                                            );
                                            ?>
                                        </span>

                                    </div>

                                <?php endif; ?>


                                <div class="detail-item">
                                    <span class="detail-label">
                                        Preferred Date
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo e(
                                            $row[
                                                'preferred_pickup_date'
                                            ] ?? 'N/A'
                                        );
                                        ?>
                                    </span>
                                </div>


                                <div class="detail-item">
                                    <span class="detail-label">
                                        Requested On
                                    </span>

                                    <span class="detail-value">

                                        <?php
                                        echo !empty(
                                            $row['request_date']
                                        )
                                            ? e(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime(
                                                        $row[
                                                            'request_date'
                                                        ]
                                                    )
                                                )
                                            )
                                            : 'N/A';
                                        ?>

                                    </span>
                                </div>

                            </div>

                        </div>


                        <div class="card-actions">

                            <button
                                type="button"
                                class="btn-act btn-act-view"
                                data-bs-toggle="modal"
                                data-bs-target="#viewRequestModal"

                                data-user="<?php echo e($row['user_name'] ?? 'N/A'); ?>"
                                data-phone="<?php echo e($row['user_phone'] ?? 'N/A'); ?>"
                                data-address="<?php echo e($row['user_address'] ?? 'N/A'); ?>"
                                data-scraptype="<?php echo e($row['scrap_type'] ?? 'N/A'); ?>"
                                data-weight="<?php echo e(($row['scrap_weight'] ?? '0') . ' KG'); ?>"
                                data-pickupdate="<?php echo e($row['preferred_pickup_date'] ?? 'N/A'); ?>"
                                data-collector="<?php echo e($row['collector_name'] ?? 'Not Assigned'); ?>"
                                data-status="<?php echo e($requestStatus); ?>"
                            >
                                <i class="ri-eye-line"></i>
                                View
                            </button>


                            <?php if ($requestStatus === 'Pending'): ?>

                                <a
                                    href="approve_request.php?id=<?php echo (int)$row['activity_id']; ?>"
                                    class="btn-act btn-act-approve"
                                >
                                    <i class="ri-check-line"></i>
                                    Approve
                                </a>


                                <a
                                    href="reject_request.php?id=<?php echo (int)$row['activity_id']; ?>"
                                    class="btn-act btn-act-reject"
                                    onclick="return confirm('Are you sure you want to reject this request?');"
                                >
                                    <i class="ri-close-line"></i>
                                    Reject
                                </a>


                            <?php elseif ($requestStatus === 'Approved'): ?>

                                <a
                                    href="assign_collector.php?id=<?php echo (int)$row['activity_id']; ?>"
                                    class="btn-act btn-act-assign"
                                >
                                    <i class="ri-user-add-line"></i>
                                    Assign Collector
                                </a>


                            <?php elseif ($requestStatus === 'Assigned'): ?>

                                <?php if (!empty($row['qr_code'])): ?>

                                    <a
                                        href="generate_qr.php?id=<?php echo (int)$row['activity_id']; ?>"
                                        class="btn-act btn-act-qr"
                                    >
                                        <i class="ri-qr-code-fill"></i>
                                        View QR
                                    </a>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn-act btn-act-disabled"
                                        disabled
                                    >
                                        <i class="ri-error-warning-line"></i>
                                        QR Missing
                                    </button>

                                <?php endif; ?>


                            <?php elseif ($requestStatus === 'Completed'): ?>

                                <button
                                    type="button"
                                    class="btn-act btn-act-disabled"
                                    disabled
                                >
                                    <i class="ri-checkbox-circle-fill"></i>
                                    Completed
                                </button>


                            <?php elseif ($requestStatus === 'Rejected'): ?>

                                <button
                                    type="button"
                                    class="btn-act btn-act-disabled"
                                    disabled
                                >
                                    <i class="ri-close-circle-fill"></i>
                                    Rejected
                                </button>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="empty-alert">
                    <i class="ri-inbox-line fs-2 d-block mb-2"></i>
                    No pickup requests found matching your filters.
                </div>

            <?php endif; ?>

        </section>

    </main>


    <!-- View Request Modal -->

    <div
        class="modal fade"
        id="viewRequestModal"
        tabindex="-1"
        aria-labelledby="viewRequestModalLabel"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="viewRequestModalLabel"
                    >
                        Pickup Request Details
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>

                </div>


                <div class="modal-body">

                    <div class="modal-detail">
                        <span>User Name</span>
                        <span id="modalUser">N/A</span>
                    </div>


                    <div class="modal-detail">
                        <span>Phone</span>
                        <span id="modalPhone">N/A</span>
                    </div>


                    <div class="modal-detail">
                        <span>Address</span>
                        <span id="modalAddress">N/A</span>
                    </div>


                    <div class="modal-detail">
                        <span>Scrap Type</span>
                        <span id="modalScrapType">N/A</span>
                    </div>


                    <div class="modal-detail">
                        <span>Estimated Weight</span>
                        <span id="modalWeight">N/A</span>
                    </div>


                    <div class="modal-detail">
                        <span>Preferred Date</span>
                        <span id="modalPickupDate">N/A</span>
                    </div>


                    <div class="modal-detail">
                        <span>Collector</span>
                        <span id="modalCollector">N/A</span>
                    </div>


                    <div class="modal-detail">
                        <span>Status</span>
                        <span id="modalStatus">N/A</span>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
    ></script>


    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const viewButtons =
                    document.querySelectorAll(
                        '.btn-act-view'
                    );


                viewButtons.forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function () {

                                document.getElementById(
                                    'modalUser'
                                ).textContent =
                                    button.dataset.user || 'N/A';


                                document.getElementById(
                                    'modalPhone'
                                ).textContent =
                                    button.dataset.phone || 'N/A';


                                document.getElementById(
                                    'modalAddress'
                                ).textContent =
                                    button.dataset.address || 'N/A';


                                document.getElementById(
                                    'modalScrapType'
                                ).textContent =
                                    button.dataset.scraptype || 'N/A';


                                document.getElementById(
                                    'modalWeight'
                                ).textContent =
                                    button.dataset.weight || 'N/A';


                                document.getElementById(
                                    'modalPickupDate'
                                ).textContent =
                                    button.dataset.pickupdate || 'N/A';


                                document.getElementById(
                                    'modalCollector'
                                ).textContent =
                                    button.dataset.collector || 'N/A';


                                document.getElementById(
                                    'modalStatus'
                                ).textContent =
                                    button.dataset.status || 'N/A';

                            }
                        );

                    }
                );

            }
        );

    </script>

</body>

</html>

<?php

$stmt->close();

?>