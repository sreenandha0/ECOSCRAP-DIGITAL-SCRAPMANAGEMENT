<?php

session_start();


// =====================================================
// ADMIN AUTHENTICATION
// =====================================================

if (
    !isset($_SESSION['role']) ||
    strtolower((string)$_SESSION['role']) !== 'admin'
) {
    header('Location: ../login.php');
    exit();
}


// =====================================================
// DATABASE CONFIGURATION
// =====================================================

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'ecoscrap_db';


// =====================================================
// DATABASE CONNECTION
// =====================================================

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $exception) {
    error_log($exception->getMessage());

    http_response_code(500);
    exit('Database connection failed.');
}


// =====================================================
// HELPER FUNCTIONS
// =====================================================

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function userInitials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);

    $firstLetter =
        substr($parts[0] ?? 'U', 0, 1);

    $secondLetter =
        substr($parts[1] ?? '', 0, 1);

    return strtoupper(
        $firstLetter . $secondLetter
    );
}


// =====================================================
// CSRF TOKEN
// =====================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


// =====================================================
// FLASH MESSAGE
// =====================================================

$message = '';
$messageType = '';


// =====================================================
// DELETE USER
// =====================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete_user'
) {
    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $postedToken
        )
    ) {
        $message =
            'Invalid security token. Please try again.';

        $messageType = 'danger';

    } else {

        $deleteId = filter_input(
            INPUT_POST,
            'user_id',
            FILTER_VALIDATE_INT
        );

        if (!$deleteId || $deleteId < 1) {

            $message = 'Invalid user ID.';
            $messageType = 'danger';

        } else {

            try {
                $pdo->beginTransaction();


                // Verify the user exists
                $checkUser = $pdo->prepare("
                    SELECT user_id
                    FROM `user`
                    WHERE user_id = ?
                    LIMIT 1
                ");

                $checkUser->execute([$deleteId]);

                if (!$checkUser->fetch()) {
                    throw new RuntimeException(
                        'The selected user no longer exists.'
                    );
                }


                /*
                 * Delete related activity records first.
                 *
                 * Keep this query if your activity table does not use:
                 * ON DELETE CASCADE
                 */
                $deleteActivity = $pdo->prepare("
                    DELETE FROM activity
                    WHERE user_id = ?
                ");

                $deleteActivity->execute([$deleteId]);


                // Delete the user
                $deleteUser = $pdo->prepare("
                    DELETE FROM `user`
                    WHERE user_id = ?
                ");

                $deleteUser->execute([$deleteId]);


                if ($deleteUser->rowCount() !== 1) {
                    throw new RuntimeException(
                        'The user could not be deleted.'
                    );
                }


                $pdo->commit();

                $message =
                    "User #{$deleteId} and related activity records were deleted successfully.";

                $messageType = 'success';

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($exception->getMessage());

                $message =
                    'Unable to delete the user. Please try again.';

                $messageType = 'danger';
            }
        }
    }
}


// =====================================================
// STATISTICS
// =====================================================

$totalUsers = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM `user`
    ")
    ->fetchColumn();


$newThisMonth = (int)$pdo
    ->query("
        SELECT COUNT(*)
        FROM `user`
        WHERE created_at >= DATE_FORMAT(
            CURRENT_DATE(),
            '%Y-%m-01'
        )
        AND created_at < DATE_ADD(
            DATE_FORMAT(
                CURRENT_DATE(),
                '%Y-%m-01'
            ),
            INTERVAL 1 MONTH
        )
    ")
    ->fetchColumn();


// =====================================================
// FETCH USERS
// =====================================================

$userQuery = "
    SELECT
        u.*,

        COUNT(a.activity_id) AS total_pickups,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'Completed'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS completed_pickups,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'In Progress'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS in_progress_pickups

    FROM `user` u

    LEFT JOIN activity a
        ON a.user_id = u.user_id

    GROUP BY u.user_id

    ORDER BY u.user_id DESC
";

$users = $pdo
    ->query($userQuery)
    ->fetchAll();


// =====================================================
// DISTRICTS
// =====================================================

$districts = [];

foreach ($users as $user) {

    $district = trim(
        (string)($user['district'] ?? '')
    );

    if ($district !== '') {
        $districts[$district] = $district;
    }
}

ksort($districts);


// =====================================================
// ACTIVE USERS
// =====================================================

$activeActivityUsers = 0;

foreach ($users as $user) {

    if ((int)$user['total_pickups'] > 0) {
        $activeActivityUsers++;
    }
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
        Manage Users | EcoScrap Admin
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <style>

        :root {
            --primary: #16a34a;
            --primary-dark: #15803d;
            --primary-light: #dcfce7;

            --dark-green: #103d29;
            --dark-green-2: #14532d;

            --background: #f3f7f4;
            --surface: rgba(255, 255, 255, .86);
            --surface-solid: #ffffff;

            --text: #17221b;
            --muted: #718078;
            --border: #dfebe2;

            --danger: #dc2626;
            --danger-light: #fee2e2;

            --blue: #0284c7;
            --blue-light: #e0f2fe;

            --purple: #7e22ce;
            --purple-light: #f3e8ff;

            --warning: #d97706;
            --warning-light: #fef3c7;

            --shadow:
                0 16px 38px rgba(20, 83, 45, .08);

            --hover-shadow:
                0 22px 48px rgba(20, 83, 45, .14);
        }


        * {
            box-sizing: border-box;
        }


        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(
                    circle at 10% 5%,
                    rgba(187, 247, 208, .55),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 95% 90%,
                    rgba(217, 249, 157, .35),
                    transparent 26%
                ),
                var(--background);
            font-family: 'Plus Jakarta Sans', sans-serif;
        }


        a {
            text-decoration: none;
        }


        .ambient-blur {
            position: fixed;
            z-index: -1;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            filter: blur(90px);
            opacity: .28;
            pointer-events: none;
        }


        .blur-one {
            top: -170px;
            left: -120px;
            background: #bbf7d0;
        }


        .blur-two {
            right: -140px;
            bottom: -160px;
            background: #d9f99d;
        }


        /* TOP NAVIGATION */

        .top-navbar {
            position: sticky;
            top: 18px;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            width: min(1420px, calc(100% - 48px));
            margin: 18px auto 0;
            padding: 15px 19px;
            color: #ffffff;
            background: rgba(16, 61, 41, .94);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 17px;
            box-shadow: 0 15px 35px rgba(16,61,41,.18);
            backdrop-filter: blur(16px);
        }


        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 800;
        }


        .nav-brand i {
            color: #a7f3d0;
            font-size: 24px;
        }


        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            color: #d9fbe4;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            transition: .2s ease;
        }


        .btn-back:hover {
            color: var(--dark-green);
            background: #ffffff;
        }


        /* MAIN CONTAINER */

        .workspace-container {
            width: min(1420px, calc(100% - 48px));
            margin: 0 auto;
            padding: 30px 0 65px;
        }


        /* PAGE HEADER */

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
        }


        .header-title h1 {
            margin: 0;
            color: #173322;
            font-size: clamp(26px, 3vw, 38px);
            font-weight: 800;
            letter-spacing: -.9px;
        }


        .header-title p {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 14px;
        }


        .search-box {
            display: flex;
            align-items: center;
            gap: 9px;
            width: min(100%, 390px);
            padding: 12px 14px;
            background: rgba(255,255,255,.8);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }


        .search-box i {
            color: var(--primary);
            font-size: 18px;
        }


        .search-input {
            width: 100%;
            border: 0;
            outline: 0;
            color: var(--text);
            background: transparent;
            font-size: 13px;
        }


        .search-input::placeholder {
            color: #9aa89f;
        }


        /* FLASH ALERT */

        .alert {
            border: 0;
            border-radius: 13px;
            box-shadow: var(--shadow);
        }


        /* STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }


        .stat-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 126px;
            padding: 21px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 17px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
            transition: .2s ease;
        }


        .stat-card:hover {
            border-color: #b9ddc1;
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }


        .stat-label {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }


        .stat-number {
            margin: 0;
            color: #173322;
            font-size: 29px;
            font-weight: 800;
        }


        .stat-icon {
            width: 49px;
            height: 49px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            font-size: 22px;
        }


        .stat-icon-green {
            color: #15803d;
            background: var(--primary-light);
        }


        .stat-icon-blue {
            color: #0369a1;
            background: var(--blue-light);
        }


        .stat-icon-yellow {
            color: #a16207;
            background: var(--warning-light);
        }


        .stat-icon-purple {
            color: var(--purple);
            background: var(--purple-light);
        }


        /* FILTER TOOLBAR */

        .filter-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 24px;
            padding: 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 17px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(12px);
        }


        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }


        .filter-control {
            min-height: 44px;
            padding: 0 13px;
            color: #33463a;
            background: #ffffff;
            border: 1px solid #dce8df;
            border-radius: 10px;
            outline: 0;
            font-size: 12px;
        }


        .filter-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(22,163,74,.11);
        }


        /* USER CARDS */

        .user-card {
            position: relative;
            min-height: 100%;
            overflow: hidden;
            padding: 22px !important;
            background: rgba(255,255,255,.87);
            border: 1px solid var(--border);
            border-radius: 19px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
            transition: .2s ease;
        }


        .user-card::before {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 4px;
            content: '';
            background: linear-gradient(
                90deg,
                var(--primary),
                #86efac
            );
        }


        .user-card:hover {
            border-color: #b7ddc0;
            transform: translateY(-4px);
            box-shadow: var(--hover-shadow);
        }


        .avatar-circle {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #ffffff;
            background: linear-gradient(
                135deg,
                #22c55e,
                #166534
            );
            border: 3px solid #ecfdf5;
            border-radius: 16px;
            box-shadow: 0 6px 14px rgba(22,163,74,.18);
            font-size: 17px;
            font-weight: 800;
        }


        .user-id {
            color: #9aa89f;
            font-size: 10px;
            font-weight: 700;
        }


        .contact-list {
            display: grid;
            gap: 11px;
        }


        .contact-item {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
            color: #607267;
            font-size: 12px;
        }


        .contact-item i {
            width: 17px;
            flex-shrink: 0;
            color: var(--primary);
            font-size: 16px;
        }


        .contact-item span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .info-pill {
            padding: 10px 8px;
            background: #f5faf6;
            border: 1px solid #e5f0e7;
            border-radius: 11px;
            text-align: center;
        }


        .info-pill-label {
            display: block;
            margin-bottom: 4px;
            color: #93a097;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
        }


        .info-pill-value {
            color: #183324;
            font-size: 17px;
            font-weight: 800;
        }


        .card-footer {
            padding-top: 16px;
            margin-top: 18px;
            border-top: 1px solid #edf2ee;
        }


        .joined-date {
            color: #87958c;
            font-size: 10px;
        }


        .btn-view {
            color: #15803d;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 9px;
            font-size: 11px;
            font-weight: 800;
        }


        .btn-view:hover {
            color: #166534;
            background: #dcfce7;
        }


        .btn-delete {
            border-radius: 9px;
            font-size: 11px;
        }


        /* EMPTY STATE */

        .empty-state {
            padding: 70px 20px;
            text-align: center;
        }


        .empty-state-icon {
            width: 90px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.4rem;
            color: var(--primary);
            background: var(--primary-light);
            border-radius: 50%;
            font-size: 2.4rem;
        }


        /* MODALS */

        .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 21px;
            box-shadow: 0 25px 65px rgba(15,23,42,.24);
        }


        .modal-header {
            color: #ffffff;
            background: var(--dark-green);
            border-bottom: 0;
        }


        .modal-title {
            font-weight: 800;
        }


        .modal-detail-label {
            display: block;
            margin-bottom: 5px;
            color: #87958c;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
        }


        .modal-detail-value {
            display: block;
            color: #24382b;
            font-size: 13px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }


        .modal-stat {
            padding: 16px 8px;
            background: #f7faf8;
            border: 1px solid #e4eee6;
            border-radius: 12px;
            text-align: center;
        }


        .modal-stat span {
            display: block;
            margin-bottom: 5px;
            color: #87958c;
            font-size: 10px;
        }


        .modal-stat strong {
            color: #173322;
            font-size: 23px;
        }


        @media (max-width: 1050px) {

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }


        @media (max-width: 768px) {

            .workspace-container,
            .top-navbar {
                width: min(100% - 28px, 1420px);
            }


            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }


            .search-box {
                width: 100%;
            }


            .filter-card {
                align-items: stretch;
                flex-direction: column;
            }


            .filter-group {
                width: 100%;
            }


            .filter-control {
                flex: 1;
            }

        }


        @media (max-width: 520px) {

            .top-navbar {
                top: 8px;
                margin-top: 8px;
            }


            .workspace-container {
                padding-top: 22px;
            }


            .nav-brand {
                font-size: 15px;
            }


            .btn-back {
                padding: 8px 10px;
                font-size: 11px;
            }


            .btn-back span {
                display: none;
            }


            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }


            .stat-card {
                min-height: 105px;
                padding: 14px;
            }


            .stat-number {
                font-size: 23px;
            }


            .stat-icon {
                width: 38px;
                height: 38px;
                font-size: 18px;
            }


            .filter-group {
                flex-direction: column;
            }


            .filter-control {
                width: 100%;
            }


            .card-footer {
                align-items: flex-start !important;
                flex-direction: column;
                gap: 11px;
            }


            .card-footer .btn-group {
                width: 100%;
            }


            .card-footer .btn-group button {
                flex: 1;
            }

        }

    </style>

</head>

<body>

    <div class="ambient-blur blur-one"></div>
    <div class="ambient-blur blur-two"></div>


    <!-- =================================================
         TOP NAVIGATION
    ================================================= -->

    <nav class="top-navbar">

        <div class="nav-brand">
            <i class="bi bi-recycle"></i>
            EcoScrap Admin
        </div>


        <a
            href="dashboard.php"
            class="btn-back"
        >
            <i class="bi bi-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>

    </nav>


    <main class="workspace-container">


        <!-- =================================================
             FLASH MESSAGE
        ================================================= -->

        <?php if ($message !== ''): ?>

            <div
                class="alert alert-<?= e($messageType) ?> alert-dismissible fade show mb-4"
                role="alert"
            >
                <i class="bi bi-info-circle-fill me-2"></i>
                <?= e($message) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>

        <?php endif; ?>


        <!-- =================================================
             PAGE HEADER
        ================================================= -->

        <header class="page-header">

            <div class="header-title">

                <h1>
                    Manage Users
                </h1>

                <p>
                    View, manage, and monitor all registered EcoScrap accounts.
                </p>

            </div>


            <div class="search-box">

                <i class="bi bi-search"></i>

                <input
                    type="search"
                    id="searchInput"
                    class="search-input"
                    placeholder="Search name, email, phone, or district..."
                    autocomplete="off"
                >

            </div>

        </header>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <section class="stats-grid">

            <div class="stat-card">

                <div>
                    <span class="stat-label">
                        Total Registered
                    </span>

                    <h2 class="stat-number">
                        <?= number_format($totalUsers) ?>
                    </h2>
                </div>


                <div class="stat-icon stat-icon-green">
                    <i class="bi bi-people-fill"></i>
                </div>

            </div>


            <div class="stat-card">

                <div>
                    <span class="stat-label">
                        Joined This Month
                    </span>

                    <h2 class="stat-number">
                        <?= number_format($newThisMonth) ?>
                    </h2>
                </div>


                <div class="stat-icon stat-icon-blue">
                    <i class="bi bi-person-plus-fill"></i>
                </div>

            </div>


            <div class="stat-card">

                <div>
                    <span class="stat-label">
                        Active Activity Users
                    </span>

                    <h2 class="stat-number">
                        <?= number_format($activeActivityUsers) ?>
                    </h2>
                </div>


                <div class="stat-icon stat-icon-yellow">
                    <i class="bi bi-recycle"></i>
                </div>

            </div>


            <div class="stat-card">

                <div>
                    <span class="stat-label">
                        Districts Covered
                    </span>

                    <h2 class="stat-number">
                        <?= number_format(count($districts)) ?>
                    </h2>
                </div>


                <div class="stat-icon stat-icon-purple">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>

            </div>

        </section>


        <!-- =================================================
             FILTER TOOLBAR
        ================================================= -->

        <section class="filter-card">

            <div>

                <strong>
                    Registered Accounts
                </strong>

                <div class="text-muted small mt-1">
                    Use the filters to find a specific user.
                </div>

            </div>


            <div class="filter-group">

                <select
                    id="districtFilter"
                    class="filter-control"
                >

                    <option value="all">
                        All Districts
                    </option>

                    <?php foreach ($districts as $district): ?>

                        <option
                            value="<?= e(strtolower($district)) ?>"
                        >
                            <?= e($district) ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <select
                    id="sortFilter"
                    class="filter-control"
                >

                    <option value="newest">
                        Newest First
                    </option>

                    <option value="oldest">
                        Oldest First
                    </option>

                </select>

            </div>

        </section>


        <!-- =================================================
             USER CARDS
        ================================================= -->

        <section
            class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4"
            id="userCardsGrid"
        >

            <?php foreach ($users as $user): ?>

                <?php

                $userId =
                    (int)($user['user_id'] ?? 0);

                $userName =
                    trim(
                        (string)($user['name'] ?? 'Unknown User')
                    );

                $userEmail =
                    (string)($user['email'] ?? 'N/A');

                $userPhone =
                    (string)($user['phone'] ?? 'N/A');

                $userAddress =
                    (string)($user['address'] ?? 'N/A');

                $userPlace =
                    (string)($user['place'] ?? 'N/A');

                $userDistrict =
                    (string)($user['district'] ?? 'N/A');

                $userPincode =
                    (string)($user['pincode'] ?? 'N/A');

                $createdTimestamp =
                    !empty($user['created_at'])
                        ? strtotime($user['created_at'])
                        : 0;

                $initials =
                    userInitials($userName);


                $searchData = strtolower(
                    $userName . ' ' .
                    $userEmail . ' ' .
                    $userPhone . ' ' .
                    $userAddress . ' ' .
                    $userPlace . ' ' .
                    $userDistrict . ' ' .
                    $userPincode . ' ' .
                    $userId
                );


                $modalUser = [
                    'user_id' =>
                        $userId,

                    'name' =>
                        $userName,

                    'email' =>
                        $userEmail,

                    'phone' =>
                        $userPhone,

                    'address' =>
                        $userAddress,

                    'place' =>
                        $userPlace,

                    'district' =>
                        $userDistrict,

                    'pincode' =>
                        $userPincode,

                    'total_pickups' =>
                        (int)$user['total_pickups'],

                    'completed_pickups' =>
                        (int)$user['completed_pickups'],

                    'in_progress_pickups' =>
                        (int)$user['in_progress_pickups']
                ];


                $modalJson = json_encode(
                    $modalUser,
                    JSON_HEX_TAG |
                    JSON_HEX_APOS |
                    JSON_HEX_QUOT |
                    JSON_HEX_AMP |
                    JSON_UNESCAPED_UNICODE
                );

                ?>


                <div
                    class="col user-card-item"
                    data-search="<?= e($searchData) ?>"
                    data-district="<?= e(strtolower($userDistrict)) ?>"
                    data-date="<?= $createdTimestamp ?>"
                >

                    <article class="user-card d-flex flex-column">

                        <!-- User Header -->

                        <div class="d-flex align-items-center gap-3 mb-4">

                            <div class="avatar-circle">
                                <?= e($initials) ?>
                            </div>


                            <div class="overflow-hidden">

                                <h5 class="fw-bold mb-1 text-dark text-truncate">
                                    <?= e($userName) ?>
                                </h5>


                                <span class="user-id">
                                    User ID:
                                    #<?= sprintf('%04d', $userId) ?>
                                </span>

                            </div>

                        </div>


                        <!-- Contact Information -->

                        <div class="contact-list mb-4">

                            <div class="contact-item">

                                <i class="bi bi-envelope"></i>

                                <span>
                                    <?= e($userEmail) ?>
                                </span>

                            </div>


                            <div class="contact-item">

                                <i class="bi bi-telephone"></i>

                                <span>
                                    <?= e($userPhone) ?>
                                </span>

                            </div>


                            <div class="contact-item">

                                <i class="bi bi-geo-alt"></i>

                                <span>
                                    <?= e($userPlace) ?>,
                                    <?= e($userDistrict) ?> -
                                    <?= e($userPincode) ?>
                                </span>

                            </div>

                        </div>


                        <!-- Activity Summary -->

                        <div class="row g-2 mb-2">

                            <div class="col-6">

                                <div class="info-pill">

                                    <span class="info-pill-label">
                                        Total Pickups
                                    </span>

                                    <span class="info-pill-value">
                                        <?= (int)$user['total_pickups'] ?>
                                    </span>

                                </div>

                            </div>


                            <div class="col-6">

                                <div class="info-pill">

                                    <span class="info-pill-label">
                                        Completed
                                    </span>

                                    <span
                                        class="info-pill-value"
                                        style="color:var(--primary-dark);"
                                    >
                                        <?= (int)$user['completed_pickups'] ?>
                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- Footer -->

                        <div class="card-footer d-flex align-items-center justify-content-between gap-2 mt-auto">

                            <span class="joined-date">

                                <i class="bi bi-calendar3 me-1"></i>

                                Joined

                                <?=
                                    $createdTimestamp
                                        ? e(
                                            date(
                                                'M d, Y',
                                                $createdTimestamp
                                            )
                                        )
                                        : 'N/A'
                                ?>

                            </span>


                            <div class="btn-group gap-1">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-view"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewDetailsModal"
                                    data-user="<?= e($modalJson) ?>"
                                >
                                    <i class="bi bi-eye"></i>
                                    View
                                </button>


                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger btn-delete"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteConfirmModal"
                                    data-userid="<?= $userId ?>"
                                    data-username="<?= e($userName) ?>"
                                    data-useremail="<?= e($userEmail) ?>"
                                >
                                    <i class="bi bi-trash3"></i>
                                </button>

                            </div>

                        </div>

                    </article>

                </div>

            <?php endforeach; ?>

        </section>


        <!-- =================================================
             EMPTY STATE
        ================================================= -->

        <section
            id="emptyState"
            class="empty-state d-none"
        >

            <div class="empty-state-icon">
                <i class="bi bi-search"></i>
            </div>


            <h4 class="fw-bold text-dark">
                No users found
            </h4>


            <p class="text-muted">
                No accounts match your current filters.
            </p>


            <button
                type="button"
                class="btn btn-success px-4"
                id="resetFiltersButton"
            >
                <i class="bi bi-arrow-counterclockwise me-2"></i>
                Reset Filters
            </button>

        </section>

    </main>


    <!-- =================================================
         VIEW USER MODAL
    ================================================= -->

    <div
        class="modal fade"
        id="viewDetailsModal"
        tabindex="-1"
        aria-labelledby="viewDetailsModalLabel"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-dialog-centered modal-lg">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="viewDetailsModalLabel"
                    >
                        User Details Overview
                    </h5>


                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>

                </div>


                <div class="modal-body p-4">

                    <div class="d-flex align-items-center gap-3 mb-4">

                        <div
                            id="modalAvatar"
                            class="avatar-circle"
                            style="width:64px;height:64px;"
                        ></div>


                        <div>

                            <h5
                                id="modalUserName"
                                class="fw-bold mb-0"
                            ></h5>


                            <small
                                id="modalUserID"
                                class="text-muted d-block"
                            ></small>

                        </div>

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <span class="modal-detail-label">
                                Email Address
                            </span>

                            <span
                                id="modalUserEmail"
                                class="modal-detail-value"
                            ></span>

                        </div>


                        <div class="col-md-6">

                            <span class="modal-detail-label">
                                Phone Number
                            </span>

                            <span
                                id="modalUserPhone"
                                class="modal-detail-value"
                            ></span>

                        </div>


                        <div class="col-12">

                            <span class="modal-detail-label">
                                Full Address
                            </span>

                            <span
                                id="modalUserAddress"
                                class="modal-detail-value"
                            ></span>

                        </div>


                        <div class="col-md-4">

                            <span class="modal-detail-label">
                                Place
                            </span>

                            <span
                                id="modalUserPlace"
                                class="modal-detail-value"
                            ></span>

                        </div>


                        <div class="col-md-4">

                            <span class="modal-detail-label">
                                District
                            </span>

                            <span
                                id="modalUserDistrict"
                                class="modal-detail-value"
                            ></span>

                        </div>


                        <div class="col-md-4">

                            <span class="modal-detail-label">
                                Pincode
                            </span>

                            <span
                                id="modalUserPincode"
                                class="modal-detail-value"
                            ></span>

                        </div>

                    </div>


                    <hr class="my-4">


                    <h6 class="fw-bold mb-3">
                        Scrap Activity Overview
                    </h6>


                    <div class="row g-3">

                        <div class="col-4">

                            <div class="modal-stat">

                                <span>
                                    Total Pickups
                                </span>

                                <strong id="modalTotalPickups">
                                    0
                                </strong>

                            </div>

                        </div>


                        <div class="col-4">

                            <div class="modal-stat">

                                <span>
                                    Completed
                                </span>

                                <strong
                                    id="modalCompletedPickups"
                                    style="color:var(--primary);"
                                >
                                    0
                                </strong>

                            </div>

                        </div>


                        <div class="col-4">

                            <div class="modal-stat">

                                <span>
                                    In Progress
                                </span>

                                <strong
                                    id="modalInProgressPickups"
                                    style="color:var(--warning);"
                                >
                                    0
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =================================================
         DELETE MODAL
    ================================================= -->

    <div
        class="modal fade"
        id="deleteConfirmModal"
        tabindex="-1"
        aria-labelledby="deleteConfirmModalLabel"
        aria-hidden="true"
    >

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="deleteConfirmModalLabel"
                    >
                        Confirm Deletion
                    </h5>


                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>

                </div>


                <div class="modal-body p-4 text-center">

                    <div class="text-danger mb-3">

                        <i
                            class="bi bi-exclamation-triangle-fill"
                            style="font-size:3.5rem;"
                        ></i>

                    </div>


                    <p class="text-muted mb-3">

                        Are you sure you want to delete
                        <strong id="deleteTargetName"></strong>?

                        This will remove the user and related activity records.

                    </p>


                    <div class="p-3 bg-light rounded-3 text-start mb-4">

                        <div>
                            <strong>User ID:</strong>
                            #
                            <span id="deleteTargetId"></span>
                        </div>


                        <div>
                            <strong>Email:</strong>
                            <span id="deleteTargetEmail"></span>
                        </div>

                    </div>


                    <form
                        method="POST"
                        action="manage_users.php"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="delete_user"
                        >


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($csrfToken) ?>"
                        >


                        <input
                            type="hidden"
                            name="user_id"
                            id="deleteTargetInput"
                        >


                        <div class="d-flex justify-content-end gap-2">

                            <button
                                type="button"
                                class="btn btn-light px-4 rounded-3"
                                data-bs-dismiss="modal"
                            >
                                Cancel
                            </button>


                            <button
                                type="submit"
                                class="btn btn-danger px-4 rounded-3"
                            >
                                Delete Permanently
                            </button>

                        </div>

                    </form>

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

                const searchInput =
                    document.getElementById(
                        'searchInput'
                    );

                const districtFilter =
                    document.getElementById(
                        'districtFilter'
                    );

                const sortFilter =
                    document.getElementById(
                        'sortFilter'
                    );

                const userGrid =
                    document.getElementById(
                        'userCardsGrid'
                    );

                const emptyState =
                    document.getElementById(
                        'emptyState'
                    );

                const resetFiltersButton =
                    document.getElementById(
                        'resetFiltersButton'
                    );

                const cards =
                    Array.from(
                        document.querySelectorAll(
                            '.user-card-item'
                        )
                    );


                function filterAndSortCards() {

                    const searchTerm =
                        searchInput.value
                            .toLowerCase()
                            .trim();

                    const selectedDistrict =
                        districtFilter.value;

                    const selectedSort =
                        sortFilter.value;

                    const visibleCards = [];


                    cards.forEach(
                        function (card) {

                            const searchData =
                                (
                                    card.dataset.search ||
                                    ''
                                ).toLowerCase();

                            const district =
                                (
                                    card.dataset.district ||
                                    ''
                                ).toLowerCase();

                            const matchesSearch =
                                searchData.includes(
                                    searchTerm
                                );

                            const matchesDistrict =
                                selectedDistrict === 'all' ||
                                district === selectedDistrict;

                            const visible =
                                matchesSearch &&
                                matchesDistrict;

                            card.style.display =
                                visible
                                    ? ''
                                    : 'none';

                            if (visible) {
                                visibleCards.push(card);
                            }
                        }
                    );


                    visibleCards.sort(
                        function (firstCard, secondCard) {

                            const firstDate =
                                Number(
                                    firstCard.dataset.date || 0
                                );

                            const secondDate =
                                Number(
                                    secondCard.dataset.date || 0
                                );

                            if (selectedSort === 'oldest') {
                                return firstDate - secondDate;
                            }

                            return secondDate - firstDate;
                        }
                    );


                    visibleCards.forEach(
                        function (card) {
                            userGrid.appendChild(card);
                        }
                    );


                    emptyState.classList.toggle(
                        'd-none',
                        visibleCards.length !== 0
                    );
                }


                window.resetFilters =
                    function () {
                        searchInput.value = '';
                        districtFilter.value = 'all';
                        sortFilter.value = 'newest';

                        filterAndSortCards();
                    };


                searchInput.addEventListener(
                    'input',
                    filterAndSortCards
                );

                districtFilter.addEventListener(
                    'change',
                    filterAndSortCards
                );

                sortFilter.addEventListener(
                    'change',
                    filterAndSortCards
                );


                resetFiltersButton.addEventListener(
                    'click',
                    function () {
                        window.resetFilters();
                    }
                );


                // View User Modal

                const viewModal =
                    document.getElementById(
                        'viewDetailsModal'
                    );

                viewModal.addEventListener(
                    'show.bs.modal',
                    function (event) {

                        const button =
                            event.relatedTarget;

                        if (!button) {
                            return;
                        }


                        let user;

                        try {
                            user =
                                JSON.parse(
                                    button.dataset.user
                                );
                        } catch (error) {
                            console.error(
                                'Invalid user data.',
                                error
                            );

                            return;
                        }


                        function setValue(
                            elementId,
                            value
                        ) {
                            const element =
                                document.getElementById(
                                    elementId
                                );

                            if (element) {
                                element.textContent =
                                    value || 'N/A';
                            }
                        }


                        setValue(
                            'modalUserName',
                            user.name
                        );

                        setValue(
                            'modalUserID',
                            `User ID: #${String(
                                user.user_id || 0
                            ).padStart(4, '0')}`
                        );

                        setValue(
                            'modalUserEmail',
                            user.email
                        );

                        setValue(
                            'modalUserPhone',
                            user.phone
                        );

                        setValue(
                            'modalUserAddress',
                            user.address
                        );

                        setValue(
                            'modalUserPlace',
                            user.place
                        );

                        setValue(
                            'modalUserDistrict',
                            user.district
                        );

                        setValue(
                            'modalUserPincode',
                            user.pincode
                        );

                        setValue(
                            'modalTotalPickups',
                            user.total_pickups || 0
                        );

                        setValue(
                            'modalCompletedPickups',
                            user.completed_pickups || 0
                        );

                        setValue(
                            'modalInProgressPickups',
                            user.in_progress_pickups || 0
                        );

                        setValue(
                            'modalAvatar',
                            getInitials(
                                user.name || 'User'
                            )
                        );
                    }
                );


                // Delete Modal

                const deleteModal =
                    document.getElementById(
                        'deleteConfirmModal'
                    );

                deleteModal.addEventListener(
                    'show.bs.modal',
                    function (event) {

                        const button =
                            event.relatedTarget;

                        if (!button) {
                            return;
                        }


                        document.getElementById(
                            'deleteTargetId'
                        ).textContent =
                            button.dataset.userid || '0';


                        document.getElementById(
                            'deleteTargetName'
                        ).textContent =
                            button.dataset.username ||
                            'Unknown User';


                        document.getElementById(
                            'deleteTargetEmail'
                        ).textContent =
                            button.dataset.useremail ||
                            'N/A';


                        document.getElementById(
                            'deleteTargetInput'
                        ).value =
                            button.dataset.userid || '';
                    }
                );


                function getInitials(name) {

                    const parts =
                        name
                            .trim()
                            .split(/\s+/)
                            .filter(Boolean);

                    if (parts.length === 0) {
                        return 'U';
                    }

                    return (
                        parts[0].charAt(0) +
                        (
                            parts[1]
                                ? parts[1].charAt(0)
                                : ''
                        )
                    ).toUpperCase();
                }


                filterAndSortCards();

            }
        );

    </script>

</body>

</html>