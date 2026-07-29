<?php
session_start();

// Database Connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "ecoscrap_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure an Activity ID is provided
$activity_id = $_GET['id'] ?? null;
if (!$activity_id) {
    header("Location: my_requests.php");
    exit();
}

// Fetch Activity along with Collector Details from `scrapcollector` table
$sql = "SELECT 
            a.*, 
            c.name AS collector_name, 
            c.phone AS collector_phone,
            c.vehicle_no AS collector_vehicle,
            c.profile_image AS collector_img
        FROM activity a
        LEFT JOIN scrapcollector c ON a.collector_id = c.collector_id
        WHERE a.activity_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h3>Request Not Found</h3><a href='my_requests.php'>Back to My Requests</a></div>");
}

// Helper function to map current status stage to numerical step
$status_clean = strtolower(trim($request['status'] ?? 'pending'));
$current_step = 1;

if ($status_clean === 'assigned') {
    $current_step = 2;
} elseif ($status_clean === 'in progress') {
    $current_step = 3;
} elseif ($status_clean === 'verified' || $status_clean === 'completed') {
    $current_step = 4;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Request #REQ-<?= htmlspecialchars($request['activity_id']); ?> | EcoScrap</title>

    <!-- Google Fonts & Remix Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #10B981;
            --secondary: #047857;
            --accent: #0EA5E9;
            --bg-color: #F8FAFC;
            --surface: rgba(255, 255, 255, 0.85);
            --surface-border: rgba(15, 23, 42, 0.08);
            --text-main: #0F172A;
            --text-muted: #64748B;
            --font-main: 'Inter', sans-serif;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            padding-bottom: 50px;
        }

        /* Navigation Header */
        .header-bar {
            background: var(--surface);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--surface-border);
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .brand-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .card-wrapper {
            background: var(--surface);
            backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.03);
            margin-bottom: 24px;
        }

        /* Timeline Step Tracker */
        .timeline-container {
            position: relative;
            padding: 20px 0;
            display: flex;
            justify-content: space-between;
        }

        .timeline-container::before {
            content: '';
            position: absolute;
            top: 40px;
            left: 10%;
            right: 10%;
            height: 4px;
            background: #E2E8F0;
            z-index: 1;
        }

        .timeline-progress {
            position: absolute;
            top: 40px;
            left: 10%;
            height: 4px;
            background: var(--primary);
            z-index: 1;
            transition: var(--transition);
        }

        .timeline-step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #E2E8F0;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 20px;
            font-weight: 700;
            border: 3px solid #FFF;
            transition: var(--transition);
        }

        .timeline-step.active .step-icon {
            background: var(--primary);
            color: #FFF;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2);
        }

        .timeline-step.completed .step-icon {
            background: var(--secondary);
            color: #FFF;
        }

        .step-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 2px;
        }

        .step-time {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Map Placeholder Visual */
        .map-box {
            background: #e2e8f0 url('https://maps.googleapis.com/maps/api/staticmap?center=Kerala,India&zoom=10&size=600x300&sensor=false') center/cover;
            height: 220px;
            border-radius: 14px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid var(--surface-border);
        }

        .map-overlay-badge {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            color: #fff;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Collector Box */
        .collector-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary);
            color: #FFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
        }

        .btn-call {
            background: var(--primary);
            color: #FFF;
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-call:hover {
            background: var(--secondary);
            color: #FFF;
        }
    </style>
</head>

<body>

    <!-- Navigation Header -->
    <header class="header-bar">
        <a href="history.php" class="brand-title">
            <i class="ri-arrow-left-line"></i>
            <span>Track Pickup</span>
        </a>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-semibold fs-7">
            Request #REQ-<?= htmlspecialchars($request['activity_id']); ?>
        </span>
    </header>

    <div class="container my-4">

        <!-- Status Timeline Progress Bar -->
        <div class="card-wrapper">
            <h6 class="fw-bold mb-4"><i class="ri-radar-line text-primary me-2"></i>Live Status Tracker</h6>

            <div class="timeline-container">
                <!-- Dynamic Width calculation for progress bar -->
                <div class="timeline-progress" style="width: <?= (($current_step - 1) / 3) * 80; ?>%;"></div>

                <!-- Step 1: Requested -->
                <div class="timeline-step <?= $current_step >= 1 ? 'active' : ''; ?> <?= $current_step > 1 ? 'completed' : ''; ?>">
                    <div class="step-icon"><i class="ri-file-list-3-line"></i></div>
                    <div class="step-title">Requested</div>
                    <div class="step-time"><?= date("d M Y, h:i A", strtotime($request['request_date'])); ?></div>
                </div>

                <!-- Step 2: Collector Assigned -->
                <div class="timeline-step <?= $current_step >= 2 ? 'active' : ''; ?> <?= $current_step > 2 ? 'completed' : ''; ?>">
                    <div class="step-icon"><i class="ri-user-received-line"></i></div>
                    <div class="step-title">Assigned</div>
                    <div class="step-time"><?= !empty($request['collector_name']) ? 'Collector Linked' : 'Pending...'; ?></div>
                </div>

                <!-- Step 3: Pickup In Progress -->
                <div class="timeline-step <?= $current_step >= 3 ? 'active' : ''; ?> <?= $current_step > 3 ? 'completed' : ''; ?>">
                    <div class="step-icon"><i class="ri-truck-line"></i></div>
                    <div class="step-title">In Progress</div>
                    <div class="step-time"><?= $current_step >= 3 ? 'Out for Pickup' : 'Awaiting'; ?></div>
                </div>

                <!-- Step 4: Verified & Completed -->
                <div class="timeline-step <?= $current_step == 4 ? 'active completed' : ''; ?>">
                    <div class="step-icon"><i class="ri-checkbox-circle-line"></i></div>
                    <div class="step-title">Verified</div>
                    <div class="step-time"><?= !empty($request['completed_at']) ? date("d M Y, h:i A", strtotime($request['completed_at'])) : 'Pending'; ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Details & Map -->
            <div class="col-lg-7">
                
                <!-- Map Tracking View -->
                <div class="card-wrapper p-3">
                    <div class="map-box mb-3">
                        <div class="map-overlay-badge">
                            <i class="ri-map-pin-2-fill text-danger me-1"></i> Pickup Location: PIN <?= htmlspecialchars($request['pickup_pincode']); ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center px-2">
                        <div>
                            <span class="text-muted small d-block">Scheduled Pickup Window</span>
                            <span class="fw-bold text-dark"><i class="ri-calendar-event-line text-primary me-1"></i> <?= htmlspecialchars($request['preferred_pickup_date']); ?> at <?= htmlspecialchars($request['pickup_time']); ?></span>
                        </div>
                        <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-2 rounded-3">
                            <?= strtoupper($request['status']); ?>
                        </span>
                    </div>
                </div>

                <!-- Scrap Item Details Card -->
                <div class="card-wrapper">
                    <h6 class="fw-bold mb-3"><i class="ri-recycle-line text-success me-2"></i>Scrap Details</h6>
                    <div class="row g-3">
                        <div class="col-6 col-sm-4">
                            <div class="p-3 border rounded-3 bg-light">
                                <span class="text-muted small d-block">Material</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($request['scrap_type']); ?></span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4">
                            <div class="p-3 border rounded-3 bg-light">
                                <span class="text-muted small d-block">Est. Weight</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($request['scrap_weight']); ?> kg</span>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="p-3 border rounded-3 bg-light">
                                <span class="text-muted small d-block">Est. Amount</span>
                                <span class="fw-bold text-success">
                                    <?= !empty($request['amount']) ? '₹ ' . htmlspecialchars($request['amount']) : 'Pending Evaluation'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 pt-3 border-top">
                        <span class="text-muted small d-block">Address</span>
                        <p class="fw-semibold text-dark mb-0">📍 <?= htmlspecialchars($request['pickup_address']); ?></p>
                    </div>
                </div>

            </div>

            <!-- Right Column: Collector Info & Actions -->
            <div class="col-lg-5">
                
                <!-- Collector Details Card -->
                <div class="card-wrapper">
                    <h6 class="fw-bold mb-3"><i class="ri-user-follow-line text-info me-2"></i>Assigned Collector</h6>

                    <?php if (!empty($request['collector_name'])) { ?>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="collector-avatar">
                                <?= strtoupper(substr($request['collector_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($request['collector_name']); ?></h6>
                                <span class="text-muted small"><i class="ri-car-line"></i> Vehicle: <?= htmlspecialchars($request['collector_vehicle']); ?></span>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="tel:<?= htmlspecialchars($request['collector_phone']); ?>" class="btn-call justify-content-center">
                                <i class="ri-phone-fill"></i> Call <?= htmlspecialchars($request['collector_phone']); ?>
                            </a>
                        </div>
                    <?php } else { ?>
                        <div class="text-center py-4 text-muted">
                            <i class="ri-time-line fs-2 d-block mb-1 text-warning"></i>
                            <span class="fw-semibold">Finding Nearby Collector...</span>
                            <p class="small text-muted mb-0">We will assign a collector in your area shortly.</p>
                        </div>
                    <?php } ?>
                </div>

                <!-- Verification QR Card -->
                <div class="card-wrapper text-center">
                    <h6 class="fw-bold mb-2 text-start"><i class="ri-qr-code-line text-primary me-2"></i>Pickup Verification</h6>
                    <p class="text-muted small text-start mb-3">Show this QR code to the collector upon arrival to verify handover.</p>

                    <?php if (!empty($request['qr_code'])) { ?>
                        <div class="p-3 bg-light rounded-3 d-inline-block border mb-2">
                            <img src="uploads/qr/<?= htmlspecialchars($request['qr_code']); ?>" alt="Pickup QR Code" class="img-fluid" style="max-width: 160px;">
                        </div>
                        <div>
                            <span class="badge <?= $request['qr_status'] === 'Used' ? 'bg-secondary' : 'bg-success'; ?> px-3 py-2">
                                Status: <?= htmlspecialchars($request['qr_status']); ?>
                            </span>
                        </div>
                    <?php } else { ?>
                        <div class="p-4 bg-light rounded-3 border">
                            <i class="ri-qr-scan-2-line fs-1 text-muted d-block mb-2"></i>
                            <span class="small text-muted">QR Pass will generate once collector arrives.</span>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>

    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>