<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_role'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must contain at least 6 characters.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $email = $_SESSION['reset_email'];
        $role = $_SESSION['reset_role'];

        if ($role === "user") {
            $table = "user";
        } elseif ($role === "collector") {
            $table = "scrapcollector";
        } else {
            $error = "Invalid account type.";
        }

        if (empty($error)) {
            $sql = "UPDATE $table SET password = ? WHERE email = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $error = "Database error.";
            } else {
                $stmt->bind_param("ss", $hashed_password, $email);

                if ($stmt->execute()) {
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_role']);
                    header("Location: ../login.php?reset=success");
                    exit();
                } else {
                    $error = "Unable to reset password.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - EcoScrap</title>
    <style>
        :root {
            --primary: #10B981;
            --primary-dark: #047857;
            --dark: #0F172A;
            --text: #0F172A;
            --muted: #64748B;
            --border: #E2E8F0;
            --background: #F8FAFC;
            --white: #FFFFFF;
            --shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
            --radius: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(16, 185, 129, 0.12), transparent 35%),
                radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.10), transparent 40%),
                linear-gradient(180deg, #F8FAFC 0%, #EEF2F7 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text);
        }

        .reset-container {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 42px 36px;
        }

        .reset-container h2 {
            font-size: clamp(30px, 4vw, 40px);
            line-height: 1.05;
            letter-spacing: -0.04em;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 12px;
        }

        .reset-container p {
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .reset-container form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .reset-container label {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: -8px;
        }

        .reset-container input {
            width: 100%;
            height: 52px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #fff;
            padding: 0 16px;
            font-size: 15px;
            color: var(--dark);
            outline: none;
            transition: 0.25s ease;
        }

        .reset-container input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
        }

        .reset-container input::placeholder {
            color: #94A3B8;
        }

        .reset-container button {
            height: 54px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
            box-shadow: 0 14px 28px rgba(4, 120, 87, 0.22);
        }

        .reset-container button:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 32px rgba(4, 120, 87, 0.28);
        }

        .reset-container button:active {
            transform: translateY(0);
            opacity: 0.96;
        }

        .error-msg {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(239, 68, 68, 0.08);
            color: #B91C1C;
            font-size: 14px;
            font-weight: 500;
        }

        .hint {
            font-size: 13px;
            color: #64748B;
            margin-top: -6px;
        }

        @media (max-width: 520px) {
            .reset-container {
                padding: 30px 20px;
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Reset Password</h2>
        <p>Create a new password for your account.</p>

        <form method="POST">
            <label>New Password</label>
            <input
                type="password"
                name="password"
                placeholder="Enter new password"
                required
            >

            <label>Confirm Password</label>
            <input
                type="password"
                name="confirm_password"
                placeholder="Re-enter new password"
                required
            >

            <div class="hint">Password must contain at least 6 characters.</div>

            <button type="submit">Update Password</button>
        </form>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>