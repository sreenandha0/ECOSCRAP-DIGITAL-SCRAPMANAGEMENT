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
    <link rel="stylesheet" href="../assets/css/approvecollectors.css">
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
       
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