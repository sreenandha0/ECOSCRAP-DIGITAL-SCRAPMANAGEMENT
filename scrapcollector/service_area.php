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
$success = '';

/*
|--------------------------------------------------------------------------
| Fetch Current Pincode
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    'SELECT name, pincode
     FROM scrapcollector
     WHERE collector_id = ?'
);

if (!$stmt) {
    die('Unable to prepare service area query.');
}

$stmt->bind_param('i', $collector_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die('Collector record not found.');
}

$collector = $result->fetch_assoc();
$stmt->close();

/*
|--------------------------------------------------------------------------
| Update Pincode
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
        $new_pincode = trim(
            (string) ($_POST['pincode'] ?? '')
        );

        if ($new_pincode === '') {
            $error = 'Please enter a service pincode.';
        } elseif (!preg_match('/^[0-9]{4,10}$/', $new_pincode)) {
            $error = 'Please enter a valid numeric pincode.';
        } else {
            $update_stmt = $conn->prepare(
                'UPDATE scrapcollector
                 SET pincode = ?
                 WHERE collector_id = ?'
            );

            if (!$update_stmt) {
                $error =
                    'Unable to prepare service area update.';
            } else {
                $update_stmt->bind_param(
                    'si',
                    $new_pincode,
                    $collector_id
                );

                if ($update_stmt->execute()) {
                    $success =
                        'Service pincode updated successfully.';

                    $collector['pincode'] = $new_pincode;
                } else {
                    $error =
                        'Unable to update the service pincode.';
                }

                $update_stmt->close();
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

    <title>Manage Service Area | EcoScrap</title>

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
            max-width: 620px;
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
            display: flex;
            align-items: flex-start;
            gap: 8px;

            margin-bottom: 20px;
            padding: 13px 16px;

            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-success {
            color: #065F46;
            background: #D1FAE5;
            border: 1px solid #A7F3D0;
        }

        .alert-error {
            color: #991B1B;
            background: #FEE2E2;
            border: 1px solid #FCA5A5;
        }

        .area-summary {
            display: flex;
            align-items: center;
            gap: 14px;

            margin-bottom: 25px;
            padding: 18px;

            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 13px;
        }

        .area-icon {
            width: 46px;
            height: 46px;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #FFFFFF;
            background: var(--primary-dark);
            border-radius: 12px;
            font-size: 23px;
        }

        .area-summary h2 {
            margin-bottom: 3px;
            font-size: 15px;
        }

        .area-summary p {
            color: var(--muted);
            font-size: 13px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
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

            margin-top: 27px;
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
        <h1>Manage Service Area</h1>
        <p>Update the pincode where you accept scrap pickup requests.</p>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success">
            <i class="ri-checkbox-circle-line"></i>
            <span><?= e($success); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            <i class="ri-error-warning-line"></i>
            <span><?= e($error); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" class="card">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']); ?>"
        >

        <div class="area-summary">
            <div class="area-icon">
                <i class="ri-map-pin-2-fill"></i>
            </div>

            <div>
                <h2>Current Service Area</h2>
                <p>
                    Pincode:
                    <?= e($collector['pincode'] ?? 'Not set'); ?>
                </p>
            </div>
        </div>

        <div class="form-group">
            <label for="pincode">
                Service Pincode
            </label>

            <input
                type="text"
                id="pincode"
                name="pincode"
                maxlength="10"
                inputmode="numeric"
                pattern="[0-9]{4,10}"
                required
                value="<?= e($collector['pincode'] ?? ''); ?>"
            >

            <span class="hint">
                Enter a numeric pincode between 4 and 10 digits.
            </span>
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
                <i class="ri-save-line"></i>
                Save Pincode
            </button>
        </div>
    </form>

</main>

</body>
</html>