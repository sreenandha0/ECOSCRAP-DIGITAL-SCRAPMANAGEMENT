<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['collector_id']) || ($_SESSION['role'] ?? '') !== 'Collector') {
    redirect('../login.php');
}

$collectorId = (int) $_SESSION['collector_id'];

// Joined with 'user' table
$stmt = $conn->prepare("SELECT a.activity_id, a.scrap_type, a.scrap_weight, a.amount, a.pickup_address, a.completed_at, u.name AS customer_name, u.phone AS customer_phone 
                        FROM activity a 
                        INNER JOIN user u ON u.user_id = a.user_id 
                        WHERE a.collector_id = ? AND a.status = 'Completed' 
                        ORDER BY a.completed_at DESC");

$stmt->bind_param('i', $collectorId);
$stmt->execute();
$pickups = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Pickups | EcoScrap</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { min-height:100vh; padding:40px 20px; background:var(--bg-color); font-family:var(--font-main); }
        .workspace { max-width:1100px; margin:0 auto; }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:28px; }
        .card { border:1px solid var(--surface-border); border-radius:18px; box-shadow:0 10px 30px rgba(15,23,42,.04); overflow:hidden; }
        .badge-complete { background:rgba(16,185,129,.1); color:#047857; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; }
        .empty { padding:56px 20px; text-align:center; color:var(--text-muted); }
    </style>
</head>
<body>
<main class="workspace">
    <header class="topbar">
        <div>
            <h1 class="h3 mb-1"><i class="ri-checkbox-circle-line text-success"></i> Completed Pickups</h1>
            <p class="text-muted mb-0">Your verified pickup history.</p>
        </div>
        <a href="dashboard.php" class="btn btn-light border rounded-3"><i class="ri-arrow-left-line"></i> Dashboard</a>
    </header>

    <section class="card bg-white">
    <?php if ($pickups->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Pickup</th>
                        <th>Customer</th>
                        <th>Weight</th>
                        <th>Amount</th>
                        <th>Completed</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($pickup = $pickups->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>#<?= (int) $pickup['activity_id'] ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($pickup['scrap_type']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($pickup['customer_name'] ?? 'Customer') ?><br>
                            <small class="text-muted"><?= htmlspecialchars($pickup['customer_phone'] ?? 'N/A') ?></small>
                        </td>
                        <td><?= htmlspecialchars($pickup['scrap_weight'] ?? '0.00') ?> kg</td>
                        <td>₹<?= number_format((float) $pickup['amount'], 2) ?></td>
                        <td><?= !empty($pickup['completed_at']) ? htmlspecialchars(date('d M Y, h:i A', strtotime($pickup['completed_at']))) : '—' ?></td>
                        <td><span class="badge badge-complete">Completed</span></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <i class="ri-inbox-line fs-1 d-block mb-2"></i>
            <h2 class="h5">No completed pickups yet</h2>
            <p class="mb-0">Completed pickups will appear here after QR verification.</p>
        </div>
    <?php endif; ?>
    </section>
</main>
</body>
</html>
<?php $stmt->close(); ?>