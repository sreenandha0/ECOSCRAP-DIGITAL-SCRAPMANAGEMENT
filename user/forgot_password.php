<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $role = $_POST['role'];
    $email = trim($_POST['email']);

    if ($role === "user") {
        $table = "user";
        $id_column = "user_id";
    } elseif ($role === "collector") {
        $table = "scrapcollector";
        $id_column = "collector_id";
    } else {
        $error = "Please select a valid role.";
    }

    if (empty($error)) {
        $sql = "SELECT $id_column, email FROM $table WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_role'] = $role;
            header("Location: reset_password.php");
            exit();
        } else {
            $error = "No account found with this email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - EcoScrap</title>
    <style>
        :root {
            --primary: #10B981;
            --primary-dark: #047857;
            --primary-deep: #065F46;
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
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .forgot-container {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 42px 36px;
        }

        .forgot-container h2 {
            font-size: clamp(32px, 4vw, 42px);
            line-height: 1.05;
            letter-spacing: -0.04em;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 12px;
        }

        .forgot-container p {
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .forgot-container form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .forgot-container label {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: -8px;
        }

        .forgot-container select,
        .forgot-container input {
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

        .forgot-container select:focus,
        .forgot-container input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
        }

        .forgot-container input::placeholder {
            color: #94A3B8;
        }

        .forgot-container button {
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

        .forgot-container button:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 32px rgba(4, 120, 87, 0.28);
        }

        .forgot-container button:active {
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

        @media (max-width: 520px) {
            .forgot-container {
                padding: 30px 20px;
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <h2>Forgot Password?</h2>
        <p>Enter your registered email to reset your password.</p>

        <form method="POST">
            <label>Select Account Type</label>
            <select name="role" required>
                <option value="">Select Role</option>
                <option value="user">User</option>
                <option value="collector">Scrap Collector</option>
            </select>

            <label>Email Address</label>
            <input
                type="email"
                name="email"
                placeholder="Enter your registered email"
                required
            >

            <button type="submit">Continue</button>
        </form>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>