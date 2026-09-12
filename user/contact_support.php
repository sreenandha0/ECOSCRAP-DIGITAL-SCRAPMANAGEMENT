<?php
session_start();

require_once "../includes/db.php";
require_once "../includes/csrf.php";

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Load user details from existing `user` table
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("SELECT name, email FROM user WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: ../logout.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Handle form submission (send email, no new table)
|--------------------------------------------------------------------------
*/

$errors = [];
$success = $_SESSION["contact_success"] ?? "";
unset($_SESSION["contact_success"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrfToken();

    $name    = trim((string)($_POST["name"] ?? ""));
    $email   = trim((string)($_POST["email"] ?? ""));
    $subject = trim((string)($_POST["subject"] ?? ""));
    $message = trim((string)($_POST["message"] ?? ""));

    // Validation
    if ($name === "") {
        $errors["name"] = "Name is required.";
    }

    if ($email === "") {
        $errors["email"] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors["email"] = "Enter a valid email address.";
    }

    if ($subject === "") {
        $errors["subject"] = "Subject is required.";
    }

    if ($message === "") {
        $errors["message"] = "Message is required.";
    }

    if (!$errors) {
        // Configure support email
        $to      = "support@ecoscrapp.in"; // <-- change to your real support email
        $subject = "[EcoScrap Support] " . $subject;
        $body    = "New support request from website\n\n" .
                   "Name: {$name}\n" .
                   "Email: {$email}\n" .
                   "User ID: {$user_id}\n\n" .
                   "Message:\n{$message}\n";

        $headers = "From: EcoScrap Support <no-reply@yourdomain.com>\r\n" .
                   "Reply-To: {$email}\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        if (mail($to, $subject, $body, $headers)) {
            $_SESSION["contact_success"] = "Your message has been sent. We'll get back to you soon.";
            header("Location: contact_support.php");
            exit;
        }

        $errors["general"] = "Could not send your message. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support | EcoScrap</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --eco-light: #82c843;
            --eco-primary: #2e7d32;
            --eco-primary-dark: #236128;
            --eco-dark: #004d40;
            --body-bg: #f1f5f4;
            --text-main: #16342f;
            --text-muted: #64748b;
            --text-soft: #94a3b8;
            --border: #e6eeeb;
            --white: #ffffff;
            --shadow-sm: 0 8px 25px rgba(22, 52, 47, 0.06);
            --shadow-md: 0 18px 45px rgba(22, 52, 47, 0.10);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 90% 0%, rgba(130, 200, 67, 0.14), transparent 30%),
                var(--body-bg);
            color: var(--text-main);
            font-family: "DM Sans", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        textarea {
            border: 0;
            cursor: pointer;
            font: inherit;
        }

        .user-page-shell {
            min-height: calc(100vh - 76px);
            padding: 34px 5% 52px;
            background:
                radial-gradient(circle at 90% 0%, rgba(130, 200, 67, 0.14), transparent 30%),
                var(--body-bg);
        }

        /* Page top & breadcrumb */
        .page-top {
            max-width: 1520px;
            margin: 0 auto 22px;
        }

        .breadcrumb {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 12px;
        }

        .breadcrumb a {
            color: var(--eco-primary);
            font-weight: 700;
        }

        .page-title {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: clamp(28px, 3vw, 38px);
            color: var(--eco-dark);
            letter-spacing: -0.7px;
            margin-bottom: 8px;
        }

        .page-desc {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.65;
            max-width: 780px;
        }

        /* Impact card */
        .impact-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            min-height: 150px;
            margin: 24px auto 25px;
            padding: 27px 31px;
            border-radius: var(--radius-lg);
            background: linear-gradient(120deg, rgba(0, 77, 64, 0.97), rgba(46, 125, 50, 0.95));
            color: white;
            box-shadow: var(--shadow-md);
            max-width: 1520px;
        }

        .impact-card::before {
            position: absolute;
            top: -75px;
            right: 13%;
            width: 210px;
            height: 210px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            content: "";
        }

        .impact-card::after {
            position: absolute;
            top: -35px;
            right: 5%;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            content: "";
        }

        .impact-info {
            position: relative;
            z-index: 2;
            max-width: 700px;
        }

        .impact-info .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 10px;
            color: var(--eco-light);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .impact-info h2 {
            margin-bottom: 7px;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 20px;
        }

        .impact-info p {
            max-width: 560px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 12px;
            line-height: 1.6;
        }

        /* Contact card */
        .contact-card {
            max-width: 1100px;
            margin: 0 auto;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--white);
            box-shadow: var(--shadow-sm);
            padding: 26px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 22px;
        }

        .contact-form h3,
        .contact-info h3 {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 18px;
            color: var(--eco-dark);
            margin-bottom: 8px;
        }

        .contact-form p,
        .contact-info p {
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-group {
            display: grid;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            color: var(--text-main);
            font-size: 13px;
            font-weight: 700;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 13px 14px;
            background: #fff;
            color: var(--text-main);
            font: inherit;
            transition: 0.25s ease;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--eco-light);
            box-shadow: 0 0 0 4px rgba(130, 200, 67, 0.12);
        }

        .input-error {
            color: #c62828;
            font-size: 12px;
            margin-top: -2px;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 22px;
        }

        .btn-primary-green,
        .btn-outline-green {
            border-radius: 14px;
            padding: 13px 18px;
            font-weight: 800;
            font-size: 13px;
            transition: 0.25s ease;
        }

        .btn-primary-green {
            background: var(--eco-primary);
            color: white;
            box-shadow: 0 10px 20px rgba(46, 125, 50, 0.18);
        }

        .btn-primary-green:hover {
            background: var(--eco-primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline-green {
            border: 1px solid var(--border);
            background: white;
            color: var(--text-main);
        }

        .btn-outline-green:hover {
            border-color: var(--eco-light);
            color: var(--eco-primary);
        }

        .contact-info-card {
            padding: 18px;
            border-radius: 14px;
            background: #f8fbfa;
            border: 1px solid #edf2ef;
            margin-bottom: 16px;
        }

        .contact-info-card strong {
            display: block;
            margin-bottom: 4px;
            font-size: 13px;
            color: var(--eco-dark);
        }

        .contact-info-card span {
            color: var(--text-muted);
            font-size: 12px;
        }

        .user-page-alert {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid #bde5c0;
            border-radius: var(--radius-sm);
            background: #effaf0;
            color: #256029;
            font-size: 13px;
            font-weight: 600;
        }

        .user-page-alert.error {
            border-color: #f3c5c5;
            background: #fff4f4;
            color: #a94442;
        }

        @media (max-width: 900px) {
            .user-page-shell {
                padding: 24px 18px 36px;
            }

            .contact-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .impact-card {
                flex-direction: column;
                align-items: flex-start;
                min-height: auto;
                padding: 22px;
            }
        }

        @media (max-width: 560px) {
            .page-title {
                font-size: 28px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn-primary-green,
            .actions .btn-outline-green {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="user-page-shell">

    <!-- Breadcrumb + Title -->
    <div class="page-top">
        <div class="breadcrumb">
            <a href="dashboard.php">
                <i class="ri-arrow-left-line"></i> Back
            </a>
            <span>/</span>
            <span>Contact Support</span>
        </div>

        <h1 class="page-title">Contact Support</h1>
        <p class="page-desc">
            Have a question or need help? Send us a message and our support team will get back to you as soon as possible.
        </p>
    </div>

    <!-- Impact card -->
    <section class="impact-card">
        <div class="impact-info">
            <p class="eyebrow"><i class="ri-mail-send-line"></i> Get in touch</p>
            <h2>We're here to help</h2>
            <p>Fill out the form below with your query and we'll respond to your registered email address.</p>
        </div>
    </section>

    <?php if ($success): ?>
        <div class="user-page-alert">
            <i class="ri-checkbox-circle-line"></i>
            <span><?= e($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors["general"])): ?>
        <div class="user-page-alert error">
            <i class="ri-error-warning-line"></i>
            <span><?= e($errors["general"]) ?></span>
        </div>
    <?php endif; ?>

    <!-- Contact form + info -->
    <div class="contact-card">
        <div class="contact-grid">
            <!-- Form -->
            <div class="contact-form">
                <h3>Send us a message</h3>
                <p>
                    Provide details about your issue or question. Include your request ID or relevant details if you have any.
                </p>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="<?= e($_POST['name'] ?? $user['name']) ?>"
                                class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                required
                            >
                            <?php if (isset($errors['name'])): ?>
                                <span class="input-error"><?= e($errors['name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="<?= e($_POST['email'] ?? $user['email']) ?>"
                                class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                required
                            >
                            <?php if (isset($errors['email'])): ?>
                                <span class="input-error"><?= e($errors['email']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group full">
                            <label for="subject">Subject</label>
                            <input
                                id="subject"
                                name="subject"
                                type="text"
                                value="<?= e($_POST['subject'] ?? '') ?>"
                                class="<?= isset($errors['subject']) ? 'is-invalid' : '' ?>"
                                placeholder="e.g. Issue with pickup request"
                                required
                            >
                            <?php if (isset($errors['subject'])): ?>
                                <span class="input-error"><?= e($errors['subject']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group full">
                            <label for="message">Message</label>
                            <textarea
                                id="message"
                                name="message"
                                rows="6"
                                class="<?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                                placeholder="Describe your issue or question in detail..."
                                required
                            ><?= e($_POST['message'] ?? '') ?></textarea>
                            <?php if (isset($errors['message'])): ?>
                                <span class="input-error"><?= e($errors['message']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="actions">
                        <a class="btn-outline-green" href="help.php">Back to Help</a>
                        <button class="btn-primary-green" type="submit">Send Message</button>
                    </div>
                </form>
            </div>

            <!-- Info side -->
            <div class="contact-info">
                <h3>Other ways to reach us</h3>
                <p>
                    You can also contact us using the details below. Response times may vary depending on the channel.
                </p>

                <div class="contact-info-card">
                    <strong><i class="ri-mail-line" style="color:var(--eco-primary); margin-right:6px;"></i> Email</strong>
                    <span>support@ecoscrapp.in</span>
                </div>

                <div class="contact-info-card">
                    <strong><i class="ri-phone-line" style="color:var(--eco-primary); margin-right:6px;"></i> Phone</strong>
                    <span>+91 98765 43210 (Mon–Sat, 9 AM–6 PM)</span>
                </div>

                <div class="contact-info-card">
                    <strong><i class="ri-time-line" style="color:var(--eco-primary); margin-right:6px;"></i> Response Time</strong>
                    <span>We usually respond within 24–48 hours on working days.</span>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>