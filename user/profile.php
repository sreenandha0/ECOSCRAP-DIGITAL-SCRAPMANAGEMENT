<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch User Profile Information
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}

$user = $result->fetch_assoc();
$stmt->close();

// Dynamic Database Fetch: Count Completed Pickups & Total Recycled Weight
$activity_stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_pickups, 
        IFNULL(SUM(scrap_weight), 0) AS total_recycled 
    FROM activity 
    WHERE user_id = ? AND status = 'Completed'
");
$activity_stmt->bind_param("i", $user_id);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result()->fetch_assoc();
$activity_stmt->close();

$completed_pickups = $activity_result['total_pickups'] ?? 0;
$total_recycled_kg = round($activity_result['total_recycled'], 2);

// Profile Image Fallback
$image = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8')
    : "../assets/images/default-user.png";

// Profile Completion Score Calculation
$track_fields = ['name', 'email', 'phone', 'place', 'district', 'state', 'pincode', 'address', 'profile_image'];
$filled_count = 0;
foreach ($track_fields as $field) {
    if (!empty($user[$field])) {
        $filled_count++;
    }
}
$completion_score = round(($filled_count / count($track_fields)) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard - EcoScrap</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
  --primary: #10B981;
  --primary-glow: rgba(16, 185, 129, 0.25);
  --secondary: #047857;
  --accent: #0EA5E9;
  --accent-glow: rgba(14, 165, 233, 0.2);
  --danger: #EF4444;
  
  --bg-color: #F8FAFC;
  --surface: rgba(255, 255, 255, 0.8);
  --surface-solid: #FFFFFF;
  --surface-border: rgba(15, 23, 42, 0.08);
  
  --text-main: #0F172A;
  --text-muted: #64748B;
  
  --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  --transition-fast: all 0.2s ease;
  --transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
  --mouse-x: 50%;
  --mouse-y: 50%;
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
  padding-top: 100px;
  padding-bottom: 60px;
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
}

.bg-ambient {
  position: fixed;
  top: 0; left: 0; width: 100vw; height: 100vh;
  z-index: -1;
  overflow: hidden;
  pointer-events: none;
}

.ambient-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.45;
  animation: floatBlob 18s infinite alternate cubic-bezier(0.4, 0, 0.2, 1);
}

.blob-1 {
  top: -10%; right: -5%; width: 500px; height: 500px;
  background: var(--primary-glow);
}

.blob-2 {
  bottom: -10%; left: -5%; width: 600px; height: 600px;
  background: var(--accent-glow);
  animation-delay: -9s;
}

@keyframes floatBlob {
  0% { transform: translate(0, 0) scale(1); }
  100% { transform: translate(-60px, 40px) scale(1.1); }
}

.container {
  max-width: 1140px;
  margin: 0 auto;
  padding: 0 24px;
}

.glass-card {
  background: var(--surface);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid var(--surface-border);
  border-radius: 20px;
  box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.05);
  transition: var(--transition);
}

.mouse-glow {
  position: relative;
  overflow: hidden;
}

.mouse-glow::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: radial-gradient(
    500px circle at var(--mouse-x) var(--mouse-y),
    rgba(16, 185, 129, 0.08),
    transparent 50%
  );
  z-index: 0;
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.mouse-glow:hover::before {
  opacity: 1;
}

.mouse-glow > * {
  position: relative;
  z-index: 1;
}

.navbar {
  position: fixed;
  top: 0; left: 0; width: 100%;
  z-index: 1000;
  padding: 16px 0;
  background: rgba(248, 250, 252, 0.8);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--surface-border);
}

.nav-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  max-width: 1140px;
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
  width: 14px;
  height: 14px;
  background: var(--primary);
  border-radius: 4px;
  box-shadow: 0 0 12px var(--primary-glow);
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 12px;
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
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
}

.btn-primary:hover {
  background: var(--primary);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px var(--primary-glow);
}

.btn-ghost {
  background: white;
  color: var(--text-main);
  border: 1px solid var(--surface-border);
}

.btn-ghost:hover {
  background: var(--bg-color);
  border-color: var(--text-main);
  transform: translateY(-2px);
}

.user-dropdown-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  background: white;
  border: 1px solid var(--surface-border);
  padding: 4px 12px 4px 6px;
  border-radius: 30px;
  cursor: pointer;
  font-family: var(--font-main);
  font-weight: 600;
  font-size: 13px;
  color: var(--text-main);
  transition: var(--transition);
}

.user-dropdown-btn:hover {
  border-color: var(--text-main);
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
}

.nav-avatar-sm {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  object-fit: cover;
  border: 1.5px solid var(--primary);
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  width: 200px;
  background: white;
  border: 1px solid var(--surface-border);
  border-radius: 16px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.1);
  padding: 8px;
  display: none;
  flex-direction: column;
  z-index: 1001;
  animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.dropdown-menu.show { display: flex; }

.dropdown-item {
  padding: 10px 14px;
  text-decoration: none;
  color: var(--text-main);
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: 10px;
  transition: var(--transition-fast);
}

.dropdown-item:hover {
  background: rgba(16, 185, 129, 0.08);
  color: var(--primary);
}

.dropdown-item.danger:hover {
  background: rgba(239, 68, 68, 0.08);
  color: var(--danger);
}

.hero-card {
  padding: 36px;
  margin-bottom: 24px;
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 28px;
  animation: fadeInScale 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.avatar-wrapper {
  position: relative;
  width: 105px;
  height: 105px;
}

.avatar-frame {
  width: 100%; height: 100%;
  border-radius: 50%;
  padding: 3px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  box-shadow: 0 8px 24px var(--primary-glow);
}

.avatar-frame img {
  width: 100%; height: 100%;
  border-radius: 50%;
  object-fit: cover;
  background: white;
  border: 3px solid white;
}

.status-online {
  position: absolute;
  bottom: 4px; right: 4px;
  width: 16px; height: 16px;
  border-radius: 50%;
  background: var(--primary);
  border: 3px solid white;
  box-shadow: 0 0 10px var(--primary);
}

.hero-meta h1 {
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.03em;
  margin-bottom: 6px;
}

.badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #d1fae5;
  color: #047857;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
}

.badge-pulse {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: #047857;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(4, 120, 87, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(4, 120, 87, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(4, 120, 87, 0); }
}

.completion-ring-box {
  text-align: right;
  background: rgba(255, 255, 255, 0.6);
  padding: 16px 20px;
  border-radius: 16px;
  border: 1px solid var(--surface-border);
}

.progress-bar-bg {
  width: 140px; height: 8px;
  background: #E2E8F0;
  border-radius: 10px;
  overflow: hidden;
  margin-top: 8px;
}

.progress-bar-fill {
  height: 100%;
  width: <?= $completion_score ?>%;
  background: linear-gradient(90deg, var(--primary), var(--accent));
  border-radius: 10px;
  transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
  margin-bottom: 28px;
  animation: slideUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.stat-card {
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 30px -10px rgba(15, 23, 42, 0.08);
  border-color: rgba(16, 185, 129, 0.3);
}

.stat-icon {
  width: 48px; height: 48px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  background: #ecfdf5;
  color: var(--primary);
  flex-shrink: 0;
}

.stat-value {
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.stat-label {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 600;
  text-transform: uppercase;
}

.cards-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
  animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.dashboard-card {
  padding: 28px;
  display: flex;
  flex-direction: column;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--surface-border);
}

.card-title {
  font-size: 16px;
  font-weight: 800;
  letter-spacing: -0.02em;
  display: flex;
  align-items: center;
  gap: 10px;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-item label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-item span {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-main);
  background: white;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--surface-border);
}

@keyframes fadeInScale {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 960px) {
  .hero-card { grid-template-columns: auto 1fr; }
  .completion-ring-box { grid-column: 1 / -1; text-align: left; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .cards-grid { grid-template-columns: 1fr; }
}

@media (max-width: 580px) {
  .hero-card { grid-template-columns: 1fr; text-align: center; }
  .avatar-wrapper { margin: 0 auto; }
  .stats-grid { grid-template-columns: 1fr; }
  body { padding-top: 80px; }
}
</style>
</head>
<body>

<div class="bg-ambient">
  <div class="ambient-blob blob-1"></div>
  <div class="ambient-blob blob-2"></div>
</div>

<nav class="navbar">
  <div class="nav-container">
    <a href="../dashboard.php" class="logo">
      <span class="logo-mark"></span>
      EcoScrap
    </a>

    <div class="nav-actions">
      <a href="update_profile.php" class="btn btn-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
        <span>Edit Profile</span>
      </a>

      <div class="dropdown" style="position: relative;">
        <button class="user-dropdown-btn" id="userMenuBtn">
          <img src="<?= $image ?>" alt="Avatar" class="nav-avatar-sm">
          <span><?= htmlspecialchars(explode(' ', $user['name'] ?? 'User')[0], ENT_QUOTES, 'UTF-8') ?></span>
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </button>

        <div class="dropdown-menu" id="userDropdown">
          <a href="profile.php" class="dropdown-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            My Account
          </a>
          <a href="update_profile.php" class="dropdown-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            Settings
          </a>
          <div style="height: 1px; background: var(--surface-border); margin: 4px 0;"></div>
          <a href="../logout.php" class="dropdown-item danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            Logout
          </a>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="container">

  <div class="glass-card mouse-glow hero-card">
    <div class="avatar-wrapper">
      <div class="avatar-frame">
        <img src="<?= $image ?>" alt="Profile Picture">
      </div>
      <div class="status-online" title="Account Active"></div>
    </div>

    <div class="hero-meta">
      <h1><?= htmlspecialchars($user['name'] ?? 'User Account', ENT_QUOTES, 'UTF-8') ?></h1>
      <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <span class="badge-pill">
          <span class="badge-pulse"></span>
          Eco Member
        </span>
        <span style="color: var(--text-muted); font-size: 14px; font-weight: 500;">
          <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </span>
      </div>
    </div>

    <div class="completion-ring-box">
      <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
        Profile Completion
      </div>
      <div style="font-size: 20px; font-weight: 800; color: var(--primary);">
        <?= $completion_score ?>%
      </div>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill"></div>
      </div>
    </div>
  </div>

  <!-- Dynamic Stats Grid -->
  <div class="stats-grid">
    <div class="glass-card mouse-glow stat-card">
      <div class="stat-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
      </div>
      <div>
        <div class="stat-value"><?= $total_recycled_kg ?> kg</div>
        <div class="stat-label">Scrap Recycled</div>
      </div>
    </div>

    <div class="glass-card mouse-glow stat-card">
      <div class="stat-icon" style="background: #e0f2fe; color: var(--accent);">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
      </div>
      <div>
        <!-- Dynamically Rendered Pickups Count -->
        <div class="stat-value"><?= $completed_pickups ?></div>
        <div class="stat-label">Pickups Done</div>
      </div>
    </div>

    <div class="glass-card mouse-glow stat-card">
      <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
      </div>
      <div>
        <div class="stat-value"><?= $completed_pickups * 50 ?></div>
        <div class="stat-label">Eco Points</div>
      </div>
    </div>

    <div class="glass-card mouse-glow stat-card">
      <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
      </div>
      <div>
        <div class="stat-value">Verified</div>
        <div class="stat-label">Account Status</div>
      </div>
    </div>
  </div>

  <div class="cards-grid">
    <div class="glass-card mouse-glow dashboard-card">
      <div class="card-header">
        <div class="card-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          Personal Details
        </div>
        <a href="update_profile.php" class="btn btn-ghost" style="padding: 6px 12px; font-size: 12px;">Edit</a>
      </div>

      <div class="info-list">
        <div class="info-item">
          <label>Full Name</label>
          <span><?= htmlspecialchars($user['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="info-item">
          <label>Email Address</label>
          <span><?= htmlspecialchars($user['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="info-item">
          <label>Phone Number</label>
          <span><?= htmlspecialchars($user['phone'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
    </div>

    <div class="glass-card mouse-glow dashboard-card">
      <div class="card-header">
        <div class="card-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          Location & Address
        </div>
        <a href="update_profile.php" class="btn btn-ghost" style="padding: 6px 12px; font-size: 12px;">Edit</a>
      </div>

      <div class="info-list">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="info-item">
            <label>Place</label>
            <span><?= htmlspecialchars($user['place'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="info-item">
            <label>District</label>
            <span><?= htmlspecialchars($user['district'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div class="info-item">
            <label>State</label>
            <span><?= htmlspecialchars($user['state'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="info-item">
            <label>Pincode</label>
            <span><?= htmlspecialchars($user['pincode'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>

        <div class="info-item">
          <label>Residential Address</label>
          <span><?= htmlspecialchars($user['address'] ?? 'No residential address set.', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const userMenuBtn = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');

if (userMenuBtn && userDropdown) {
  userMenuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    userDropdown.classList.toggle('show');
  });

  document.addEventListener('click', () => {
    userDropdown.classList.remove('show');
  });
}

document.querySelectorAll('.mouse-glow').forEach(element => {
  element.addEventListener('mousemove', e => {
    const rect = element.getBoundingClientRect();
    element.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
    element.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);
  });
});
</script>

</body>
</html>