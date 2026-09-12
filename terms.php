<?php
$pageTitle = "Terms & Conditions";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $pageTitle; ?> | EcoScrap</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

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

            --spring: cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(
                    circle at 90% 0%,
                    rgba(130, 200, 67, 0.14),
                    transparent 30%
                ),
                var(--body-bg);
            color: var(--text-main);
            font-family: "DM Sans", sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .terms-page-shell {
            min-height: calc(100vh - 76px);
            padding: 34px 5% 52px;
        }

        .page-container {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }

        /* Navigation */

        .top-navigation {
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 5%;
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--eco-dark);
        }

        .brand-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 13px;
            background: linear-gradient(
                135deg,
                var(--eco-light),
                var(--eco-primary)
            );
            color: var(--white);
            font-size: 22px;
            box-shadow: 0 8px 18px rgba(46, 125, 50, 0.18);
        }

        .brand-text {
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 21px;
            font-weight: 800;
            letter-spacing: -0.4px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--eco-primary);
            font-size: 13px;
            font-weight: 800;
            transition: 0.25s ease;
        }

        .back-btn:hover {
            color: var(--eco-primary-dark);
            transform: translateX(-3px);
        }

        /* Page Header */

        .page-top {
            margin: 0 auto 24px;
        }

        .breadcrumb {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .breadcrumb a {
            color: var(--eco-primary);
            font-weight: 700;
        }

        .page-title {
            margin-bottom: 8px;
            color: var(--eco-dark);
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: clamp(28px, 3vw, 38px);
            letter-spacing: -0.8px;
        }

        .page-desc {
            max-width: 760px;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.65;
        }

        /* Hero Card */

        .terms-hero {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            min-height: 190px;
            margin-bottom: 24px;
            padding: 30px 34px;
            border-radius: var(--radius-lg);
            background: linear-gradient(
                120deg,
                rgba(0, 77, 64, 0.98),
                rgba(46, 125, 50, 0.95)
            );
            color: var(--white);
            box-shadow: var(--shadow-md);
        }

        .terms-hero::before {
            position: absolute;
            top: -90px;
            right: 10%;
            width: 240px;
            height: 240px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 50%;
            content: "";
        }

        .terms-hero::after {
            position: absolute;
            top: -35px;
            right: 3%;
            width: 180px;
            height: 180px;
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 50%;
            content: "";
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 720px;
        }

        .hero-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            margin-bottom: 15px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.14);
            color: var(--eco-light);
            font-size: 27px;
        }

        .hero-content h1 {
            margin-bottom: 8px;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: clamp(24px, 3vw, 34px);
            letter-spacing: -0.5px;
        }

        .hero-content p {
            max-width: 650px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 13px;
            line-height: 1.65;
        }

        .hero-badge {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            padding: 11px 15px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.10);
            color: rgba(255, 255, 255, 0.85);
            font-size: 12px;
            font-weight: 700;
        }

        .hero-badge i {
            color: var(--eco-light);
            font-size: 17px;
        }

        /* Policy Card */

        .policy-card {
            padding: 30px;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .last-updated {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 28px;
            padding: 9px 14px;
            border: 1px solid #ccebc9;
            border-radius: 999px;
            background: #effaf0;
            color: var(--eco-primary);
            font-size: 12px;
            font-weight: 800;
        }

        .last-updated i {
            color: var(--eco-primary);
            font-size: 16px;
        }

        /* Policy Sections */

        .policy-section {
            padding: 22px 0;
            border-bottom: 1px solid #edf2ef;
        }

        .policy-section:first-of-type {
            padding-top: 0;
        }

        .policy-section:last-of-type {
            border-bottom: 0;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            color: var(--eco-dark);
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 17px;
            font-weight: 800;
        }

        .section-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 11px;
            background: #effaf0;
            color: var(--eco-primary);
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
            font-weight: 800;
        }

        .policy-section p {
            margin-left: 46px;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.8;
        }

        .policy-section ul {
            display: grid;
            gap: 8px;
            margin: 13px 0 0 66px;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .policy-section li::marker {
            color: var(--eco-light);
        }

        /* Information Box */

        .info-box {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-top: 26px;
            padding: 17px 18px;
            border: 1px solid #ccebc9;
            border-left: 4px solid var(--eco-light);
            border-radius: var(--radius-sm);
            background: #effaf0;
        }

        .info-box i {
            flex: 0 0 auto;
            color: var(--eco-primary);
            font-size: 22px;
        }

        .info-box p {
            color: var(--eco-primary-dark);
            font-size: 13px;
            line-height: 1.7;
        }

        /* Footer */

        .site-footer {
            padding: 24px 5%;
            border-top: 1px solid var(--border);
            background: var(--white);
            color: var(--text-muted);
            text-align: center;
            font-size: 13px;
        }

        /* Responsive Design */

        @media (max-width: 900px) {
            .terms-page-shell {
                padding: 26px 18px 38px;
            }

            .top-navigation {
                padding: 14px 18px;
            }

            .terms-hero {
                align-items: flex-start;
                flex-direction: column;
                padding: 26px 24px;
            }

            .hero-badge {
                align-self: flex-start;
            }

            .policy-card {
                padding: 24px;
            }
        }

        @media (max-width: 560px) {
            .brand-text {
                font-size: 19px;
            }

            .back-btn {
                font-size: 0;
            }

            .back-btn i {
                font-size: 21px;
            }

            .page-title {
                font-size: 28px;
            }

            .terms-hero {
                padding: 23px 18px;
                border-radius: 20px;
            }

            .policy-card {
                padding: 20px 17px;
                border-radius: 20px;
            }

            .section-title {
                align-items: flex-start;
                font-size: 16px;
            }

            .policy-section p {
                margin-left: 0;
            }

            .policy-section ul {
                margin-left: 20px;
            }

            .info-box {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <!-- NAVIGATION -->
    

    <!-- PAGE CONTENT -->
    <main class="terms-page-shell">
        <div class="page-container">

            <!-- PAGE TOP -->
            <div class="page-top">
                <div class="breadcrumb">
                    <a href="index.php">Home</a>
                    <i class="ph ph-caret-right"></i>
                    <span>Terms & Conditions</span>
                </div>

                <h1 class="page-title">Terms & Conditions</h1>

                <p class="page-desc">
                    Please review the terms that apply when using the
                    EcoScrap Smart Scrap Management System.
                </p>
            </div>

            <!-- HERO -->
            <section class="terms-hero">
                <div class="hero-content">
                    <div class="hero-icon">
                        <i class="ph ph-file-text"></i>
                    </div>

                    <h1>Clear rules for responsible recycling</h1>

                    <p>
                        These terms help ensure that users, collectors, and
                        administrators can use the EcoScrap platform safely,
                        accurately, and responsibly.
                    </p>
                </div>

                <div class="hero-badge">
                    <i class="ph ph-shield-check"></i>
                    <span>Platform Guidelines</span>
                </div>
            </section>

            <!-- TERMS CARD -->
            <section class="policy-card">

                <div class="last-updated">
                    <i class="ph ph-calendar"></i>
                    <span>Last Updated: September 2026</span>
                </div>

                <!-- SECTION 1 -->
                <div class="policy-section">
                    <div class="section-title">
                        <span class="section-number">1</span>
                        <span>Acceptance of Terms</span>
                    </div>

                    <p>
                        By accessing, registering, or using the EcoScrap
                        Smart Scrap Management System, you agree to comply
                        with these Terms and Conditions.
                    </p>
                </div>

                <!-- SECTION 2 -->
                <div class="policy-section">
                    <div class="section-title">
                        <span class="section-number">2</span>
                        <span>User Accounts</span>
                    </div>

                    <p>
                        Users must provide accurate information while creating
                        an account and are responsible for maintaining the
                        confidentiality of their login credentials.
                    </p>
                </div>

                <!-- SECTION 3 -->
                <div class="policy-section">
                    <div class="section-title">
                        <span class="section-number">3</span>
                        <span>Scrap Collection Requests</span>
                    </div>

                    <p>
                        Users can submit scrap collection requests by providing
                        accurate details regarding the scrap type, quantity,
                        pickup address, pincode, preferred date, and time.
                    </p>
                </div>

                <!-- SECTION 4 -->
                <div class="policy-section">
                    <div class="section-title">
                        <span class="section-number">4</span>
                        <span>Scrap Collectors</span>
                    </div>

                    <p>
                        Scrap collectors are responsible for managing assigned
                        collection requests and updating request statuses
                        accurately within the EcoScrap system.
                    </p>
                </div>

                <!-- SECTION 5 -->
                <div class="policy-section">
                    <div class="section-title">
                        <span class="section-number">5</span>
                        <span>QR Code Verification</span>
                    </div>

                    <p>
                        EcoScrap may use QR code verification to confirm scrap
                        collection activities. QR codes must only be used for
                        their intended verification purposes.
                    </p>
                </div>

                <!-- SECTION 6 -->
                <div class="policy-section">
                    <div class="section-title">
                        <span class="section-number">6</span>
                        <span>User Responsibilities</span>
                    </div>

                    <p>Users must not:</p>

                    <ul>
                        <li>Provide false or misleading information.</li>
                        <li>Misuse the EcoScrap platform.</li>
                        <li>Attempt to access another user's account.</li>
                        <li>Create unauthorized or duplicate accounts.</li>
                        <li>Interfere with the normal functioning of the system.</li>
                    </ul>
                </div>

                <!-- SECTION 7 -->
                <div class="policy-section">
                    <div class="section-title">
                        <span class="section-number">7</span>
                        <span>Account Suspension</span>
                    </div>

                    <p>
                        EcoScrap administrators may suspend or deactivate
                        accounts that violate these Terms and Conditions or
                        misuse the system.
                    </p>
                </div>

                <!-- SECTION 8 -->
                <div class="policy-section">
                    <div class="section-title">
                        <span class="section-number">8</span>
                        <span>Changes to Terms</span>
                    </div>

                    <p>
                        EcoScrap may update these Terms and Conditions when
                        necessary. Continued use of the platform indicates
                        acceptance of the updated terms.
                    </p>
                </div>

                <!-- INFORMATION BOX -->
                <div class="info-box">
                    <i class="ph ph-info"></i>

                    <p>
                        By creating an account or continuing to use EcoScrap,
                        you confirm that you have read and agreed to these
                        Terms and Conditions.
                    </p>
                </div>

            </section>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="site-footer">
        © <?php echo date("Y"); ?> EcoScrap.
        Smart Scrap Management System.
    </footer>

</body>
</html>