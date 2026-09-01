<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'User') {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$user_stmt = $conn->prepare("SELECT name FROM user WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result()->fetch_assoc();
$current_user_name = $user_res['name'] ?? 'User';
$user_stmt->close();

$sql = "
    SELECT 
        a.*, 
        c.name AS collector_name, 
        c.phone AS collector_phone,
        c.email AS collector_email,
        c.vehicle_no AS collector_vehicle
    FROM activity a
    LEFT JOIN scrapcollector c 
        ON a.collector_id = c.collector_id
    WHERE a.user_id = ?
    ORDER BY a.request_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$pending_count = 0;
$approved_count = 0;
$assigned_count = 0;
$in_progress_count = 0;
$completed_count = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        $st = strtolower(trim($row['status'] ?? 'pending'));

        if ($st === 'pending') $pending_count++;
        elseif ($st === 'approved') $approved_count++;
        elseif ($st === 'assigned') $assigned_count++;
        elseif ($st === 'in progress') $in_progress_count++;
        elseif ($st === 'verified' || $st === 'completed') $completed_count++;
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pickup Requests | EcoScrap</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --eco-light: #82c843;
            --eco-primary: #2e7d32;
            --eco-primary-dark: #236128;
            --eco-dark: #004d40;
            --eco-accent: #00b4d8;
            --body-bg: #f1f5f4;
            --text-main: #16342f;
            --text-muted: #64748b;
            --text-soft: #94a3b8;
            --border: #e6eeeb;
            --white: #ffffff;
            --shadow-sm: 0 8px 25px rgba(22, 52, 47, 0.06);
            --shadow-md: 0 18px 45px rgba(22, 52, 47, 0.10);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
            --spring: cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 90% 0%, rgba(130, 200, 67, 0.14), transparent 30%),
                var(--body-bg);
            color: var(--text-main);
            font-family: "DM Sans", sans-serif;
            overflow-x: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .workspace-container {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 20px 50px;
            position: relative;
            z-index: 1;
        }

        .page-head {
            margin-bottom: 22px;
        }

        .page-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--eco-primary);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
            transition: 0.25s ease;
        }

        .page-back:hover {
            color: var(--eco-primary-dark);
            transform: translateX(-2px);
        }

        .page-title {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: clamp(28px, 3vw, 38px);
            color: var(--eco-dark);
            letter-spacing: -0.04em;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.65;
            max-width: 760px;
        }

        .impact-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            min-height: 150px;
            margin-bottom: 25px;
            padding: 27px 31px;
            border-radius: var(--radius-lg);
            background: linear-gradient(120deg, rgba(0, 77, 64, 0.97), rgba(46, 125, 50, 0.95));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .impact-card::before {
            position: absolute;
            top: -75px;
            right: 13%;
            width: 210px;
            height: 210px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            content: "";
        }

        .impact-card::after {
            position: absolute;
            top: -35px;
            right: 5%;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            content: "";
        }

        .impact-info {
            position: relative;
            z-index: 2;
            max-width: 700px;
        }

        .impact-info .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            color: var(--eco-light);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .impact-info h2 {
            margin-bottom: 7px;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 20px;
        }

        .impact-info p {
            max-width: 560px;
            color: rgba(255,255,255,0.72);
            font-size: 12px;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            position: relative;
            z-index: 2;
            min-width: 330px;
        }

        .stat-pill {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 76px;
        }

        .stat-icon-wrapper {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            background: rgba(130, 200, 67, 0.16);
            color: #dff7c7;
            flex: 0 0 auto;
        }

        .stat-val {
            font-size: 1.1rem;
            font-weight: 800;
            line-height: 1.2;
            color: #ffffff;
        }

        .stat-lbl {
            font-size: 0.75rem;
            color: #cbd5e1;
            font-weight: 500;
        }

        .filter-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-bottom: 22px;
            box-shadow: var(--shadow-sm);
        }

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-soft);
            font-size: 1.05rem;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #ffffff;
            font-size: 0.92rem;
            color: var(--text-main);
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--eco-primary);
            box-shadow: 0 0 0 4px rgba(130, 200, 67, 0.12);
        }

        .card-wrapper {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
            transition: all 0.25s ease;
        }

        .card-wrapper:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: rgba(130, 200, 67, 0.35);
        }

        .status-badge {
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-pending { background: rgba(130, 200, 67, 0.12); color: var(--eco-primary); }
        .badge-approved { background: rgba(130, 200, 67, 0.16); color: var(--eco-primary-dark); }
        .badge-assigned { background: rgba(0, 180, 216, 0.12); color: var(--eco-accent); }
        .badge-in-progress { background: rgba(36, 97, 40, 0.12); color: var(--eco-dark); }
        .badge-completed { background: rgba(46, 125, 50, 0.16); color: var(--eco-primary); }

        .collector-box {
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            margin: 16px 0;
        }

        .btn-custom-outline,
        .btn-custom-primary {
            border-radius: 12px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.25s ease;
        }

        .btn-custom-outline {
            background: #ffffff;
            border: 1px solid var(--border);
            color: var(--text-main);
        }

        .btn-custom-outline:hover {
            border-color: var(--eco-light);
            color: var(--eco-primary);
            background: rgba(130, 200, 67, 0.05);
        }

        .btn-custom-primary {
            background: linear-gradient(135deg, var(--eco-primary) 0%, var(--eco-primary-dark) 100%);
            color: #ffffff;
            border: none;
        }

        .btn-custom-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(46, 125, 50, 0.18);
            color: #ffffff;
        }

        .empty-state {
            background: rgba(255, 255, 255, 0.92);
            border: 1px dashed #cbd5e1;
            border-radius: 22px;
            padding: 58px 24px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .empty-icon {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            margin: 0 auto 18px;
            display: grid;
            place-items: center;
            background: rgba(130, 200, 67, 0.12);
            color: var(--eco-primary);
            font-size: 2.1rem;
        }

        .text-success-eco {
            color: var(--eco-primary) !important;
        }

        .text-muted-eco {
            color: var(--text-muted) !important;
        }

        @media (max-width: 992px) {
            .impact-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                min-width: 0;
            }
        }

        @media (max-width: 768px) {
            .workspace-container {
                padding: 18px 12px 36px;
            }

            .impact-card {
                padding: 22px 18px;
                border-radius: 20px;
            }

            .page-title {
                font-size: 1.55rem;
            }

            .card-wrapper {
                padding: 18px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 560px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="workspace-container">
    <header class="page-head">
        <a href="dashboard.php" class="page-back"><i class="ri-arrow-left-line"></i> Back to Dashboard</a>
        <h1 class="page-title">My Pickup Requests</h1>
        <p class="page-subtitle">Track each request, review collector details, and follow your pickup progress in one place.</p>
    </header>

    <section class="impact-card">
        <div class="impact-info">
            <p class="eyebrow"><i class="ri-recycle-line"></i> Pickup history</p>
            <h2>Track all your scrap pickup requests</h2>
            <p>Review request status, assigned collector details, and service progress in a clean timeline-style dashboard.</p>
        </div>

        <div class="stats-grid" aria-label="Pickup request statistics">
            <div class="stat-pill">
                <div class="stat-icon-wrapper"><i class="ri-time-line"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$pending_count ?></div>
                    <div class="stat-lbl">Pending</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="stat-icon-wrapper"><i class="ri-check-line"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$approved_count ?></div>
                    <div class="stat-lbl">Approved</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="stat-icon-wrapper"><i class="ri-user-follow-line"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$assigned_count ?></div>
                    <div class="stat-lbl">Assigned</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="stat-icon-wrapper"><i class="ri-truck-line"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$in_progress_count ?></div>
                    <div class="stat-lbl">In Progress</div>
                </div>
            </div>
            <div class="stat-pill">
                <div class="stat-icon-wrapper"><i class="ri-award-line"></i></div>
                <div>
                    <div class="stat-val"><?= (int)$completed_count ?></div>
                    <div class="stat-lbl">Completed</div>
                </div>
            </div>
        </div>
    </section>

    <div class="filter-card">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="search-box">
                    <i class="ri-search-2-line"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search pickup requests...">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <select id="statusSelect" class="search-input">
                    <option value="">Status (All)</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="assigned">Assigned</option>
                    <option value="in progress">In Progress</option>
                    <option value="verified">Verified / Completed</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select id="scrapTypeSelect" class="search-input">
                    <option value="">Scrap Type (All)</option>
                    <option value="plastic">Plastic</option>
                    <option value="metal">Metal</option>
                    <option value="glass">Glass</option>
                    <option value="paper">Paper</option>
                    <option value="e-waste">E-Waste</option>
                </select>
            </div>
        </div>
    </div>

    <?php if (!empty($rows)) : ?>
        <?php foreach ($rows as $row) :
            $status = $row['status'] ?? 'Pending';
            $status_clean = strtolower(trim($status));
            $scrapType = $row['scrap_type'] ?? 'General';
            $collectorName = $row['collector_name'] ?? '';
            $collectorPhone = $row['collector_phone'] ?? '';
            $collectorVehicle = $row['collector_vehicle'] ?? '';
            $isAccepted = !empty($collectorName) && !in_array($status_clean, ['pending', 'approved', 'assigned'], true);
        ?>
            <article
                class="card-wrapper"
                data-status="<?= e($status_clean) ?>"
                data-type="<?= e(strtolower($scrapType)) ?>"
                data-search="<?= e(strtolower(($row['pickup_address'] ?? '') . ' ' . ($row['pickup_pincode'] ?? '') . ' ' . ($row['scrap_type'] ?? '') . ' ' . ($row['status'] ?? '') . ' ' . ($collectorName ?? ''))) ?>"
            >
                <div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap">
                    <span class="fw-bold text-dark fs-6">Request #REQ-<?= e($row['activity_id'] ?? '') ?></span>

                    <?php if ($status_clean === 'pending') : ?>
                        <span class="status-badge badge-pending"><i class="ri-time-line"></i> Pending</span>
                    <?php elseif ($status_clean === 'approved') : ?>
                        <span class="status-badge badge-approved"><i class="ri-checkbox-circle-line"></i> Approved</span>
                    <?php elseif ($status_clean === 'assigned') : ?>
                        <span class="status-badge badge-assigned"><i class="ri-user-follow-line"></i> Assigned</span>
                    <?php elseif ($status_clean === 'in progress') : ?>
                        <span class="status-badge badge-in-progress"><i class="ri-truck-line"></i> In Progress</span>
                    <?php elseif ($status_clean === 'verified' || $status_clean === 'completed') : ?>
                        <span class="status-badge badge-completed"><i class="ri-award-line"></i> Completed</span>
                    <?php endif; ?>
                </div>

                <div class="row align-items-center g-3">
                    <div class="col-md-7">
                        <h5 class="fw-bold mb-1 text-success-eco"><?= e($scrapType) ?> Scrap</h5>
                        <p class="text-muted-eco small mb-1">Pickup Address: <?= e($row['pickup_address'] ?? '') ?></p>
                        <p class="text-muted-eco small mb-0">Pincode: <?= e($row['pickup_pincode'] ?? '') ?></p>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <div class="fw-bold fs-6"><?= e($row['scrap_weight'] ?? '') ?> kg</div>
                        <div class="text-muted-eco small">Pickup Date: <?= e($row['preferred_pickup_date'] ?? '') ?> (<?= e($row['pickup_time'] ?? '') ?>)</div>
                        <?php if (!empty($row['amount'])) : ?>
                            <div class="fw-bold text-success fs-5">₹ <?= e($row['amount']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isAccepted) : ?>
                    <div class="collector-box">
                        <span class="fw-bold d-block text-muted small mb-2">Assigned Collector</span>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <div class="fw-bold text-dark"><?= e($collectorName) ?></div>
                                <div class="small text-muted">
                                    <i class="ri-phone-line me-1"></i><?= e($collectorPhone) ?>
                                    <?php if (!empty($collectorVehicle)) : ?>
                                        <span class="ms-2">| <i class="ri-car-line ms-1 me-1"></i><?= e($collectorVehicle) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <a href="tel:<?= e($collectorPhone) ?>" class="btn-custom-outline">
                                    <i class="ri-phone-fill"></i> Call Collector
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3 pt-2 border-top flex-wrap">
                    <a href="track_status.php?id=<?= e($row['activity_id'] ?? '') ?>" class="btn-custom-outline">
                        <i class="ri-eye-line"></i> View Details
                    </a>

                    <?php if ($status_clean === 'assigned' || $status_clean === 'in progress') : ?>
                        <a href="track_status.php?id=<?= e($row['activity_id'] ?? '') ?>" class="btn-custom-outline">
                            <i class="ri-map-pin-time-line"></i> Track Status
                        </a>
                    <?php endif; ?>

                    <?php if ($status_clean === 'in progress' && !empty($row['qr_code'])) : ?>
                        <a href="../uploads/qr/<?= e($row['qr_code']) ?>" target="_blank" rel="noopener noreferrer" class="btn-custom-primary">
                            <i class="ri-qr-code-line"></i> View QR Pass
                        </a>
                    <?php endif; ?>

                    <?php if ($status_clean === 'verified' || $status_clean === 'completed') : ?>
                        <a href="track_status.php?id=<?= e($row['activity_id'] ?? '') ?>" class="btn-custom-outline">
                            <i class="ri-file-list-line"></i> View Receipt
                        </a>
                        <?php if (empty($row['rating'])) : ?>
                            <a href="feedback.php?id=<?= e($row['activity_id'] ?? '') ?>" class="btn-custom-primary">
                                <i class="ri-star-line"></i> Leave Feedback
                            </a>
                        <?php else : ?>
                            <span class="btn-custom-outline" style="color: var(--eco-primary-dark); border-color: rgba(130,200,67,0.35);">
                                <i class="ri-star-fill"></i> Rated <?= (int)$row['rating'] ?>/5
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="ri-inbox-line"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1">No Requests Found</h5>
            <p class="text-muted small mb-0">You haven't scheduled any pickup requests yet.</p>
        </div>
    <?php endif; ?>
</main>

<script>
const searchInput = document.getElementById('searchInput');
const statusSelect = document.getElementById('statusSelect');
const scrapTypeSelect = document.getElementById('scrapTypeSelect');

function filterCards() {
    const searchVal = searchInput.value.toLowerCase().trim();
    const statusVal = statusSelect.value.toLowerCase().trim();
    const typeVal = scrapTypeSelect.value.toLowerCase().trim();

    document.querySelectorAll('.card-wrapper').forEach(card => {
        const text = card.getAttribute('data-search') || '';
        const cardStatus = card.getAttribute('data-status') || '';
        const cardType = card.getAttribute('data-type') || '';

        const matchesSearch = !searchVal || text.includes(searchVal);
        const matchesStatus = !statusVal || cardStatus.includes(statusVal);
        const matchesType = !typeVal || cardType.includes(typeVal);

        card.style.display = (matchesSearch && matchesStatus && matchesType) ? 'block' : 'none';
    });
}

searchInput.addEventListener('input', filterCards);
statusSelect.addEventListener('change', filterCards);
scrapTypeSelect.addEventListener('change', filterCards);
</script>
</body>
</html>