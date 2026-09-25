<?php
// admin/profile.php

session_start();

require_once '../includes/db.php';
require_once '../includes/functions.php';

/*
|--------------------------------------------------------------------------
| ESCAPE OUTPUT
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
|
| The admin table contains admin_id, name, email, password, and created_at.
| It does not contain a role column, so only admin_id is checked.
|
*/

if (!isset($_SESSION['admin_id'])) {
    redirect('../login.php');
}

$adminId = (int)$_SESSION['admin_id'];

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        setMessage(
            'danger',
            'Please enter your full name.'
        );

        redirect('profile.php');
    }

    if ($password !== '') {
        if (strlen($password) < 8) {
            setMessage(
                'danger',
                'Password must contain at least 8 characters.'
            );

            redirect('profile.php');
        }

        if ($password !== $confirmPassword) {
            setMessage(
                'danger',
                'New password and confirmation password do not match.'
            );

            redirect('profile.php');
        }
    }

    if ($password === '') {
        $stmt = $conn->prepare(
            'UPDATE admin
             SET name = ?
             WHERE admin_id = ?'
        );

        if (!$stmt) {
            setMessage(
                'danger',
                'Unable to prepare the profile update.'
            );

            redirect('profile.php');
        }

        $stmt->bind_param(
            'si',
            $name,
            $adminId
        );
    } else {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare(
            'UPDATE admin
             SET name = ?, password = ?
             WHERE admin_id = ?'
        );

        if (!$stmt) {
            setMessage(
                'danger',
                'Unable to prepare the password update.'
            );

            redirect('profile.php');
        }

        $stmt->bind_param(
            'ssi',
            $name,
            $passwordHash,
            $adminId
        );
    }

    if ($stmt->execute()) {
        $_SESSION['name'] = $name;

        setMessage(
            'success',
            'Admin profile updated successfully.'
        );
    } else {
        setMessage(
            'danger',
            'Profile update failed. Please try again.'
        );
    }

    $stmt->close();

    redirect('profile.php');
}

/*
|--------------------------------------------------------------------------
| FETCH ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    'SELECT name, email
     FROM admin
     WHERE admin_id = ?'
);

if (!$stmt) {
    die('Unable to load admin profile.');
}

$stmt->bind_param(
    'i',
    $adminId
);

$stmt->execute();

$admin = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$admin) {
    session_destroy();
    redirect('../login.php');
}

$adminName = $admin['name'] ?? 'Administrator';
$adminEmail = $admin['email'] ?? '';

$initial = strtoupper(
    substr(trim($adminName), 0, 1)
);

/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['message'])) {
    $messageData = $_SESSION['message'];

    if (is_array($messageData)) {
        $messageType = $messageData['type'] ?? 'info';
        $message = $messageData['text'] ?? '';
    }

    unset($_SESSION['message']);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Profile | EcoScrap</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --bg-color: #f8fafc;
            --surface: #ffffff;
            --surface-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #10b981;
            --primary-dark: #059669;
            --danger: #ef4444;
            --font-main: "Inter", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            color: var(--text-main);
            background:
                linear-gradient(
                    135deg,
                    #f8fafc 0%,
                    #f0fdf4 48%,
                    #f8fafc 100%
                );
            font-family: var(--font-main);
        }

        button,
        input {
            font: inherit;
        }

        .ambient-blur {
            position: fixed;
            z-index: -1;
            pointer-events: none;
            border-radius: 50%;
            filter: blur(4px);
        }

        .blur-1 {
            top: -10%;
            right: 0;
            width: 600px;
            height: 600px;
            background:
                radial-gradient(
                    circle,
                    rgba(16, 185, 129, .15) 0%,
                    transparent 70%
                );
        }

        .blur-2 {
            bottom: -5%;
            left: 200px;
            width: 500px;
            height: 500px;
            background:
                radial-gradient(
                    circle,
                    rgba(14, 165, 233, .15) 0%,
                    transparent 70%
                );
        }

        .workspace-container {
            width: min(980px, calc(100% - 40px));
            margin: 0 auto;
            padding: 42px 0 60px;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .page-title {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .page-title-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            color: var(--primary-dark);
            background: rgba(16, 185, 129, .12);
            border-radius: 14px;
            font-size: 24px;
        }

        .page-title h1 {
            margin: 0;
            color: var(--text-main);
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .page-title p {
            margin: 7px 0 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 15px;
            color: var(--text-main);
            background: rgba(255, 255, 255, .9);
            border: 1px solid var(--surface-border);
            border-radius: 11px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all .2s ease;
        }

        .back-button:hover {
            color: var(--primary-dark);
            border-color: var(--primary);
            box-shadow: 0 7px 18px rgba(15, 23, 42, .06);
            transform: translateX(-2px);
        }

        .alert-message {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            padding: 14px 16px;
            border: 1px solid transparent;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .alert-message.success {
            color: #047857;
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .alert-message.danger {
            color: #b91c1c;
            background: #fef2f2;
            border-color: #fecaca;
        }

        .alert-message.info {
            color: #1d4ed8;
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .alert-close {
            margin-left: auto;
            padding: 0;
            color: currentColor;
            background: transparent;
            border: 0;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 270px minmax(0, 1fr);
            gap: 22px;
            align-items: start;
        }

        .glass-card {
            background: rgba(255, 255, 255, .88);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            box-shadow: 0 14px 35px rgba(15, 23, 42, .06);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .profile-summary {
            padding: 28px 22px;
            text-align: center;
        }

        .profile-avatar {
            display: grid;
            place-items: center;
            width: 90px;
            height: 90px;
            margin: 0 auto 17px;
            color: #ffffff;
            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    #0ea5e9
                );
            border: 6px solid rgba(255, 255, 255, .85);
            border-radius: 50%;
            box-shadow: 0 10px 22px rgba(16, 185, 129, .2);
            font-size: 32px;
            font-weight: 800;
        }

        .profile-summary h2 {
            margin: 0;
            color: var(--text-main);
            font-size: 19px;
            font-weight: 800;
        }

        .profile-summary p {
            margin: 7px 0 18px;
            color: var(--text-muted);
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .profile-role {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 11px;
            color: var(--primary-dark);
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
        }

        .profile-summary-footer {
            margin-top: 25px;
            padding-top: 18px;
            border-top: 1px solid var(--surface-border);
            color: var(--text-muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .profile-form-card {
            padding: 30px;
        }

        .section-heading {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--surface-border);
        }

        .section-heading-icon {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            color: var(--primary-dark);
            background: rgba(16, 185, 129, .11);
            border-radius: 11px;
            font-size: 20px;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--text-main);
            font-size: 18px;
            font-weight: 800;
        }

        .section-heading p {
            margin: 5px 0 0;
            color: var(--text-muted);
            font-size: 12px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 14px;
            z-index: 1;
            color: #94a3b8;
            transform: translateY(-50%);
            font-size: 18px;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            height: 46px;
            padding: 0 14px 0 43px;
            color: var(--text-main);
            background: rgba(248, 250, 252, .86);
            border: 1px solid var(--surface-border);
            border-radius: 11px;
            outline: none;
            transition: all .2s ease;
        }

        .form-control:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, .1);
        }

        .form-control[readonly] {
            color: #64748b;
            cursor: not-allowed;
            background: #f1f5f9;
        }

        .password-control {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            padding: 0;
            color: #94a3b8;
            background: transparent;
            border: 0;
            border-radius: 7px;
            cursor: pointer;
            transform: translateY(-50%);
        }

        .password-toggle:hover {
            color: var(--primary-dark);
            background: #ecfdf5;
        }

        .form-help {
            margin-top: 7px;
            color: var(--text-muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .password-section {
            margin-top: 27px;
            padding-top: 23px;
            border-top: 1px solid var(--surface-border);
        }

        .password-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 17px;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 800;
        }

        .password-section-title i {
            color: var(--primary);
            font-size: 18px;
        }

        .password-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--surface-border);
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 43px;
            padding: 10px 17px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            transition: all .2s ease;
        }

        .button-light {
            color: var(--text-muted);
            background: #ffffff;
            border: 1px solid var(--surface-border);
        }

        .button-light:hover {
            color: var(--text-main);
            border-color: #94a3b8;
        }

        .button-primary {
            color: #ffffff;
            background: var(--primary);
            border: 1px solid var(--primary);
            box-shadow: 0 8px 18px rgba(16, 185, 129, .2);
        }

        .button-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        @media (max-width: 800px) {
            .workspace-container {
                width: min(680px, calc(100% - 32px));
                padding-top: 28px;
            }

            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-summary {
                display: flex;
                align-items: center;
                gap: 18px;
                padding: 21px;
                text-align: left;
            }

            .profile-avatar {
                width: 70px;
                height: 70px;
                flex: 0 0 70px;
                margin: 0;
                font-size: 26px;
            }

            .profile-summary p {
                margin-bottom: 10px;
            }

            .profile-summary-footer {
                display: none;
            }
        }

        @media (max-width: 650px) {
            .password-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (max-width: 600px) {
            .workspace-container {
                width: calc(100% - 24px);
                padding-top: 22px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .back-button {
                align-self: flex-start;
            }

            .profile-summary {
                align-items: flex-start;
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin: 0 auto;
            }

            .profile-summary-content {
                width: 100%;
            }

            .profile-form-card {
                padding: 22px 17px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .form-actions .button {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="ambient-blur blur-1"></div>
<div class="ambient-blur blur-2"></div>

<main class="workspace-container">

    <header class="page-header">

        <div class="page-title">

            <div class="page-title-icon">
                <i class="ri-user-settings-line"></i>
            </div>

            <div>
                <h1>Admin Profile</h1>

                <p>
                    Manage your account details and security settings.
                </p>
            </div>

        </div>

        <a
            href="dashboard.php"
            class="back-button"
        >
            <i class="ri-arrow-left-line"></i>
            Back to Dashboard
        </a>

    </header>

    <?php if ($message !== ''): ?>

        <div
            class="alert-message <?= e($messageType) ?>"
            role="alert"
        >
            <?php if ($messageType === 'success'): ?>
                <i class="ri-checkbox-circle-fill"></i>
            <?php elseif ($messageType === 'danger'): ?>
                <i class="ri-error-warning-fill"></i>
            <?php else: ?>
                <i class="ri-information-fill"></i>
            <?php endif; ?>

            <span><?= e($message) ?></span>

            <button
                type="button"
                class="alert-close"
                onclick="this.parentElement.remove()"
                aria-label="Close"
            >
                &times;
            </button>
        </div>

    <?php endif; ?>

    <section class="profile-layout">

        <aside class="glass-card profile-summary">

            <div class="profile-avatar">
                <?= e($initial) ?>
            </div>

            <div class="profile-summary-content">

                <h2>
                    <?= e($adminName) ?>
                </h2>

                <p>
                    <?= e($adminEmail) ?>
                </p>

                <span class="profile-role">
                    <i class="ri-shield-user-line"></i>
                    Administrator
                </span>

            </div>

            <div class="profile-summary-footer">
                Manage your EcoScrap administrator account,
                profile information, and password securely.
            </div>

        </aside>

        <section class="glass-card profile-form-card">

            <div class="section-heading">

                <div class="section-heading-icon">
                    <i class="ri-account-circle-line"></i>
                </div>

                <div>
                    <h2>Account Information</h2>

                    <p>
                        Update your personal information below.
                    </p>
                </div>

            </div>

            <form
                method="post"
                action="profile.php"
                autocomplete="off"
            >

                <div class="form-group">

                    <label
                        class="form-label"
                        for="name"
                    >
                        Full Name
                    </label>

                    <div class="input-wrapper">

                        <i class="ri-user-line input-icon"></i>

                        <input
                            type="text"
                            class="form-control"
                            id="name"
                            name="name"
                            value="<?= e($adminName) ?>"
                            maxlength="100"
                            autocomplete="name"
                            required
                        >

                    </div>

                </div>

                <div class="form-group">

                    <label
                        class="form-label"
                        for="email"
                    >
                        Email Address
                    </label>

                    <div class="input-wrapper">

                        <i class="ri-mail-line input-icon"></i>

                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            value="<?= e($adminEmail) ?>"
                            readonly
                        >

                    </div>

                    <div class="form-help">
                        Email address cannot be changed from this page.
                    </div>

                </div>

                <div class="password-section">

                    <div class="password-section-title">
                        <i class="ri-lock-password-line"></i>
                        Change Password
                    </div>

                    <div class="password-grid">

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="password"
                            >
                                New Password
                            </label>

                            <div class="input-wrapper">

                                <i class="ri-lock-line input-icon"></i>

                                <input
                                    type="password"
                                    class="form-control password-control"
                                    id="password"
                                    name="password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Leave blank to keep current"
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="password"
                                    aria-label="Show password"
                                >
                                    <i class="ri-eye-line"></i>
                                </button>

                            </div>

                            <div class="form-help">
                                Minimum 8 characters.
                            </div>

                        </div>

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="confirm_password"
                            >
                                Confirm New Password
                            </label>

                            <div class="input-wrapper">

                                <i class="ri-lock-2-line input-icon"></i>

                                <input
                                    type="password"
                                    class="form-control password-control"
                                    id="confirm_password"
                                    name="confirm_password"
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Repeat new password"
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="confirm_password"
                                    aria-label="Show password"
                                >
                                    <i class="ri-eye-line"></i>
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="form-actions">

                    <a
                        href="dashboard.php"
                        class="button button-light"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="button button-primary"
                    >
                        <i class="ri-save-3-line"></i>
                        Save Changes
                    </button>

                </div>

            </form>

        </section>

    </section>

</main>

<script>

document.querySelectorAll('.password-toggle').forEach(button => {

    button.addEventListener('click', function () {

        const targetId = this.dataset.target;
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'ri-eye-off-line';
            this.setAttribute('aria-label', 'Hide password');
        } else {
            input.type = 'password';
            icon.className = 'ri-eye-line';
            this.setAttribute('aria-label', 'Show password');
        }

    });

});

</script>

</body>
</html>