<?php
session_start();

// Database Connection Strategy
// Replace with your db include if available (e.g., require_once 'db.php';)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ecoscrap_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Authentication Check: Ensure collector is logged in
if (!isset($_SESSION['collector_id'])) {
    // Demo fallback for testing if session isn't set yet (Change/remove in production)
    $_SESSION['collector_id'] = 8; // Defaulting to Ajith Joseph from your SQL table
}

$collector_id = $_SESSION['collector_id'];

// Handle Quick Availability Status Update (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = $_POST['availability_status'] ?? 'Available';
    $allowed_statuses = ['Available', 'Busy', 'Offline'];

    if (in_array($new_status, $allowed_statuses)) {
        $update_stmt = $conn->prepare("UPDATE scrapcollector SET availability_status = ? WHERE collector_id = ?");
        $update_stmt->bind_param("si", $new_status, $collector_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    header("Location: collector_profile.php?updated=1");
    exit();
}

// Fetch Collector Data from `scrapcollector` table
$stmt = $conn->prepare("SELECT collector_id, name, email, phone, profile_image, vehicle_no, pincode, availability_status, verification_status, completed_pickups, created_at FROM scrapcollector WHERE collector_id = ?");
$stmt->bind_param("i", $collector_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Collector record not found.");
}

$collector = $result->fetch_assoc();
$stmt->close();

// Derived Eco Metrics & Formatting
$completed_count = (int)$collector['completed_pickups'];
// Estimated CO2 diverted: ~15kg per completed pickup formatted into Tons
$co2_impact_tons = number_format(($completed_count * 15) / 1000, 1);

// Status Badge Helpers
$status = $collector['availability_status'];
$status_color_map = [
    'Available' => 'var(--primary)',
    'Busy'      => '#F59E0B',
    'Offline'   => '#64748B'
];
$current_status_color = $status_color_map[$status] ?? 'var(--primary)';

// Profile Picture Fallback
$avatar_src = !empty($collector['profile_image']) ? 'uploads/profile/' . htmlspecialchars($collector['profile_image']) : null;
$initials = strtoupper(substr($collector['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($collector['name']); ?> - Collector Profile | EcoScrap</title>
  
  <!-- Remix Icons -->
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
  
  <style>
    :root {
      /* Brand Colors */
      --primary: #10B981;    /* Emerald Green */
      --secondary: #047857;  /* Forest Green */
      --accent: #0EA5E9;     /* Sky Blue */
      
      /* Backgrounds & Surface */
      --bg-color: #F8FAFC;
      --surface: rgba(255, 255, 255, 0.75);
      --surface-border: rgba(15, 23, 42, 0.08);
      
      /* Text */
      --text-main: #0F172A;
      --text-muted: #64748B;
      
      /* Utilities */
      --font-main: 'Inter', system-ui, -apple-system, sans-serif;
      --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      --mouse-x: 50%;
      --mouse-y: 50%;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: var(--font-main);
      background-color: var(--bg-color);
      color: var(--text-main);
      line-height: 1.6;
      padding-top: 90px;
      padding-bottom: 60px;
      -webkit-font-smoothing: antialiased;
    }

    /* Layout Structure */
    .container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 24px;
    }

    /* Glass Utilities */
    .glass-panel {
      background: var(--surface);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--surface-border);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.03);
    }

    .glass-card {
      background: var(--surface);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--surface-border);
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
      transition: var(--transition);
    }

    /* Navbar Header */
    .navbar {
      position: fixed;
      top: 0; left: 0; width: 100%;
      z-index: 100;
      padding: 16px 0;
      background: rgba(248, 250, 252, 0.85);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--surface-border);
    }
    .nav-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 24px;
    }
    .logo {
      font-weight: 800;
      font-size: 20px;
      color: var(--text-main);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 10px;
      letter-spacing: -0.03em;
    }
    .logo-mark {
      width: 14px; height: 14px;
      background: var(--primary);
      border-radius: 4px;
    }
    .nav-actions { display: flex; align-items: center; gap: 16px; }

    /* Buttons */
    .btn-primary, .btn-secondary, .btn-ghost, .btn-danger {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 18px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 14px;
      text-decoration: none;
      transition: var(--transition);
      cursor: pointer;
      border: 1px solid transparent;
    }
    .btn-primary {
      background: var(--text-main);
      color: white;
    }
    .btn-primary:hover {
      background: var(--primary);
      box-shadow: 0 6px 18px rgba(16, 185, 129, 0.25);
    }
    .btn-secondary {
      background: white;
      color: var(--text-main);
      border: 1px solid var(--surface-border);
    }
    .btn-secondary:hover {
      border-color: var(--text-main);
      background: #f1f5f9;
    }
    .btn-danger {
      background: #fef2f2;
      color: #ef4444;
      border: 1px solid #fecaca;
    }
    .btn-danger:hover {
      background: #ef4444;
      color: white;
    }

    /* Page Titles */
    .page-title-row {
      margin-bottom: 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .page-title {
      font-size: 28px;
      font-weight: 800;
      letter-spacing: -0.03em;
    }

    /* Main Grid Layout */
    .profile-grid {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 24px;
      margin-bottom: 36px;
    }

    /* Avatar Side Card */
    .avatar-card {
      padding: 32px 24px;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .avatar-wrapper {
      position: relative;
      width: 110px;
      height: 110px;
      margin-bottom: 16px;
    }
    .avatar-img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid white;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .avatar-fallback {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      font-size: 38px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 3px solid white;
      box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
    }
    .status-indicator-dot {
      position: absolute;
      bottom: 4px;
      right: 4px;
      width: 18px;
      height: 18px;
      border-radius: 50%;
      border: 3px solid white;
      background-color: <?= $current_status_color ?>;
    }

    .collector-name {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .verification-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 20px;
    }
    .badge-approved {
      background: #d1fae5;
      color: #047857;
    }
    .badge-pending {
      background: #fef3c7;
      color: #b45309;
    }

    /* Status Form Control */
    .status-select-form {
      width: 100%;
      margin-top: 12px;
      padding-top: 16px;
      border-top: 1px solid var(--surface-border);
    }
    .status-select-form label {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      display: block;
      margin-bottom: 8px;
    }
    .custom-select {
      width: 100%;
      padding: 10px 12px;
      border-radius: 8px;
      border: 1px solid var(--surface-border);
      background: white;
      color: var(--text-main);
      font-weight: 500;
      font-size: 14px;
      outline: none;
      cursor: pointer;
    }

    /* Info Details Card */
    .info-card {
      padding: 32px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 24px;
    }
    .info-item label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 6px;
    }
    .info-item span {
      font-size: 16px;
      font-weight: 600;
      color: var(--text-main);
    }

    /* Performance Overview Section */
    .section-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 16px;
      letter-spacing: -0.02em;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 18px;
      margin-bottom: 36px;
    }
    .stat-item {
      padding: 24px;
      text-align: center;
    }
    .stat-value {
      font-size: 32px;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--text-main);
      margin-bottom: 4px;
    }
    .stat-label {
      font-size: 12px;
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    /* Settings Action Toolbar */
    .settings-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
    }

    /* Toast Notification */
    .toast-alert {
      padding: 12px 20px;
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #a7f3d0;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Responsive */
    @media (max-width: 900px) {
      .profile-grid { grid-template-columns: 1fr; }
      .stats-grid, .settings-grid { grid-template-columns: repeat(2, 1fr); }
      .info-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 540px) {
      .stats-grid, .settings-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  <!-- Top Navigation -->
  <nav class="navbar">
    <div class="nav-container">
      <a href="dashboard.php" class="logo">
        <span class="logo-mark"></span> EcoScrap
      </a>
      <div class="nav-actions">
        <a href="notifications.php" class="btn-secondary" style="padding: 8px 14px;">
          <i class="ri-notification-3-line"></i> Notifications
        </a>
        <a href="dashboard.php" class="btn-ghost" style="padding: 8px 12px;">Dashboard</a>
      </div>
    </div>
  </nav>

  <!-- Content Workspace -->
  <main class="container">

    <?php if (isset($_GET['updated'])): ?>
      <div class="toast-alert">
        <i class="ri-checkbox-circle-fill"></i> Availability status successfully updated.
      </div>
    <?php endif; ?>

    <div class="page-title-row">
      <h1 class="page-title">My Collector Profile</h1>
    </div>

    <!-- Main Collector Details Section -->
    <section class="profile-grid">
      
      <!-- Avatar Card -->
      <div class="glass-card avatar-card">
        <div class="avatar-wrapper">
          <?php if ($avatar_src): ?>
            <img src="<?= $avatar_src ?>" alt="<?= htmlspecialchars($collector['name']) ?>" class="avatar-img">
          <?php else: ?>
            <div class="avatar-fallback"><?= $initials ?></div>
          <?php endif; ?>
          <span class="status-indicator-dot" title="Current status: <?= htmlspecialchars($status) ?>"></span>
        </div>

        <h2 class="collector-name"><?= htmlspecialchars($collector['name']); ?></h2>

        <?php if ($collector['verification_status'] === 'Approved'): ?>
          <span class="verification-badge badge-approved">
            <i class="ri-shield-check-fill"></i> Verified Collector
          </span>
        <?php else: ?>
          <span class="verification-badge badge-pending">
            <i class="ri-time-line"></i> <?= htmlspecialchars($collector['verification_status']); ?> Verification
          </span>
        <?php endif; ?>

        <!-- Quick Status Toggle Form -->
        <form method="POST" action="collector_profile.php" class="status-select-form">
          <input type="hidden" name="action" value="update_status">
          <label for="availability_status">Update Availability</label>
          <select name="availability_status" id="availability_status" class="custom-select" onchange="this.form.submit()">
            <option value="Available" <?= $status === 'Available' ? 'selected' : '' ?>>🟢 Available</option>
            <option value="Busy" <?= $status === 'Busy' ? 'selected' : '' ?>>🟠 Busy / On Pickup</option>
            <option value="Offline" <?= $status === 'Offline' ? 'selected' : '' ?>>⚪ Offline</option>
          </select>
        </form>
      </div>

      <!-- Information Card -->
      <div class="glass-card info-card">
        <div class="info-grid">
          <div class="info-item">
            <label>Full Name</label>
            <span><?= htmlspecialchars($collector['name']); ?></span>
          </div>

          <div class="info-item">
            <label>Email Address</label>
            <span><?= htmlspecialchars($collector['email']); ?></span>
          </div>

          <div class="info-item">
            <label>Phone Number</label>
            <span><?= htmlspecialchars($collector['phone'] ?? 'N/A'); ?></span>
          </div>

          <div class="info-item">
            <label>Vehicle Number</label>
            <span><?= htmlspecialchars($collector['vehicle_no'] ?? 'N/A'); ?></span>
          </div>

          <div class="info-item">
            <label>Service Pincode</label>
            <span><?= htmlspecialchars($collector['pincode']); ?></span>
          </div>

          <div class="info-item">
            <label>Account Created</label>
            <span><?= date("d M Y", strtotime($collector['created_at'])); ?></span>
          </div>
        </div>
      </div>

    </section>

    <!-- Performance Stats Overview -->
    <h3 class="section-title">Performance Overview</h3>
    <section class="stats-grid">
      <div class="glass-card stat-item">
        <div class="stat-value" style="color: var(--primary);"><?= $completed_count ?></div>
        <div class="stat-label">Completed Pickups</div>
      </div>

      <div class="glass-card stat-item">
        <div class="stat-value" style="color: var(--accent);"><?= $co2_impact_tons ?>T</div>
        <div class="stat-label">CO₂ Saved (Tons)</div>
      </div>

      <div class="glass-card stat-item">
        <div class="stat-value" style="color: <?= $current_status_color ?>;"><?= htmlspecialchars($status) ?></div>
        <div class="stat-label">Current Status</div>
      </div>

      <div class="glass-card stat-item">
        <div class="stat-value" style="color: #047857;">4.9 ★</div>
        <div class="stat-label">Rating & Trust</div>
      </div>
    </section>

    <!-- Account Actions Toolbar -->
    <h3 class="section-title">Account Settings</h3>
    <section class="settings-grid">
      <a href="edit_profile.php" class="btn-secondary">
        <i class="ri-edit-line"></i> Edit Profile
      </a>

      <a href="change_password.php" class="btn-secondary">
        <i class="ri-lock-password-line"></i> Change Password
      </a>

      <a href="service_area.php" class="btn-secondary">
        <i class="ri-map-pin-line"></i> Manage Pincodes
      </a>

      <a href="logout.php" class="btn-danger">
        <i class="ri-logout-box-r-line"></i> Logout
      </a>
    </section>

  </main>

</body>
</html>