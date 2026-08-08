<?php
session_start();
require_once "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current user details
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User not found.");
}

$success = "";
$error = "";

// Update Profile
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $place = trim($_POST['place']);
    $district = trim($_POST['district']);
    $state = trim($_POST['state']);
    $pincode = trim($_POST['pincode']);

    // Basic Validation
    if (
        empty($name) ||
        empty($phone) ||
        empty($address) ||
        empty($place) ||
        empty($district) ||
        empty($state) ||
        empty($pincode)
    ) {
        $error = "All fields are required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must be 10 digits.";
    } elseif (!preg_match('/^[0-9]{6}$/', $pincode)) {
        $error = "Pincode must be 6 digits.";
    } else {

        // Keep old image by default
        $profile_image = $user['profile_image'];

        // Upload New Image
        if (!empty($_FILES['profile_image']['name'])) {

            $allowed = ['jpg','jpeg','png','webp'];
            $extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

            if (in_array($extension, $allowed)) {
                $profile_image = uniqid() . "." . $extension;
                $uploadPath = "../uploads/profile/" . $profile_image;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {

                    // Delete old image
                    if (!empty($user['profile_image']) &&
                        file_exists("../uploads/profile/" . $user['profile_image'])) {
                        unlink("../uploads/profile/" . $user['profile_image']);
                    }

                } else {
                    $error = "Failed to upload image.";
                }

            } else {
                $error = "Only JPG, JPEG, PNG and WEBP images are allowed.";
            }
        }

        if (empty($error)) {

            $update = $conn->prepare("UPDATE user SET
                name=?,
                phone=?,
                address=?,
                place=?,
                district=?,
                state=?,
                pincode=?,
                profile_image=?
                WHERE user_id=?");

            $update->bind_param(
                "ssssssssi",
                $name,
                $phone,
                $address,
                $place,
                $district,
                $state,
                $pincode,
                $profile_image,
                $user_id
            );

            if ($update->execute()) {
                $_SESSION['success'] = "Profile updated successfully.";
                header("Location: profile.php");
                exit();
            } else {
                $error = "Something went wrong.";
            }

        }

    }

}

$image = (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image']))
    ? "../uploads/profile/" . htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8')
    : "../assets/images/default-user.png";

?>
<!DOCTYPE html>
<html lang="en" class="lenis lenis-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Profile - EcoScrap</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
  /* Brand Colors */
  --primary: #10B981;    /* Emerald Green */
  --secondary: #047857;  /* Forest Green */
  --accent: #0EA5E9;     /* Sky Blue */
  --danger: #EF4444;     /* Red */
  
  /* Backgrounds & Surface */
  --bg-color: #F8FAFC;
  --surface: #FFFFFF;
  --surface-border: rgba(15, 23, 42, 0.08);
  
  /* Text */
  --text-main: #0F172A;
  --text-muted: #64748B;
  
  /* Utilities */
  --font-main: 'Inter', sans-serif;
  --transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
  --radius-lg: 20px;
  --radius-md: 12px;
  --shadow-card: 0 10px 30px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02);
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
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
}

/* ==========================================
   WIRE FRAME TOP NAVBAR
   ========================================== */
.navbar {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--surface-border);
}

.nav-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 12px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.nav-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo {
  font-weight: 700;
  font-size: 18px;
  color: var(--text-main);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 8px;
  letter-spacing: -0.03em;
}

.logo-mark {
  width: 12px;
  height: 12px;
  background: var(--primary);
  border-radius: 3px;
  flex-shrink: 0;
}

.nav-center {
  font-weight: 700;
  font-size: 15px;
  color: var(--text-main);
}

.nav-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.nav-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(15, 23, 42, 0.05);
  color: var(--text-main);
  text-decoration: none;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  transition: var(--transition);
}

.nav-back-btn:hover {
  background: rgba(15, 23, 42, 0.1);
}

/* User Dropdown */
.dropdown { position: relative; }

.user-dropdown-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: white;
  border: 1px solid var(--surface-border);
  padding: 4px 12px 4px 6px;
  border-radius: 20px;
  cursor: pointer;
  font-family: var(--font-main);
  font-weight: 600;
  font-size: 13px;
  color: var(--text-main);
  transition: var(--transition);
}

.nav-avatar-sm {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  object-fit: cover;
  border: 1.5px solid var(--primary);
}

.dropdown-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  width: 190px;
  background: white;
  border: 1px solid var(--surface-border);
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
  padding: 6px 0;
  display: none;
  flex-direction: column;
  z-index: 1001;
}

.dropdown-menu.show { display: flex; }

.dropdown-item {
  padding: 8px 16px;
  text-decoration: none;
  color: var(--text-main);
  font-size: 13px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 10px;
  transition: var(--transition);
}

.dropdown-item:hover {
  background: rgba(16, 185, 129, 0.08);
  color: var(--primary);
}

.dropdown-divider {
  height: 1px;
  background: var(--surface-border);
  margin: 4px 0;
}

/* ==========================================
   LAYOUT GRID CONTAINER
   ========================================== */
.dashboard-shell {
  max-width: 1100px;
  margin: 0 auto;
  padding: 32px 20px 60px;
}

.page-header {
  margin-bottom: 24px;
}

.page-title {
  font-size: 24px;
  font-weight: 800;
  color: var(--text-main);
  letter-spacing: -0.02em;
}

.page-subtitle {
  font-size: 14px;
  color: var(--text-muted);
  margin-top: 2px;
}

/* Main Cards Grid */
.profile-grid {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 24px;
  align-items: start;
}

/* Common Card Base */
.grid-card {
  background: var(--surface);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  padding: 28px;
  transition: var(--transition);
}

.grid-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-bottom: 16px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--surface-border);
}

.card-header-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.1);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.card-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--text-main);
  line-height: 1.2;
}

.card-subtitle {
  font-size: 12px;
  color: var(--text-muted);
}

/* Sidebar Profile Photo Card */
.avatar-card {
  text-align: center;
}

.avatar-upload-wrapper {
  position: relative;
  width: 130px;
  height: 130px;
  margin: 0 auto 16px;
}

.avatar-frame {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  padding: 4px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  box-shadow: 0 10px 25px rgba(16, 185, 129, 0.2);
}

.avatar-frame img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  background: white;
  border: 3px solid white;
}

.file-input-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 10px 16px;
  background: rgba(16, 185, 129, 0.08);
  border: 1px dashed var(--primary);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  color: var(--primary);
  cursor: pointer;
  transition: var(--transition);
}

.file-input-trigger:hover {
  background: var(--primary);
  color: white;
}

.hidden-file-input { display: none; }

.user-meta-info {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px dashed var(--surface-border);
}

.meta-badge {
  display: inline-block;
  padding: 4px 10px;
  background: rgba(15, 23, 42, 0.05);
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
}

/* Form Section Stack */
.form-cards-stack {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Inner Form Grid Systems */
.card-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 18px;
}

.full-width { grid-column: 1 / -1; }

.field-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 6px;
}

.field-input, .field-textarea {
  width: 100%;
  padding: 11px 14px;
  font-size: 14px;
  font-family: var(--font-main);
  border: 1px solid var(--surface-border);
  border-radius: var(--radius-md);
  background: var(--bg-color);
  color: var(--text-main);
  transition: var(--transition);
  outline: none;
}

.field-input:focus, .field-textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
  background: white;
}

.field-input[readonly] {
  background-color: rgba(241, 245, 249, 0.7);
  color: var(--text-muted);
  cursor: not-allowed;
  border-color: transparent;
}

/* Action Bar Card */
.actions-card {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 28px;
}

.btn-primary-custom {
  padding: 12px 28px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
}

.btn-primary-custom:hover {
  background: var(--secondary);
  box-shadow: 0 6px 20px rgba(4, 120, 87, 0.35);
}

.btn-secondary-custom {
  padding: 12px 24px;
  background: rgba(15, 23, 42, 0.06);
  color: var(--text-main);
  text-decoration: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  transition: var(--transition);
}

.btn-secondary-custom:hover {
  background: rgba(15, 23, 42, 0.12);
}

/* Alerts */
.alert-danger-custom {
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.25);
  color: var(--danger);
  padding: 14px 18px;
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
}

@media (max-width: 902px) {
  .profile-grid {
    grid-template-columns: 1fr;
  }
  .card-form-grid {
    grid-template-columns: 1fr;
  }
  .nav-center { display: none; }
}
</style>
</head>
<body>

<!-- Sticky Wireframe Navbar -->
<nav class="navbar">
  <div class="nav-container">
    
    <div class="nav-left">
      <a href="../dashboard.php" class="logo">
        <span class="logo-mark"></span>
        EcoScrap
      </a>
    </div>

    <div class="nav-center">
      Edit Profile
    </div>

    <div class="nav-right">
      <a href="profile.php" class="nav-back-btn" title="View Profile">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        <span>View Profile</span>
      </a>

      <div class="dropdown">
        <button class="user-dropdown-btn" id="userMenuBtn">
          <img src="<?= $image ?>" alt="Avatar" class="nav-avatar-sm">
          <span><?= htmlspecialchars(explode(' ', $user['name'] ?? 'User')[0], ENT_QUOTES, 'UTF-8') ?></span>
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </button>

        <div class="dropdown-menu" id="userDropdown">
          <a href="profile.php" class="dropdown-item">My Profile</a>
          <div class="dropdown-divider"></div>
          <a href="../logout.php" class="dropdown-item">Logout</a>
        </div>
      </div>
    </div>

  </div>
</nav>

<div class="dashboard-shell">

  <!-- Header Title -->
  <div class="page-header">
    <h1 class="page-title">Account Settings</h1>
    <p class="page-subtitle">Update your personal details and public profile presence.</p>
  </div>

  <?php if ($error != ""): ?>
    <div class="alert-danger-custom">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    
    <!-- Outer Cards Grid Layout -->
    <div class="profile-grid">

      <!-- LEFT COLUMN CARD: Avatar & Media Upload -->
      <div class="grid-card avatar-card">
        <div class="grid-card-header" style="justify-content: center; border: none; padding-bottom: 0; margin-bottom: 12px;">
          <span class="meta-badge">Profile Picture</span>
        </div>

        <div class="avatar-upload-wrapper">
          <div class="avatar-frame">
            <img src="<?= $image ?>" id="profilePreview" alt="Profile Picture">
          </div>
        </div>

        <label for="profile_image_input" class="file-input-trigger">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
          Upload New Image
        </label>
        <input type="file" id="profile_image_input" name="profile_image" class="hidden-file-input" accept="image/*" onchange="previewImage(this)">

        <div class="user-meta-info">
          <p style="font-size: 13px; font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></p>
          <p style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>

      <!-- RIGHT COLUMN CARDS STACK: Information Cards -->
      <div class="form-cards-stack">

        <!-- CARD 1: Personal Details -->
        <div class="grid-card">
          <div class="grid-card-header">
            <div class="card-header-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <div>
              <h2 class="card-title">Personal Information</h2>
              <p class="card-subtitle">Basic details associated with your EcoScrap account</p>
            </div>
          </div>

          <div class="card-form-grid">
            <div>
              <label class="field-label">Full Name</label>
              <input type="text" name="name" class="field-input" value="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div>
              <label class="field-label">Email Address (Read-only)</label>
              <input type="email" class="field-input" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly>
            </div>

            <div class="full-width">
              <label class="field-label">Phone Number</label>
              <input type="text" name="phone" class="field-input" value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="10-digit number" required>
            </div>
          </div>
        </div>

        <!-- CARD 2: Location & Address -->
        <div class="grid-card">
          <div class="grid-card-header">
            <div class="card-header-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
            </div>
            <div>
              <h2 class="card-title">Address & Location</h2>
              <p class="card-subtitle">Manage pickup address and regional location info</p>
            </div>
          </div>

          <div class="card-form-grid">
            <div class="full-width">
              <label class="field-label">Residential Address</label>
              <textarea name="address" class="field-textarea" rows="3" required><?= htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div>
              <label class="field-label">Place</label>
              <input type="text" name="place" class="field-input" value="<?= htmlspecialchars($user['place'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div>
              <label class="field-label">District</label>
              <input type="text" name="district" class="field-input" value="<?= htmlspecialchars($user['district'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div>
              <label class="field-label">State</label>
              <input type="text" name="state" class="field-input" value="<?= htmlspecialchars($user['state'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div>
              <label class="field-label">Pincode</label>
              <input type="text" name="pincode" class="field-input" value="<?= htmlspecialchars($user['pincode'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="6-digit pincode" required>
            </div>
          </div>
        </div>

        <!-- CARD 3: Actions Bar -->
        <div class="grid-card actions-card">
          <a href="profile.php" class="btn-secondary-custom">Cancel</a>
          <button type="submit" class="btn-primary-custom">Save Changes</button>
        </div>

      </div>

    </div>

  </form>

</div>

<script>
// User Dropdown Toggle
const userMenuBtn = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');

userMenuBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  userDropdown.classList.toggle('show');
});

document.addEventListener('click', () => {
  userDropdown.classList.remove('show');
});

// Profile Image Live Preview
function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('profilePreview').src = e.target.result;
    }
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

</body>
</html>