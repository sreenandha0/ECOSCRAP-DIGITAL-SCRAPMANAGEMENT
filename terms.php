<?php
$pageTitle = "Terms & Conditions";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $pageTitle; ?> | EcoScrap</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --primary-green: #10B981;
            --dark-green: #047857;
            --light-green: #ECFDF5;
            --text-dark: #1F2937;
            --text-muted: #6B7280;
            --border-color: #E5E7EB;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #F8FAFC;
            color: var(--text-dark);
        }

        /* NAVBAR */

        .navbar {
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(
                135deg,
                var(--primary-green),
                var(--dark-green)
            );
            color: white;
            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 22px;
        }

        .brand-text {
            font-size: 22px;
            font-weight: 800;
            color: var(--dark-green);
        }

        .back-btn {
            text-decoration: none;
            color: var(--dark-green);
            font-weight: 600;

            display: flex;
            align-items: center;
            gap: 6px;

            transition: 0.2s;
        }

        .back-btn:hover {
            color: var(--primary-green);
        }


        /* HERO */

        .hero-section {
            background:
                linear-gradient(
                    135deg,
                    rgba(16, 185, 129, 0.95),
                    rgba(4, 120, 87, 0.95)
                );

            padding: 70px 0 90px;
            color: white;
        }

        .hero-icon {
            width: 70px;
            height: 70px;

            background: rgba(255, 255, 255, 0.18);

            border-radius: 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 34px;

            margin-bottom: 20px;
        }

        .hero-section h1 {
            font-weight: 800;
            font-size: 42px;
        }

        .hero-section p {
            max-width: 600px;
            font-size: 16px;
            opacity: 0.9;
        }


        /* CONTENT */

        .content-wrapper {
            margin-top: -45px;
            padding-bottom: 80px;
        }

        .policy-card {
            background: white;

            border-radius: 20px;

            padding: 45px;

            box-shadow:
                0 10px 35px rgba(0, 0, 0, 0.08);

            border: 1px solid var(--border-color);
        }

        .last-updated {
            display: inline-flex;

            align-items: center;
            gap: 7px;

            background: var(--light-green);

            color: var(--dark-green);

            padding: 8px 14px;

            border-radius: 20px;

            font-size: 13px;
            font-weight: 600;

            margin-bottom: 30px;
        }


        /* SECTIONS */

        .policy-section {
            margin-bottom: 35px;
        }

        .section-title {
            display: flex;

            align-items: center;

            gap: 12px;

            font-size: 20px;

            font-weight: 700;

            color: var(--dark-green);

            margin-bottom: 12px;
        }

        .section-number {
            width: 34px;
            height: 34px;

            border-radius: 10px;

            background: var(--light-green);

            color: var(--dark-green);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 14px;

            font-weight: 700;

            flex-shrink: 0;
        }

        .policy-section p {
            color: var(--text-muted);

            line-height: 1.8;

            margin-left: 46px;
        }

        .policy-section ul {
            margin-left: 25px;

            color: var(--text-muted);

            line-height: 1.9;
        }

        .policy-section li::marker {
            color: var(--primary-green);
        }


        /* INFO BOX */

        .info-box {
            background: var(--light-green);

            border-left: 4px solid var(--primary-green);

            padding: 20px;

            border-radius: 12px;

            margin-top: 30px;

            display: flex;

            gap: 14px;
        }

        .info-box i {
            font-size: 24px;

            color: var(--dark-green);
        }

        .info-box p {
            margin: 0;

            color: var(--dark-green);

            line-height: 1.7;
        }


        /* FOOTER */

        footer {
            background: #ffffff;

            border-top: 1px solid var(--border-color);

            padding: 25px 0;

            text-align: center;

            color: var(--text-muted);

            font-size: 14px;
        }


        /* MOBILE */

        @media (max-width: 768px) {

            .hero-section {
                padding: 50px 0 70px;
            }

            .hero-section h1 {
                font-size: 32px;
            }

            .policy-card {
                padding: 25px;
            }

            .policy-section p {
                margin-left: 0;
            }

            .policy-section ul {
                margin-left: 0;
            }

        }
    </style>
</head>

<body>


<!-- NAVBAR -->

<nav class="navbar">
    <div class="container d-flex justify-content-between align-items-center">

        <a href="index.php" class="brand">

            <div class="brand-icon">
                <i class="ph ph-recycle"></i>
            </div>

            <span class="brand-text">
                EcoScrap
            </span>

        </a>


        <a href="javascript:history.back()" class="back-btn">

            <i class="ph ph-arrow-left"></i>

            Back

        </a>

    </div>
</nav>



<!-- HERO -->

<section class="hero-section">

    <div class="container">

        <div class="hero-icon">

            <i class="ph ph-file-text"></i>

        </div>

        <h1>Terms & Conditions</h1>

        <p>
            Please read these Terms and Conditions carefully before
            using the EcoScrap Smart Scrap Management System.
        </p>

    </div>

</section>



<!-- CONTENT -->

<div class="content-wrapper">

    <div class="container">

        <div class="policy-card">


            <div class="last-updated">

                <i class="ph ph-calendar"></i>

                Last Updated: September 2026

            </div>



            <!-- SECTION 1 -->

            <div class="policy-section">

                <div class="section-title">

                    <div class="section-number">1</div>

                    Acceptance of Terms

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

                    <div class="section-number">2</div>

                    User Accounts

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

                    <div class="section-number">3</div>

                    Scrap Collection Requests

                </div>

                <p>
                    Users can submit scrap collection requests by providing
                    accurate details regarding the scrap type, quantity,
                    pickup address, pincode, preferred date and time.
                </p>

            </div>



            <!-- SECTION 4 -->

            <div class="policy-section">

                <div class="section-title">

                    <div class="section-number">4</div>

                    Scrap Collectors

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

                    <div class="section-number">5</div>

                    QR Code Verification

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

                    <div class="section-number">6</div>

                    User Responsibilities

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

                    <div class="section-number">7</div>

                    Account Suspension

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

                    <div class="section-number">8</div>

                    Changes to Terms

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


        </div>

    </div>

</div>



<!-- FOOTER -->

<footer>

    © <?php echo date("Y"); ?> EcoScrap.
    Smart Scrap Management System.

</footer>



</body>
</html>
```
