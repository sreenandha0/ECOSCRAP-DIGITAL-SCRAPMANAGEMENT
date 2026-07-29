<?php
// 1. Session Setup with Mobile/LocalTunnel Cookie Compatibility
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once "../includes/db.php";
require_once "../includes/functions.php";

// 2. Authorization Check
if (!isset($_SESSION['collector_id']) || ($_SESSION['role'] ?? '') !== "Collector") {
    redirect("../login.php");
    exit();
}

$collector_id = (int)$_SESSION['collector_id'];
$id           = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message      = "";

// 3. Generate CSRF Token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. Fetch Collector Name
$collector_name = $_SESSION['collector_name'] ?? 'Collector';
$stmt_col = $conn->prepare("SELECT name FROM scrapcollector WHERE collector_id = ?");
if ($stmt_col) {
    $stmt_col->bind_param("i", $collector_id);
    $stmt_col->execute();
    $res_col = $stmt_col->get_result();
    if ($col_data = $res_col->fetch_assoc()) {
        $collector_name = $col_data['name'];
        $_SESSION['collector_name'] = $collector_name;
    }
    $stmt_col->close();
}

/* ----------------------------------
   CONFIRM & VERIFY PICKUP SUBMISSION
---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {

    // CSRF Check
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $posted_token)) {
        if (function_exists('verifyCsrfToken')) {
            try {
                verifyCsrfToken();
            } catch (Exception $e) {
                $message = "Session token expired. Please refresh the page and try again.";
            }
        } else {
            $message = "Invalid security token. Please refresh and try again.";
        }
    }

    if (empty($message)) {
        $activity_id = (int)$_POST['activity_id'];
        $weight      = (float)$_POST['scrap_weight'];
        $amount      = (float)$_POST['amount'];
        $remarks     = trim($_POST['remarks'] ?? '');

        try {
            if ($weight <= 0 || $amount < 0) {
                throw new Exception("Please enter a valid scrap weight and amount.");
            }

            $conn->begin_transaction();

            // Updated query matching exact database columns: qr_status & completed_at
            $sql = "UPDATE activity
                    SET scrap_weight = ?,
                        amount = ?,
                        remarks = ?,
                        status = 'Completed',
                        qr_status = 'Used',
                        completed_at = NOW()
                    WHERE activity_id = ? AND collector_id = ? AND status != 'Completed'";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ddsii", $weight, $amount, $remarks, $activity_id, $collector_id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("Unable to confirm pickup. It may already be completed or assigned to another collector.");
            }
            $stmt->close();

            // Increment completed pickups and set status to 'Available'
            $stmt_avail = $conn->prepare("UPDATE scrapcollector SET availability_status = 'Available', completed_pickups = completed_pickups + 1 WHERE collector_id = ?");
            $stmt_avail->bind_param("i", $collector_id);
            $stmt_avail->execute();
            $stmt_avail->close();

            $conn->commit();

            $_SESSION['flash_success'] = "Pickup completed successfully!";
            header("Location: dashboard.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Verification | EcoScrap</title>

    <!-- Google Fonts & Remix Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- HTML5 QR Code Library -->
    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>
        :root {
            --bg-color: #F8FAFC;
            --surface-card: #FFFFFF;
            --border-color: #E2E8F0;
            --primary: #10B981;
            --primary-hover: #059669;
            --secondary: #0EA5E9;
            --success: #22C55E;
            --error: #EF4444;
            --warning: #F59E0B;
            --text-main: #0F172A;
            --text-muted: #64748B;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(14, 165, 233, 0.04) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.05) 0%, transparent 40%);
        }

        .topbar {
            height: 70px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            text-decoration: none;
        }

        .brand-badge {
            width: 36px; height: 36px;
            background: rgba(16, 185, 129, 0.15);
            color: var(--primary);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }

        .user-profile { display: flex; align-items: center; gap: 16px; }

        .icon-btn {
            width: 40px; height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }

        .icon-btn:hover { background: #f8fafc; }

        .avatar-pill {
            display: flex; align-items: center; gap: 10px;
            background: #fff; padding: 6px 14px 6px 8px;
            border-radius: 30px; border: 1px solid var(--border-color);
        }

        .avatar {
            width: 28px; height: 28px;
            background: var(--secondary); color: #fff;
            border-radius: 50%; display: flex;
            align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
        }

        .content-area {
            padding: 40px 20px;
            max-width: 680px;
            margin: 0 auto;
            width: 100%;
            flex: 1;
        }

        .page-header { text-align: center; margin-bottom: 28px; }
        .page-header h1 { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; }
        .page-header p { color: var(--text-muted); font-size: 14px; margin-top: 4px; }

        .scanner-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            position: relative;
        }

        .camera-viewport {
            width: 100%; max-width: 400px; height: 320px;
            margin: 0 auto; border-radius: 16px;
            overflow: hidden; position: relative;
            background: #090d16;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid var(--border-color);
        }

        #reader { width: 100% !important; height: 100% !important; border: none !important; }
        #reader video { object-fit: cover !important; width: 100% !important; height: 100% !important; }

        .scan-overlay {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 200px; height: 200px;
            pointer-events: none; z-index: 10;
        }

        .corner {
            position: absolute; width: 24px; height: 24px;
            border-color: var(--primary); border-style: solid;
            filter: drop-shadow(0 0 6px var(--primary));
        }

        .top-left { top: 0; left: 0; border-width: 3px 0 0 3px; border-top-left-radius: 8px; }
        .top-right { top: 0; right: 0; border-width: 3px 3px 0 0; border-top-right-radius: 8px; }
        .bottom-left { bottom: 0; left: 0; border-width: 0 0 3px 3px; border-bottom-left-radius: 8px; }
        .bottom-right { bottom: 0; right: 0; border-width: 0 3px 3px 0; border-bottom-right-radius: 8px; }

        .scan-line {
            width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            position: absolute;
            animation: scanAnim 2s infinite ease-in-out;
        }

        @keyframes scanAnim {
            0% { top: 0%; opacity: 0.2; }
            50% { top: 100%; opacity: 1; }
            100% { top: 0%; opacity: 0.2; }
        }

        .helper-text { text-align: center; font-size: 13px; color: var(--text-muted); margin-top: 14px; font-weight: 500; }

        .status-card {
            margin-top: 20px; padding: 16px; border-radius: 14px;
            background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(10px);
            border: 1px solid var(--border-color); display: flex;
            align-items: center; justify-content: center; gap: 10px;
            font-weight: 600; font-size: 14px;
        }

        .status-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--warning); box-shadow: 0 0 8px var(--warning); }
        .status-card.success { background: rgba(34, 197, 94, 0.08); border-color: rgba(34, 197, 94, 0.3); color: #15803D; }
        .status-card.success .status-dot { background: var(--success); box-shadow: 0 0 8px var(--success); }
        .status-card.error { background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.3); color: #B91C1C; }
        .status-card.error .status-dot { background: var(--error); box-shadow: 0 0 8px var(--error); }

        .action-grid { display: flex; gap: 12px; margin-top: 20px; }

        .btn {
            flex: 1; padding: 12px 18px; border-radius: 10px; font-weight: 600; font-size: 14px;
            border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; transition: all 0.2s ease; text-decoration: none;
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }

        .btn-secondary { background: #fff; border: 1px solid var(--border-color); color: var(--text-main); }
        .btn-secondary:hover { background: #F1F5F9; }

        .customer-card {
            background: #fff; border: 1px solid var(--border-color); border-radius: 20px;
            padding: 28px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            animation: fadeIn 0.3s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin: 20px 0; }
        .info-item { background: #F8FAFC; padding: 14px; border-radius: 12px; border: 1px solid var(--border-color); }
        .info-label { font-size: 12px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.4px; margin-bottom: 4px; }
        .info-value { font-size: 15px; font-weight: 600; color: var(--text-main); }

        .form-control {
            width: 100%; padding: 10px 14px; font-size: 14px; border: 1px solid var(--border-color);
            border-radius: 8px; margin-top: 4px;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }

        @media (max-width: 600px) {
            .topbar { padding: 0 16px; }
            .content-area { padding: 20px 16px; }
            .info-grid { grid-template-columns: 1fr; }
            .info-item[style*="span 2"] { grid-column: span 1 !important; }
        }
    </style>
</head>

<body>

    <!-- Header Topbar -->
    <header class="topbar">
        <a href="dashboard.php" class="brand-header">
            <div class="brand-badge"><i class="ri-leaf-fill"></i></div>
            <span>EcoScrap</span>
        </a>

        <div class="user-profile">
            <a href="dashboard.php" class="icon-btn" title="Back to Dashboard">
                <i class="ri-dashboard-line"></i>
            </a>
            <div class="avatar-pill">
                <div class="avatar"><?= strtoupper(substr($collector_name, 0, 1)); ?></div>
                <span style="font-size: 14px; font-weight: 600;"><?= htmlspecialchars($collector_name); ?></span>
            </div>
            <a href="../logout.php" class="icon-btn" title="Logout">
                <i class="ri-logout-box-r-line"></i>
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="content-area">

        <?php if (!empty($message)): ?>
            <div class="status-card error" style="margin-bottom: 20px;">
                <div class="status-dot"></div>
                <span><?= htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($id === 0) { ?>

            <!-- SCANNER VIEW -->
            <div class="page-header">
                <h1>QR Pickup Verification</h1>
                <p>Scan the customer's QR code to verify and access the pickup details.</p>
            </div>

            <div class="scanner-card">
                <div class="camera-viewport">
                    <div id="reader"></div>
                    <div class="scan-overlay">
                        <div class="corner top-left"></div>
                        <div class="corner top-right"></div>
                        <div class="corner bottom-left"></div>
                        <div class="corner bottom-right"></div>
                        <div class="scan-line"></div>
                    </div>
                </div>

                <p class="helper-text"><i class="ri-focus-3-line"></i> Align the QR Code inside the glowing frame</p>

                <div class="status-card" id="scanner-status">
                    <div class="status-dot"></div>
                    <span>Waiting for QR...</span>
                </div>

                <div class="action-grid">
                    <button class="btn btn-primary" onclick="restartScanner()"><i class="ri-camera-switch-line"></i> Start Camera</button>
                    <input type="file" id="qr-input-file" accept="image/*" style="display:none;" onchange="scanFromFile(this)">
                    <button class="btn btn-secondary" onclick="document.getElementById('qr-input-file').click()"><i class="ri-upload-2-line"></i> Upload Image</button>
                </div>
            </div>

            <script>
                let html5QrCode = null;

                async function stopScannerIfScanning() {
                    if (html5QrCode && html5QrCode.isScanning) {
                        try {
                            await html5QrCode.stop();
                        } catch (err) {
                            console.error("Error stopping scanner:", err);
                        }
                    }
                }

                async function onScanSuccess(decodedText) {
                    let activityId = null;

                    // 1. JSON check
                    try {
                        const json = JSON.parse(decodedText);
                        if (json && (json.activity_id || json.id)) {
                            activityId = json.activity_id || json.id;
                        }
                    } catch (e) {}

                    // 2. URL search check
                    if (!activityId) {
                        try {
                            const url = new URL(decodedText);
                            activityId = url.searchParams.get("id");
                        } catch (e) {}
                    }

                    // 3. Fallback direct numeric ID
                    if (!activityId && !isNaN(decodedText.trim())) {
                        activityId = decodedText.trim();
                    }

                    if (activityId) {
                        await stopScannerIfScanning();
                        window.location.href = "verify_qr.php?id=" + encodeURIComponent(activityId);
                    } else {
                        alert("Invalid or unrecognized QR code format.");
                    }
                }

                async function startScanner() {
                    await stopScannerIfScanning();
                    if (!html5QrCode) {
                        html5QrCode = new Html5Qrcode("reader");
                    }
                    html5QrCode.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 220, height: 220 } },
                        onScanSuccess
                    ).catch(err => {
                        console.error("Camera access failed", err);
                        const statusSpan = document.querySelector('#scanner-status span');
                        if (statusSpan) statusSpan.textContent = "Camera access denied or unavailable";
                    });
                }

                function restartScanner() {
                    startScanner();
                }

                async function scanFromFile(input) {
                    if (input.files.length === 0) return;
                    const file = input.files[0];
                    await stopScannerIfScanning();

                    if (!html5QrCode) {
                        html5QrCode = new Html5Qrcode("reader");
                    }

                    html5QrCode.scanFile(file, true)
                        .then(decodedText => onScanSuccess(decodedText))
                        .catch(err => alert("Could not parse QR from uploaded image."));
                }

                document.addEventListener("DOMContentLoaded", startScanner);
            </script>

        <?php } else {

            // FETCH ACTIVITY RECORD
            $stmt = $conn->prepare("
                SELECT a.*, u.name AS customer_name 
                FROM activity a 
                LEFT JOIN user u ON a.user_id = u.user_id 
                WHERE a.activity_id = ? AND a.collector_id = ?
            ");
            $stmt->bind_param("ii", $id, $collector_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) { ?>

                <!-- INVALID QR VIEW -->
                <div class="customer-card" style="text-align: center;">
                    <div class="status-card error" style="margin-bottom: 20px;">
                        <div class="status-dot"></div>
                        <span>Invalid QR Code</span>
                    </div>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">This QR code does not exist or is not assigned to your route.</p>
                    <a href="verify_qr.php" class="btn btn-secondary" style="width: 100%;"><i class="ri-refresh-line"></i> Scan Again</a>
                </div>

            <?php } else {
                $row = $result->fetch_assoc();
                $qr_status_val = $row['qr_status'] ?? '';

                if ($qr_status_val === "Used" || $row['status'] === "Completed") { ?>

                    <!-- ALREADY USED QR VIEW -->
                    <div class="customer-card">
                        <div class="status-card error" style="margin-bottom: 20px;">
                            <div class="status-dot"></div>
                            <span>QR Already Used</span>
                        </div>

                        <p style="text-align: center; color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">This pickup has already been verified and marked as complete.</p>

                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Activity ID</div>
                                <div class="info-value">#<?= htmlspecialchars($row['activity_id']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value"><?= htmlspecialchars($row['status']); ?></div>
                            </div>
                        </div>

                        <a href="verify_qr.php" class="btn btn-secondary" style="width: 100%; margin-top: 10px;"><i class="ri-qr-scan-line"></i> Scan Another QR</a>
                    </div>

                <?php } else { ?>

                    <!-- VERIFIED & READY TO COMPLETE VIEW -->
                    <div class="customer-card">
                        <div class="status-card success">
                            <div class="status-dot"></div>
                            <span>QR Verified Successfully</span>
                        </div>

                        <form method="POST" action="verify_qr.php?id=<?= htmlspecialchars($row['activity_id']); ?>" style="margin-top: 20px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="activity_id" value="<?= htmlspecialchars($row['activity_id']); ?>">

                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Customer</div>
                                    <div class="info-value"><?= htmlspecialchars($row['customer_name'] ?? 'Customer'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Scrap Type</div>
                                    <div class="info-value"><?= htmlspecialchars($row['scrap_type'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Weight (kg)</div>
                                    <input type="number" step="0.01" min="0.01" name="scrap_weight" class="form-control" value="<?= htmlspecialchars($row['scrap_weight'] ?? '0.00'); ?>" required>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Amount (₹)</div>
                                    <input type="number" step="0.01" min="0.00" name="amount" class="form-control" value="<?= htmlspecialchars($row['amount'] ?? '0.00'); ?>" required>
                                </div>
                                <div class="info-item" style="grid-column: span 2;">
                                    <div class="info-label">Pickup Address</div>
                                    <div class="info-value" style="font-weight: 500; font-size: 14px; margin-top: 2px;">
                                        <?= htmlspecialchars($row['pickup_address'] ?? 'Address Not Provided'); ?>
                                    </div>
                                </div>
                                <div class="info-item" style="grid-column: span 2;">
                                    <div class="info-label">Collector Remarks</div>
                                    <input type="text" name="remarks" class="form-control" value="<?= htmlspecialchars($row['remarks'] ?? ''); ?>" placeholder="Optional notes (e.g. Paid cash, material verified)">
                                </div>
                            </div>

                            <div class="action-grid">
                                <button type="submit" name="confirm" class="btn btn-primary" style="padding: 14px;"><i class="ri-checkbox-circle-line"></i> Complete Pickup</button>
                            </div>
                        </form>

                        <a href="verify_qr.php" class="btn btn-secondary" style="width: 100%; margin-top: 12px;"><i class="ri-arrow-left-line"></i> Cancel / Scan Another</a>
                    </div>

            <?php 
                }
            }
            $stmt->close();
        } ?>

    </main>

</body>

</html>