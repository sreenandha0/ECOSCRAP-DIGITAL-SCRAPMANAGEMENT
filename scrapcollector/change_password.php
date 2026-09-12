<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Session Setup
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    );

    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['collector_id']) ||
    ($_SESSION['role'] ?? '') !== 'Collector'
) {
    header('Location: ../login.php');
    exit;
}

$collector_id = (int) $_SESSION['collector_id'];

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

/*
|--------------------------------------------------------------------------
| Form Processing
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string) ($_POST['csrf_token'] ?? '');

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $posted_token)
    ) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $current_password =
            (string) ($_POST['current_password'] ?? '');

        $new_password =
            (string) ($_POST['new_password'] ?? '');

        $confirm_password =
            (string) ($_POST['confirm_password'] ?? '');

        if ($current_password === '') {
            $error = 'Please enter your current password.';
        } elseif (strlen($new_password) < 8) {
            $error =
                'The new password must contain at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'The new passwords do not match.';
        } elseif ($current_password === $new_password) {
            $error =
                'The new password must be different from the current password.';
        } else {
            $stmt = $conn->prepare(
                'SELECT password
                 FROM scrapcollector
                 WHERE collector_id = ?'
            );

            if (!$stmt) {
                $error = 'Unable to prepare password query.';
            } else {
                $stmt->bind_param('i', $collector_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $account = $result->fetch_assoc();

                $stmt->close();

                if (
                    !$account ||
                    !password_verify(
                        $current_password,
                        (string) $account['password']
                    )
                ) {
                    $error = 'Your current password is incorrect.';
                } else {
                    $new_password_hash = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    $update_stmt = $conn->prepare(
                        'UPDATE scrapcollector
                         SET password = ?
                         WHERE collector_id = ?'
                    );

                    if (!$update_stmt) {
                        $error =
                            'Unable to prepare password update.';
                    } else {
                        $update_stmt->bind_param(
                            'si',
                            $new_password_hash,
                            $collector_id
                        );

                        if ($update_stmt->execute()) {
                            session_regenerate_id(true);

                            $_SESSION['flash_success'] =
                                'Password changed successfully.';

                            $update_stmt->close();

                            header('Location: collector_profile.php');
                            exit;
                        }

                        $error =
                            'Unable to change your password.';

                        $update_stmt->close();
                    }
                }
            }
        }
    }
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

    <title>Change Password | EcoScrap</title>

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
            --primary: #10B981;
            --primary-dark: #047857;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --border: #E2E8F0;
            --text: #0F172A;
            --muted: #64748B;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            padding: 90px 16px 50px;
            color: var(--text);
            background: var(--bg);
            font-family: 'Inter', sans-serif;
        }

        .topbar {
            position: fixed;
            inset: 0 0 auto 0;
            z-index: 10;

            min-height: 70px;
            padding: 0 32px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            background: rgba(255, 255, 255, .92);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            text-decoration: none;
        }

        .brand img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .brand span {
            font-size: 20px;
            font-weight: 800;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .back-link:hover {
            color: var(--primary-dark);
        }

        .page {
            width: 100%;
            max-width: 540px;
            margin: 0 auto;
        }

        .heading {
            margin-bottom: 24px;
        }

        .heading h1 {
            font-size: 28px;
            font-weight: 800;
        }

        .heading p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 14px;
        }

        .card {
            padding: 30px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        }

        .alert {
            margin-bottom: 20px;
            padding: 13px 16px;
            color: #991B1B;
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .security-note {
            display: flex;
            gap: 10px;

            margin-bottom: 24px;
            padding: 13px;

            color: #075985;
            background: #E0F2FE;
            border: 1px solid #BAE6FD;
            border-radius: 10px;

            font-size: 13px;
            line-height: 1.5;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-bottom: 18px;
        }

        label {
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 12px 13px;

            color: var(--text);
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 9px;
            outline: none;

            font-size: 14px;
            transition: .2s ease;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .12);
        }

        .hint {
            color: var(--muted);
            font-size: 12px;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;

            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid var(--border);
        }

        .btn {
            min-height: 44px;
            padding: 10px 18px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            border-radius: 9px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-cancel {
            color: var(--text);
            background: #FFFFFF;
            border: 1px solid var(--border);
        }

        .btn-save {
            color: #FFFFFF;
            background: var(--primary-dark);
            border: 1px solid var(--primary-dark);
        }

        .btn-save:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        @media (max-width: 600px) {
            body {
                padding-top: 80px;
            }

            .topbar {
                padding: 0 16px;
            }

            .brand img {
                width: 36px;
                height: 36px;
            }

            .brand span {
                font-size: 18px;
            }

            .card {
                padding: 22px 18px;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <a href="dashboard.php" class="brand">
        <img
            src="../assets/logo/ecoscrap-logo.png"
            alt="EcoScrap"
        >
        <span>EcoScrap</span>
    </a>

    <a href="profile.php" class="back-link">
        <i class="ri-arrow-left-line"></i>
        Back to Profile
    </a>
</header>

<main class="page">

    <div class="heading">
        <h1>Change Password</h1>
        <p>Protect your EcoScrap collector account.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert">
            <i class="ri-error-warning-line"></i>
            <?= e($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']); ?>"
        >

        <div class="security-note">
            <i class="ri-shield-check-line"></i>
            <span>
                Use a password that contains at least 8 characters.
            </span>
        </div>

        <div class="form-group">
            <label for="current_password">
                Current Password
            </label>

            <input
                type="password"
                id="current_password"
                name="current_password"
                required
                autocomplete="current-password"
            >
        </div>

        <div class="form-group">
            <label for="new_password">
                New Password
            </label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                minlength="8"
                required
                autocomplete="new-password"
            >

            <span class="hint">
                Minimum 8 characters.
            </span>
        </div>

        <div class="form-group">
            <label for="confirm_password">
                Confirm New Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                required
                autocomplete="new-password"
            >
        </div>

        <div class="actions">
            <a
                href="collector_profile.php"
                class="btn btn-cancel"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-save"
            >
                <i class="ri-lock-password-line"></i>
                Change Password
            </button>
        </div>
    </form>

</main>

</body>
</html>