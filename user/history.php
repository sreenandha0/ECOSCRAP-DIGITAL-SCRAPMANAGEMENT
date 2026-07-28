<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== "User"
) {
    redirect("../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT 
            activity.*, 
            collector.name AS collector_name, 
            collector.phone AS collector_phone 
        FROM activity
        LEFT JOIN user AS collector ON activity.collector_id = collector.user_id
        WHERE activity.user_id = ?
        ORDER BY activity.request_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Store rows in an array to output modals separately after the table
$rows = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
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

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Design System CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        :root {
          --primary: #10B981;
          --secondary: #047857;
          --accent: #0EA5E9;
          --bg-color: #F8FAFC;
          --surface: rgba(255, 255, 255, 0.7);
          --surface-border: rgba(15, 23, 42, 0.08);
          --text-main: #0F172A;
          --text-muted: #64748B;
          --font-main: 'Inter', sans-serif;
          --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
          --mouse-x: 50%;
          --mouse-y: 50%;
        }

        * {
          box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: var(--font-main);
            padding: 40px 20px;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .workspace-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* Top Header */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .topbar-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.03em;
        }

        .topbar-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 4px 0 0 0;
        }

        /* Table Card Container with Glassmorphism & Mouse Glow */
        .table-card {
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .mouse-glow {
            position: relative;
        }
        .mouse-glow::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(
                800px circle at var(--mouse-x) var(--mouse-y),
                rgba(16, 185, 129, 0.05),
                transparent 40%
            );
            z-index: 0;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .mouse-glow:hover::before { opacity: 1; }
        .mouse-glow > * { position: relative; z-index: 1; }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table th {
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--surface-border);
            background: transparent;
        }

        .custom-table td {
            padding: 16px;
            font-size: 14px;
            color: var(--text-main);
            border-bottom: 1px solid var(--surface-border);
            vertical-align: middle;
        }

        .custom-table tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge-custom {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .badge-approved {
            background: rgba(14, 165, 233, 0.1);
            color: #0284c7;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }

        .badge-assigned {
            background: rgba(99, 102, 241, 0.1);
            color: #4f46e5;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .badge-progress {
            background: rgba(15, 23, 42, 0.1);
            color: #0f172a;
            border: 1px solid rgba(15, 23, 42, 0.2);
        }

        .badge-completed {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Buttons */
        .btn-qr {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: var(--primary);
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-qr:hover {
            background: var(--secondary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            transform: translateY(-1px);
        }

        .btn-secondary-custom {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--surface-border);
            border-radius: 10px;
            color: var(--text-main);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
        }

        .btn-secondary-custom:hover {
            background: #ffffff;
            color: var(--text-main);
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
        }

        .btn-view-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: white;
            border: 1px solid var(--surface-border);
            color: var(--text-main);
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            transition: var(--transition);
        }

        .btn-view-action:hover {
            background: var(--bg-color);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Modal Customizations */
        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        }

        .modal-header {
            border-bottom: 1px solid var(--surface-border);
            padding: 20px 24px;
        }

        .modal-body {
            padding: 24px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed var(--surface-border);
            font-size: 14px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-main);
            text-align: right;
        }

        .collector-box {
            background: rgba(14, 165, 233, 0.05);
            border: 1px solid rgba(14, 165, 233, 0.2);
            border-radius: 12px;
            padding: 14px;
            margin-top: 16px;
        }

        @media (max-width: 768px) {
            body {
                padding: 20px 12px;
            }
            .table-card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

    <main class="workspace-container">

        <!-- Header -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>
                    <i class="ri-history-line" style="color: var(--primary);"></i>
                    My Pickup Requests
                </h1>
                <p>Track statuses, view assigned collectors, access QR passes, and check request details.</p>
            </div>

            <a href="dashboard.php" class="btn-secondary-custom">
                <i class="ri-arrow-left-line"></i> Dashboard
            </a>
        </header>

        <!-- Requests Card -->
        <div class="table-card mouse-glow" id="cardGlow">

            <?php if (!empty($rows)) { ?>

                <div class="table-responsive">
                    <table class="custom-table align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Scrap Type</th>
                                <th>Weight</th>
                                <th>Preferred Date</th>
                                <th>Status</th>
                                <th>QR Pass</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row) { 
                                $status = $row['status'];

                                // Determine Badge Style
                                $badgeClass = "badge-pending";
                                if ($status === "Approved") $badgeClass = "badge-approved";
                                elseif ($status === "Assigned") $badgeClass = "badge-assigned";
                                elseif ($status === "In Progress") $badgeClass = "badge-progress";
                                elseif ($status === "Completed") $badgeClass = "badge-completed";
                                elseif ($status === "Rejected") $badgeClass = "badge-rejected";
                            ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($row['activity_id']); ?></strong></td>
                                    <td><strong><?= htmlspecialchars($row['scrap_type']); ?></strong></td>
                                    <td><?= htmlspecialchars($row['scrap_weight']); ?> kg</td>
                                    <td><?= htmlspecialchars($row['preferred_pickup_date']); ?></td>
                                    <td>
                                        <span class="badge-custom <?= $badgeClass; ?>">
                                            <?= htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($status !== "Pending" && $status !== "Rejected") { ?>
                                            <a class="btn-qr" target="_blank" href="qr.php?id=<?= htmlspecialchars($row['activity_id']); ?>">
                                                <i class="ri-qr-code-line"></i> View QR
                                            </a>
                                        <?php } else { ?>
                                            <span style="color: var(--text-muted);">—</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <button class="btn-view-action" data-bs-toggle="modal" data-bs-target="#modal<?= htmlspecialchars($row['activity_id']); ?>">
                                            <i class="ri-eye-line"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            <?php } else { ?>

                <!-- Empty State -->
                <div class="text-center py-5">
                    <div class="mb-3" style="width: 70px; height: 70px; background: rgba(16, 185, 129, 0.1); color: var(--primary); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 36px;">
                        <i class="ri-inbox-archive-line"></i>
                    </div>
                    <h4 class="fw-bold mb-1">No Pickup Requests Found</h4>
                    <p class="text-muted mb-4">You haven't created any scrap pickup requests yet.</p>
                    <a href="create_request.php" class="btn-qr" style="padding: 10px 24px; text-decoration: none;">
                        <i class="ri-add-line"></i> Create Pickup Request
                    </a>
                </div>

            <?php } ?>

        </div>

    </main>

    <!-- Modals Outputted Outside the Table for Valid HTML -->
    <?php if (!empty($rows)) { 
        foreach ($rows as $row) { 
            $status = $row['status'];
            $badgeClass = "badge-pending";
            if ($status === "Approved") $badgeClass = "badge-approved";
            elseif ($status === "Assigned") $badgeClass = "badge-assigned";
            elseif ($status === "In Progress") $badgeClass = "badge-progress";
            elseif ($status === "Completed") $badgeClass = "badge-completed";
            elseif ($status === "Rejected") $badgeClass = "badge-rejected";
    ?>
        <div class="modal fade" id="modal<?= htmlspecialchars($row['activity_id']); ?>" tabindex="-1" aria-labelledby="modalLabel<?= htmlspecialchars($row['activity_id']); ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="modalLabel<?= htmlspecialchars($row['activity_id']); ?>">
                            Pickup Details #<?= htmlspecialchars($row['activity_id']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="detail-row">
                            <span class="detail-label">Scrap Type</span>
                            <span class="detail-value"><?= htmlspecialchars($row['scrap_type']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Estimated Weight</span>
                            <span class="detail-value"><?= htmlspecialchars($row['scrap_weight']); ?> kg</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Pickup Date</span>
                            <span class="detail-value"><?= htmlspecialchars($row['preferred_pickup_date']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Preferred Time</span>
                            <span class="detail-value"><?= htmlspecialchars($row['pickup_time'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status</span>
                            <span class="detail-value">
                                <span class="badge-custom <?= $badgeClass; ?>"><?= htmlspecialchars($status); ?></span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Pincode</span>
                            <span class="detail-value"><?= htmlspecialchars($row['pickup_pincode'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row" style="flex-direction: column; gap: 4px; align-items: flex-start;">
                            <span class="detail-label">Pickup Address</span>
                            <span class="detail-value text-start mt-1" style="font-weight: 500;">
                                <?= htmlspecialchars($row['pickup_address']); ?>
                            </span>
                        </div>

                        <?php if (!empty($row['collector_name'])) { ?>
                            <div class="collector-box">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="ri-user-received-line text-info fs-5"></i>
                                    <span class="fw-bold text-dark" style="font-size: 14px;">Assigned Collector Details</span>
                                </div>
                                <div class="detail-row" style="padding: 4px 0; border: none;">
                                    <span class="detail-label">Name</span>
                                    <span class="detail-value"><?= htmlspecialchars($row['collector_name']); ?></span>
                                </div>
                                <div class="detail-row" style="padding: 4px 0; border: none;">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value">
                                        <a href="tel:<?= htmlspecialchars($row['collector_phone']); ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['collector_phone']); ?>
                                        </a>
                                    </span>
                                </div>
                            </div>
                        <?php } ?>

                        <?php if (!empty($row['remarks'])) { ?>
                            <div class="detail-row mt-2" style="flex-direction: column; gap: 4px; align-items: flex-start;">
                                <span class="detail-label">Remarks</span>
                                <span class="detail-value text-start mt-1" style="font-weight: 400; color: #475569;">
                                    <?= htmlspecialchars($row['remarks']); ?>
                                </span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php 
        } 
    } 
    ?>

    <!-- Mouse Glow Coordinate Script -->
    <script>
        const card = document.getElementById('cardGlow');
        if (card) {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.style.setProperty('--mouse-x', `${x}px`);
                card.style.setProperty('--mouse-y', `${y}px`);
            });
        }
    </script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>