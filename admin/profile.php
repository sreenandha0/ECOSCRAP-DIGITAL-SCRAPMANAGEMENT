<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    redirect('../login.php');
}

$adminId = (int) $_SESSION['admin_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($name === '' || ($password !== '' && (strlen($password) < 8 || $password !== $confirm))) {
        setMessage('danger', 'Enter a name and, if changing it, a matching password of at least 8 characters.');
        redirect('profile.php');
    }

    if ($password === '') {
        $stmt = $conn->prepare('UPDATE admin SET name = ? WHERE admin_id = ?');
        $stmt->bind_param('si', $name, $adminId);
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE admin SET name = ?, password = ? WHERE admin_id = ?');
        $stmt->bind_param('ssi', $name, $hash, $adminId);
    }
    $stmt->execute();
    $stmt->close();
    $_SESSION['name'] = $name;
    setMessage('success', 'Admin profile updated successfully.');
    redirect('profile.php');
}

$stmt = $conn->prepare('SELECT name, email FROM admin WHERE admin_id = ?');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | EcoScrap</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { min-height: 100vh; padding: 40px 20px; background: var(--bg-color); font-family: var(--font-main); }
        .workspace { max-width: 760px; margin: 0 auto; }
        .topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:28px; }
        .profile-card { background:rgba(255,255,255,.95); border:1px solid var(--surface-border); border-radius:20px; padding:32px; box-shadow:0 15px 35px rgba(15,23,42,.05); }
        .form-label { font-size:13px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em; }
        .form-control { border-radius:10px; padding:11px 14px; }
        .btn-save { background:var(--primary); border:0; border-radius:10px; color:#fff; font-weight:600; padding:12px 22px; }
    </style>
</head>
<body>
<main class="workspace">
    <header class="topbar">
        <div><h1 class="h3 mb-1"><i class="ri-user-settings-line text-success"></i> Admin Profile</h1><p class="text-muted mb-0">Manage your account details.</p></div>
        <a href="dashboard.php" class="btn btn-light border rounded-3"><i class="ri-arrow-left-line"></i> Dashboard</a>
    </header>
    <?php if (isset($_SESSION['message'])): $message = $_SESSION['message']; unset($_SESSION['message']); ?>
        <div class="alert alert-<?= htmlspecialchars($message['type']) ?>"><?= htmlspecialchars($message['text']) ?></div>
    <?php endif; ?>
    <section class="profile-card">
        <form method="post">
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="name">Full Name</label><input class="form-control" id="name" name="name" value="<?= htmlspecialchars($admin['name'] ?? '') ?>" required></div>
                <div class="col-12"><label class="form-label">Email Address</label><input class="form-control" value="<?= htmlspecialchars($admin['email'] ?? '') ?>" readonly></div>
                <div class="col-md-6"><label class="form-label" for="password">New Password</label><input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label" for="confirm_password">Confirm New Password</label><input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password"></div>
            </div>
            <div class="mt-4 text-end"><button class="btn-save" type="submit"><i class="ri-save-line"></i> Save Changes</button></div>
        </form>
    </section>
</main>
</body>
</html>
