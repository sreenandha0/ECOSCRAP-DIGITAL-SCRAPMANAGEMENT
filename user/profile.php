<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/functions.php";

// Auth Check
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== "User"
) {
    redirect("../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch latest user details
$stmt = $conn->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Profile image fallback
$image = "../assets/images/default-user.png";
if (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image'])) {
    $image = "../uploads/profile/" . $user['profile_image'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | EcoScrap</title>

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
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Top Bar Navigation */
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

        /* Profile Card Layout with Glassmorphism & Mouse Glow */
        .profile-card {
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
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

        /* Avatar Container */
        .avatar-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 20px;
        }

        .profile-photo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15);
        }

        .avatar-upload-btn {
            position: absolute;
            bottom: 4px;
            right: 4px;
            width: 38px;
            height: 38px;
            background: var(--primary);
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transition: var(--transition);
        }

        .avatar-upload-btn:hover {
            transform: scale(1.08);
            background: var(--secondary);
        }

        /* Form Controls */
        .form-label-custom {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .form-control-custom {
            width: 100%;
            padding: 11px 14px;
            font-size: 14px;
            border: 1px solid var(--surface-border);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.8);
            color: var(--text-main);
            transition: var(--transition);
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .form-control-custom[readonly] {
            background-color: rgba(241, 245, 249, 0.6);
            color: var(--text-muted);
            cursor: not-allowed;
            border-color: var(--surface-border);
        }

        textarea.form-control-custom {
            resize: vertical;
        }

        /* Buttons */
        .btn-submit {
            background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 12px 28px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-submit:hover {
            background: var(--secondary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        /* Alert Toast */
        .alert-success-custom {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #059669;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Meta Box */
        .meta-box {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid var(--surface-border);
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
            text-align: left;
        }

        @media (max-width: 991.65px) {
            .border-end-lg {
                border-right: none !important;
                border-bottom: 1px solid var(--surface-border);
                padding-bottom: 24px;
                margin-bottom: 24px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 20px 12px;
            }
            .profile-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

    <main class="workspace-container">

        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>
                    <i class="ri-user-settings-line" style="color: var(--primary);"></i>
                    Account Profile
                </h1>
                <p>Manage your address, contact, and account preference settings.</p>
            </div>

            <a href="dashboard.php" class="btn-secondary-custom">
                <i class="ri-arrow-left-line"></i> Dashboard
            </a>
        </header>

        <!-- Notification Alert -->
        <?php if (isset($_GET['success'])) { ?>
            <div class="alert-success-custom">
                <i class="ri-checkbox-circle-fill fs-5"></i>
                <span>Your profile information has been successfully updated.</span>
            </div>
        <?php } ?>

        <!-- Main Form Card with Mouse Glow -->
        <div class="profile-card mouse-glow" id="cardGlow">
            <form action="update_profile.php" method="POST" enctype="multipart/form-data">

                <div class="row g-4">

                    <!-- Left Column: Image & Read-only Meta -->
                    <div class="col-lg-4 text-center border-end-lg pe-lg-4">
                        
                        <div class="avatar-container">
                            <img src="<?= htmlspecialchars($image); ?>" id="avatarPreview" class="profile-photo" alt="Profile Photo">
                            <label for="profile_image_input" class="avatar-upload-btn" title="Upload New Photo">
                                <i class="ri-camera-line fs-5"></i>
                            </label>
                            <input type="file" id="profile_image_input" name="profile_image" accept="image/*" class="d-none" onchange="previewImage(this)">
                        </div>

                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['name'] ?? 'User'); ?></h5>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($user['email']); ?></p>

                        <div class="meta-box">
                            <div class="mb-3">
                                <span class="d-block text-muted small fw-semibold mb-1">ACCOUNT ROLE</span>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 600; padding: 6px 12px; border-radius: 20px; font-size: 12px;">
                                    <?= htmlspecialchars($user['role'] ?? 'User'); ?>
                                </span>
                            </div>
                            <?php if (!empty($user['created_at'])) { ?>
                                <div>
                                    <span class="d-block text-muted small fw-semibold mb-1">MEMBER SINCE</span>
                                    <span class="fw-semibold text-dark small"><?= date("M d, Y", strtotime($user['created_at'])); ?></span>
                                </div>
                            <?php } ?>
                        </div>

                    </div>

                    <!-- Right Column: Editable Fields -->
                    <div class="col-lg-8 ps-lg-4">

                        <div class="row g-3">

                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label class="form-label-custom" for="name">Full Name</label>
                                <input type="text" id="name" name="name" class="form-control-custom" value="<?= htmlspecialchars($user['name'] ?? ''); ?>" required>
                            </div>

                            <!-- Email (Read-only) -->
                            <div class="col-md-6">
                                <label class="form-label-custom">Email Address</label>
                                <input type="email" class="form-control-custom" value="<?= htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                            </div>

                            <!-- Phone -->
                            <div class="col-md-6">
                                <label class="form-label-custom" for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" class="form-control-custom" value="<?= htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+91 00000 00000">
                            </div>

                            <!-- Pincode -->
                            <div class="col-md-6">
                                <label class="form-label-custom" for="pincode">Pincode</label>
                                <input type="text" id="pincode" name="pincode" class="form-control-custom" value="<?= htmlspecialchars($user['pincode'] ?? ''); ?>">
                            </div>

                            <!-- Full Address -->
                            <div class="col-12">
                                <label class="form-label-custom" for="address">Pickup Address</label>
                                <textarea id="address" name="address" rows="3" class="form-control-custom" placeholder="House/Building Name, Street, Area"><?= htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>

                            <!-- Place -->
                            <div class="col-md-4">
                                <label class="form-label-custom" for="place">Place / City</label>
                                <input type="text" id="place" name="place" class="form-control-custom" value="<?= htmlspecialchars($user['place'] ?? ''); ?>">
                            </div>

                            <!-- District -->
                            <div class="col-md-4">
                                <label class="form-label-custom" for="district">District</label>
                                <input type="text" id="district" name="district" class="form-control-custom" value="<?= htmlspecialchars($user['district'] ?? ''); ?>">
                            </div>

                            <!-- State -->
                            <div class="col-md-4">
                                <label class="form-label-custom" for="state">State</label>
                                <input type="text" id="state" name="state" class="form-control-custom" value="<?= htmlspecialchars($user['state'] ?? ''); ?>">
                            </div>

                        </div>

                        <!-- Action Row -->
                        <div class="mt-4 pt-3 border-top text-end" style="border-color: var(--surface-border) !important;">
                            <button type="submit" class="btn-submit">
                                <i class="ri-save-line"></i> Save Changes
                            </button>
                        </div>

                    </div>

                </div>

            </form>
        </div>

    </main>

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

    <!-- Client-side Avatar Preview -->
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

</body>

</html>
