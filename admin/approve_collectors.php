<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

if (
    !isset($_SESSION['admin_id']) && !isset($_SESSION['role']) ||
    $_SESSION['role'] != "Admin"
){
    redirect("../login.php");
}

$result = $conn->query("
SELECT *
FROM scrapcollector
ORDER BY created_at DESC
");

// Sort the collectors into two arrays based on status
$pending_collectors = [];
$processed_collectors = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($row['verification_status'] === 'Pending') {
            $pending_collectors[] = $row;
        } else {
            $processed_collectors[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collector Verification Queue | EcoScrap Admin</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            /* Brand Colors */
            --primary: #10B981;    /* Emerald Green */
            --secondary: #047857;  /* Forest Green */
            --accent: #0EA5E9;     /* Sky Blue */
            
            /* Backgrounds & Surface */
            --bg-color: #F8FAFC;
            --surface: rgba(255, 255, 255, 0.85);
            --surface-border: rgba(15, 23, 42, 0.08);
            
            /* Text */
            --text-main: #0F172A;
            --text-muted: #64748B;
            
            /* Utilities */
            --font-main: 'Inter', sans-serif;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            
            /* Status Badges */
            --badge-pending-bg: #FEF3C7;
            --badge-pending-text: #92400E;
            --badge-approved-bg: #DCFCE7;
            --badge-approved-text: #166534;
            --badge-rejected-bg: #FEE2E2;
            --badge-rejected-text: #991B1B;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* Ambient Glow Effect */
        .ambient-glow {
            position: fixed;
            top: -100px;
            right: -100px;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.08) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* --- NEW: Top Navigation Bar --- */
        .top-navbar {
            background: var(--surface);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--surface-border);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .nav-brand i {
            color: var(--primary);
            font-size: 26px;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* --- NEW: Back Button --- */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: #FFFFFF;
            border: 1px solid var(--surface-border);
            border-radius: 10px;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .btn-back:hover {
            background-color: #F1F5F9;
            border-color: #CBD5E1;
            transform: translateY(-1px);
        }

        /* Container */
        .workspace-container {
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
            padding: 40px 24px 80px 24px;
        }

        /* Glass Surface Wrappers */
        .glass-card {
            background: var(--surface);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.06);
            transition: var(--transition);
        }

        .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 45px rgba(0,0,0,0.08);
            border-color: rgba(16, 185, 129, 0.3);
        }

        /* Header Bar Layout */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title h1 {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-main);
        }

        .header-title p {
            font-size: 15px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box i {
            position: absolute;
            left: 16px;
            color: var(--text-muted);
            font-size: 20px;
        }

        .search-input {
            padding: 12px 16px 12px 46px;
            border: 1px solid var(--surface-border);
            border-radius: 12px;
            background: #FFFFFF;
            font-family: var(--font-main);
            font-size: 14px;
            outline: none;
            width: 300px;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }
        
        /* Section Dividers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 48px 0 24px 0;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--surface-border);
        }
        
        .section-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .badge-count {
            background-color: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
        }

        /* Card Queue Stack */
        .cards-queue {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .collector-card {
            padding: 28px;
        }

        /* Card Header Row */
        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--surface-border);
            margin-bottom: 24px;
        }

        .user-identity {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
        }

        .user-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-pending {
            background-color: var(--badge-pending-bg);
            color: var(--badge-pending-text);
        }

        .status-approved {
            background-color: var(--badge-approved-bg);
            color: var(--badge-approved-text);
        }

        .status-rejected {
            background-color: var(--badge-rejected-bg);
            color: var(--badge-rejected-text);
        }

        /* Information Grid Layout */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px 24px;
            margin-bottom: 24px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .detail-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .detail-value {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            word-break: break-all;
        }

        .detail-value i {
            font-size: 20px;
            color: var(--text-muted);
        }

        /* Actions Footer */
        .card-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 16px;
            padding-top: 20px;
            border-top: 1px solid var(--surface-border);
        }

        /* Dynamic System Buttons */
        .btn-approve {
            background: linear-gradient(135deg, #10B981, #059669);
            color: #FFFFFF;
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .btn-approve:hover {
            transform: scale(1.03);
            box-shadow: 0 12px 30px rgba(16, 185, 129, 0.3);
        }

        .btn-reject {
            background: #F1F5F9;
            color: #334155;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-reject:hover {
            background: #E2E8F0;
            color: var(--text-main);
        }

        /* Modal Overlays */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-box {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 36px;
            max-width: 480px;
            width: 90%;
            border: 1px solid var(--surface-border);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            transform: scale(0.95);
            transition: var(--transition);
        }

        .modal-overlay.active .modal-box {
            transform: scale(1);
        }

        .modal-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 12px;
        }

        .modal-desc {
            font-size: 15px;
            color: var(--text-muted);
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .modal-textarea {
            width: 100%;
            border: 1px solid var(--surface-border);
            border-radius: 14px;
            padding: 16px;
            font-family: var(--font-main);
            font-size: 15px;
            outline: none;
            resize: vertical;
            min-height: 120px;
            margin-bottom: 24px;
            background: #F8FAFC;
        }

        .modal-textarea:focus {
            background: #FFFFFF;
            border-color: #EF4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
        }

        .empty-state {
            padding: 80px 24px;
            text-align: center;
            color: var(--text-muted);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-navbar {
                padding: 16px 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box, .search-input {
                width: 100%;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .card-actions {
                flex-direction: column-reverse;
                gap: 12px;
            }

            .btn-approve, .btn-reject {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <div class="ambient-glow"></div>

    <!-- NEW: Sticky Navigation Bar -->
    <nav class="top-navbar">
        <div class="nav-brand">
            <i class="ph-fill ph-leaf"></i> EcoScrap
        </div>
        <div class="nav-actions">
            <!-- Ensure to update "dashboard.php" to your actual admin dashboard URL -->
            <a href="dashboard.php" class="btn-back">
                <i class="ph ph-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <main class="workspace-container">

        <!-- Top Header Area -->
        <header class="page-header">
            <div class="header-title">
                <h1>Verification Queue</h1>
                <p>Manage and process new collector registrations</p>
            </div>
            <div class="search-box">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Search by name, email, or pincode...">
            </div>
        </header>

        <!-- Dynamic System Message -->
        <?php if (function_exists('displayMessage')) { displayMessage(); } ?>

        <!-- SECTION 1: PENDING QUEUE -->
        <div class="section-header">
            <h2><i class="ph ph-clock-countdown"></i> Action Required <span class="badge-count"><?= count($pending_collectors); ?></span></h2>
        </div>
        
        <div class="cards-queue" id="pendingQueue">
            <?php if (count($pending_collectors) > 0): ?>
                <?php foreach ($pending_collectors as $row): 
                    $collector_id = htmlspecialchars($row['collector_id']);
                    $collector_name = htmlspecialchars($row['name']);
                    $collector_email = htmlspecialchars($row['email']);
                    $collector_phone = htmlspecialchars($row['phone']);
                    $collector_vehicle = htmlspecialchars($row['vehicle_no']);
                    $collector_pincode = htmlspecialchars($row['pincode']);
                    $status = $row['verification_status'];
                    $created_at = isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'Today';
                ?>
                    <div class="glass-card collector-card" data-card-name="<?= strtolower($collector_name . ' ' . $collector_email . ' ' . $collector_pincode); ?>">
                        <div class="card-top">
                            <div class="user-identity">
                                <div class="user-avatar">
                                    <i class="ph ph-user"></i>
                                </div>
                                <div class="user-name"><?= $collector_name; ?></div>
                            </div>
                            <div>
                                <span class="status-badge status-pending">
                                    <i class="ph-fill ph-circle"></i> Pending Review
                                </span>
                            </div>
                        </div>

                        <div class="details-grid">
                            <div class="detail-item">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><i class="ph ph-envelope"></i> <?= $collector_email; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><i class="ph ph-phone"></i> <?= $collector_phone; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Vehicle</span>
                                <span class="detail-value"><i class="ph ph-truck"></i> <?= $collector_vehicle; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Pincode</span>
                                <span class="detail-value"><i class="ph ph-map-pin"></i> <?= $collector_pincode; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Applied</span>
                                <span class="detail-value"><i class="ph ph-calendar-blank"></i> <?= $created_at; ?></span>
                            </div>
                        </div>

                        <div class="card-actions">
                            <button type="button" class="btn-reject" onclick="triggerReject('<?= $collector_id; ?>', '<?= addslashes($collector_name); ?>')">
                                <i class="ph ph-x"></i> Decline
                            </button>
                            <button type="button" class="btn-approve" onclick="triggerApprove('<?= $collector_id; ?>', '<?= addslashes($collector_name); ?>')">
                                <i class="ph ph-check"></i> Approve Collector
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-card empty-state">
                    <i class="ph ph-check-circle" style="font-size: 48px; margin-bottom: 16px; color: var(--primary); display: block;"></i>
                    <h3 style="color: var(--text-main); margin-bottom: 8px;">You're all caught up!</h3>
                    <p>No collector registrations are currently pending review.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- SECTION 2: PROCESSED QUEUE -->
        <div class="section-header">
            <h2><i class="ph ph-folder-open"></i> Processed Applications <span class="badge-count" style="background: var(--text-muted);"><?= count($processed_collectors); ?></span></h2>
        </div>
        
        <div class="cards-queue" id="processedQueue">
            <?php if (count($processed_collectors) > 0): ?>
                <?php foreach ($processed_collectors as $row): 
                    $collector_id = htmlspecialchars($row['collector_id']);
                    $collector_name = htmlspecialchars($row['name']);
                    $collector_email = htmlspecialchars($row['email']);
                    $collector_phone = htmlspecialchars($row['phone']);
                    $collector_vehicle = htmlspecialchars($row['vehicle_no']);
                    $collector_pincode = htmlspecialchars($row['pincode']);
                    $status = $row['verification_status'];
                    $created_at = isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'Today';
                ?>
                    <div class="glass-card collector-card" data-card-name="<?= strtolower($collector_name . ' ' . $collector_email . ' ' . $collector_pincode); ?>">
                        <div class="card-top" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                            <div class="user-identity">
                                <div class="user-avatar" style="background: #F1F5F9; color: var(--text-muted);">
                                    <i class="ph ph-user"></i>
                                </div>
                                <div class="user-name"><?= $collector_name; ?></div>
                            </div>
                            <div>
                                <?php if ($status === 'Approved'): ?>
                                    <span class="status-badge status-approved">
                                        <i class="ph-fill ph-check-circle"></i> Approved
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-rejected">
                                        <i class="ph-fill ph-x-circle"></i> Rejected
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Optional: You can hide the details grid for processed ones to save space, 
                             or leave it as is. I've left it visible but padded down for hierarchy. -->
                        <div class="details-grid" style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--surface-border);">
                            <div class="detail-item">
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><i class="ph ph-envelope"></i> <?= $collector_email; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Phone</span>
                                <span class="detail-value"><i class="ph ph-phone"></i> <?= $collector_phone; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Vehicle</span>
                                <span class="detail-value"><i class="ph ph-truck"></i> <?= $collector_vehicle; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Pincode</span>
                                <span class="detail-value"><i class="ph ph-map-pin"></i> <?= $collector_pincode; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Applied</span>
                                <span class="detail-value"><i class="ph ph-calendar-blank"></i> <?= $created_at; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="glass-card empty-state">
                    <i class="ph ph-tray" style="font-size: 48px; margin-bottom: 16px; color: var(--text-muted); display: block;"></i>
                    <p>No historical applications found.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Approve Modal Confirmation -->
    <div class="modal-overlay" id="approveModal">
        <div class="modal-box">
            <h3 class="modal-title">Approve Collector?</h3>
            <p class="modal-desc" id="approveModalText">
                This collector will gain full access to accept pickup requests in their designated area.
            </p>
            <div class="modal-footer">
                <button class="btn-reject" onclick="closeModals()">Cancel</button>
                <a href="#" id="approveConfirmBtn" class="btn-approve">Confirm Approval</a>
            </div>
        </div>
    </div>

    <!-- Reject Modal Confirmation -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal-box">
            <h3 class="modal-title">Decline Application?</h3>
            <p class="modal-desc">Provide a reason for declining this collector application (Optional):</p>
            
            <textarea id="rejectReason" class="modal-textarea" placeholder="E.g., Invalid vehicle registration, out of service area..."></textarea>
            
            <div class="modal-footer">
                <button class="btn-reject" onclick="closeModals()">Cancel</button>
                <button class="btn-reject" style="background:#DC2626; color:#FFF;" onclick="executeReject()">Confirm Decline</button>
            </div>
        </div>
    </div>

    <!-- UI Interaction Scripts -->
    <script>
        let currentCollectorId = null;

        // Search Filter (Updated to search multiple attributes)
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                const query = this.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.collector-card');
                
                cards.forEach(card => {
                    const searchableText = card.getAttribute('data-card-name');
                    if (searchableText.includes(query)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }

        // Approval Modal Trigger
        function triggerApprove(id, name) {
            currentCollectorId = id;
            document.getElementById('approveModalText').innerText = `Are you sure you want to approve ${name}? They will gain immediate access to the collector portal.`;
            document.getElementById('approveConfirmBtn').href = `approve_collector_process.php?id=${id}&action=approve`;
            document.getElementById('approveModal').classList.add('active');
        }

        // Reject Modal Trigger
        function triggerReject(id, name) {
            currentCollectorId = id;
            document.getElementById('rejectReason').value = '';
            document.getElementById('rejectModal').classList.add('active');
        }

        // Close Modals
        function closeModals() {
            document.getElementById('approveModal').classList.remove('active');
            document.getElementById('rejectModal').classList.remove('active');
            currentCollectorId = null;
        }

        // Execute Reject Redirect
        function executeReject() {
            if (!currentCollectorId) return;
            const reason = encodeURIComponent(document.getElementById('rejectReason').value.trim());
            window.location.href = `approve_collector_process.php?id=${currentCollectorId}&action=reject&reason=${reason}`;
        }

        // Close on Overlay Click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                closeModals();
            }
        };
    </script>

</body>

</html>