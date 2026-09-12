<?php
session_start();

require_once "../includes/db.php";

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
| Helper Functions
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support | EcoScrap</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --eco-light: #82c843;
            --eco-primary: #2e7d32;
            --eco-primary-dark: #236128;
            --eco-dark: #004d40;
            --eco-accent: #00b4d8;
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
        input {
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

        /* Search box */
        .help-search-wrap {
            max-width: 1520px;
            margin: 0 auto 28px;
        }

        .help-search-card {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--white);
            box-shadow: var(--shadow-sm);
            padding: 22px 24px;
        }

        .help-search-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .help-search-head i {
            color: var(--eco-primary);
            font-size: 20px;
        }

        .help-search-head h3 {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 18px;
            color: var(--eco-dark);
        }

        .help-search-head p {
            color: var(--text-muted);
            font-size: 13px;
        }

        .help-search-input {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            background: #fff;
        }

        .help-search-input input {
            flex: 1;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--text-main);
            font-size: 14px;
        }

        .help-search-input i {
            color: var(--eco-primary);
            font-size: 18px;
        }

        /* Quick actions */
        .help-quick-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            max-width: 1520px;
            margin: 0 auto 34px;
        }

        .help-action-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--white);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .help-action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .help-action-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #e8f7e9;
            color: var(--eco-primary);
            font-size: 20px;
        }

        .help-action-title {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 15px;
            color: var(--eco-dark);
        }

        .help-action-desc {
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        /* FAQ */
        .help-faq-wrap {
            max-width: 1520px;
            margin: 0 auto 34px;
        }

        .help-section-title {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 20px;
            color: var(--eco-dark);
            margin-bottom: 18px;
        }

        .help-faq-section {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--white);
            box-shadow: var(--shadow-sm);
            margin-bottom: 18px;
            overflow: hidden;
        }

        .help-faq-section-header {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
            background: #fafdfb;
        }

        .help-faq-section-header h4 {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 16px;
            color: var(--eco-dark);
        }

        .help-faq-item {
            border-bottom: 1px solid var(--border);
        }

        .help-faq-item:last-child {
            border-bottom: 0;
        }

        .help-faq-item summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            cursor: pointer;
            list-style: none;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 600;
        }

        .help-faq-item summary::-webkit-details-marker {
            display: none;
        }

        .help-faq-item summary i {
            color: var(--eco-primary);
            font-size: 16px;
            transition: transform 0.2s ease;
        }

        .help-faq-item[open] summary i {
            transform: rotate(180deg);
        }

        .help-faq-item p {
            padding: 0 18px 14px;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        /* Still need help */
        .help-support-card {
            max-width: 1520px;
            margin: 0 auto;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--white);
            box-shadow: var(--shadow-sm);
            padding: 24px;
            text-align: center;
        }

        .help-support-card h3 {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 18px;
            color: var(--eco-dark);
            margin-bottom: 6px;
        }

        .help-support-card p {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 18px;
        }

        .help-support-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-primary-green,
        .btn-outline-green {
            border-radius: 14px;
            padding: 12px 18px;
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

        @media (max-width: 900px) {
            .user-page-shell {
                padding: 24px 18px 36px;
            }

            .help-quick-actions {
                grid-template-columns: 1fr;
            }

            .impact-card {
                flex-direction: column;
                align-items: flex-start;
                min-height: auto;
                padding: 22px;
            }

            .impact-progress-wrap {
                width: 100%;
                flex: 1 1 auto;
            }
        }

        @media (max-width: 560px) {
            .page-title {
                font-size: 28px;
            }

            .help-support-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn-primary-green,
            .btn-outline-green {
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
            <span>Help & Support</span>
        </div>

        <h1 class="page-title">Help & Support</h1>
        <p class="page-desc">
            We're here to help you with your pickup. Find answers to common questions or contact support if you need more help.
        </p>
    </div>

    <!-- Impact card -->
    <section class="impact-card">
        <div class="impact-info">
            <p class="eyebrow"><i class="ri-customer-service-2-line"></i> Need assistance?</p>
            <h2>We're here to help you with your pickup</h2>
            <p>Browse FAQs, quick guides, and support options to get your questions answered quickly.</p>
        </div>
    </section>

    <!-- Search -->
    <div class="help-search-wrap">
        <div class="help-search-card">
            <div class="help-search-head">
                <i class="ri-search-line"></i>
                <div>
                    <h3>Search your question</h3>
                    <p>Type keywords like “pickup”, “QR”, or “password” to find relevant help.</p>
                </div>
            </div>

            <div class="help-search-input">
                <i class="ri-search-line"></i>
                <input type="text" placeholder="Search your question...">
            </div>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="help-quick-actions">
        <a href="request_pickup.php" class="help-action-card">
            <div class="help-action-icon">
                <i class="ri-file-edit-line"></i>
            </div>
            <div class="help-action-title">Pickup Request</div>
            <div class="help-action-desc">Learn how to create and manage pickup requests.</div>
        </a>

        <a href="track_status.php" class="help-action-card">
            <div class="help-action-icon">
                <i class="ri-map-pin-line"></i>
            </div>
            <div class="help-action-title">Track Pickup</div>
            <div class="help-action-desc">Check the status and location of your scheduled pickup.</div>
        </a>

        <a href="qr_verify.php" class="help-action-card">
            <div class="help-action-icon">
                <i class="ri-qr-code-line"></i>
            </div>
            <div class="help-action-title">QR Verify</div>
            <div class="help-action-desc">Understand how QR verification works for pickups.</div>
        </a>
    </div>

    <!-- FAQ -->
    <div class="help-faq-wrap">
        <h2 class="help-section-title">Frequently Asked Questions</h2>

        <!-- Pickup Requests -->
        <div class="help-faq-section">
            <div class="help-faq-section-header">
                <h4>Pickup Requests</h4>
            </div>

            <details class="help-faq-item">
                <summary>
                    How do I create a pickup request?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    From your dashboard, click “Request Pickup”, fill in your scrap details, location, and preferred time, then submit. You’ll receive a confirmation once the request is created.
                </p>
            </details>

            <details class="help-faq-item">
                <summary>
                    Can I cancel my request?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    Yes, you can cancel a pickup request from the tracking page as long as it hasn’t been marked as “In Progress” by the collector.
                </p>
            </details>

            <details class="help-faq-item">
                <summary>
                    How do I know if it is approved?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    You’ll receive a notification and see the status updated on the tracking page once your request is approved by the EcoScrap team.
                </p>
            </details>
        </div>

        <!-- Scrap Collector -->
        <div class="help-faq-section">
            <div class="help-faq-section-header">
                <h4>Scrap Collector</h4>
            </div>

            <details class="help-faq-item">
                <summary>
                    When is a collector assigned?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    A collector is assigned after your request is approved. You’ll see their details and contact information on the tracking page.
                </p>
            </details>

            <details class="help-faq-item">
                <summary>
                    Can I contact my collector?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    Yes, once assigned, you can contact your collector directly using the phone number shown on the tracking page.
                </p>
            </details>
        </div>

        <!-- Pickup Tracking -->
        <div class="help-faq-section">
            <div class="help-faq-section-header">
                <h4>Pickup Tracking</h4>
            </div>

            <details class="help-faq-item">
                <summary>
                    How do I track my pickup?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    Go to “Track Pickup” from your dashboard or the quick actions above. Enter or select your request to view its current status and timeline.
                </p>
            </details>

            <details class="help-faq-item">
                <summary>
                    What do the status stages mean?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    Common statuses: “Requested” (submitted), “Approved” (accepted by team), “Assigned” (collector allocated), “In Progress”, “Completed”, and “Cancelled”.
                </p>
            </details>
        </div>

        <!-- QR Verification -->
        <div class="help-faq-section">
            <div class="help-faq-section-header">
                <h4>QR Verification</h4>
            </div>

            <details class="help-faq-item">
                <summary>
                    What is the QR code?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    The QR code is a unique code linked to your pickup request. The collector scans it to confirm the pickup and mark it as completed.
                </p>
            </details>

            <details class="help-faq-item">
                <summary>
                    When will I receive it?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    The QR code becomes available once your pickup is approved and a collector is assigned. You can view and download it from the tracking page.
                </p>
            </details>
        </div>

        <!-- Account & Profile -->
        <div class="help-faq-section">
            <div class="help-faq-section-header">
                <h4>Account & Profile</h4>
            </div>

            <details class="help-faq-item">
                <summary>
                    How do I update my profile?
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    Go to “My Profile” from the dashboard, edit your details and profile image, then click “Save Changes”.
                </p>
            </details>

            <details class="help-faq-item">
                <summary>
                    I forgot my password.
                    <i class="ri-arrow-down-s-line"></i>
                </summary>
                <p>
                    Use the “Forgot Password” link on the login page. Enter your registered email to receive a password reset link.
                </p>
            </details>
        </div>
    </div>

    <!-- Still need help -->
    <div class="help-support-card">
        <h3>Still need help?</h3>
        <p>Contact us or send feedback if you couldn’t find what you were looking for.</p>

        <div class="help-support-actions">
            <a href="contact_support.php" class="btn-primary-green">
                Contact Support
            </a>
            <a href="feedback.php" class="btn-outline-green">
                Feedback
            </a>
        </div>
    </div>

</div>

</body>
</html>