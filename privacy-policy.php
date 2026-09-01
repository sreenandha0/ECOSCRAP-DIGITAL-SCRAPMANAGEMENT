
<?php
$pageTitle = "Privacy Policy";
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
        }

        .hero-section {
            background: linear-gradient(
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
            max-width: 650px;
            font-size: 16px;
            opacity: 0.9;
        }

        .content-wrapper {
            margin-top: -45px;
            padding-bottom: 80px;
        }

        .policy-card {
            background: white;
            border-radius: 20px;
            padding: 45px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
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

        footer {
            background: #ffffff;
            border-top: 1px solid var(--border-color);
            padding: 25px 0;
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
        }

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

            <i class="ph ph-shield-check"></i>

        </div>

        <h1>Privacy Policy</h1>

        <p>
            Learn how EcoScrap collects, uses, and protects the
            information provided by users of our Smart Scrap
            Management System.
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

                    Information We Collect

                </div>

                <p>
                    EcoScrap may collect information required to create
                    and manage user accounts and process scrap collection
                    requests.
                </p>

                <ul>

                    <li>Name</li>

                    <li>Email address</li>

                    <li>Phone number</li>

                    <li>Address and pincode</li>

                    <li>Scrap collection request details</li>

                    <li>Scrap images uploaded by users</li>

                </ul>

            </div>



            <!-- SECTION 2 -->

            <div class="policy-section">

                <div class="section-title">

                    <div class="section-number">2</div>

                    How We Use Your Information

                </div>

                <p>
                    The information collected through EcoScrap may be used
                    to create accounts, process scrap pickup requests,
                    assign requests to scrap collectors, provide request
                    updates, and improve the system.
                </p>

            </div>



            <!-- SECTION 3 -->

            <div class="policy-section">

                <div class="section-title">

                    <div class="section-number">3</div>

                    Information Sharing

                </div>

                <p>
                    Relevant pickup information may be shared with
                    authorized scrap collectors when necessary to complete
                    an assigned scrap collection request.
                </p>

            </div>



            <!-- SECTION 4 -->

            <div class="policy-section">

                <div class="section-title">

                    <div class="section-number">4</div>

                    Data Security

                </div>

                <p>
                    EcoScrap takes reasonable measures to protect user
                    information from unauthorized access, modification,
                    or misuse. Users are also responsible for keeping
                    their login credentials confidential.
                </p>

            </div>



            <!-- SECTION 5 -->

            <div class="policy-section">

                <div class="section-title">

                    <div class="section-number">5</div>

                    Uploaded Images

                </div>

                <p>
                    Images uploaded as part of a scrap collection request
                    are used to identify and process the requested scrap
                    collection.
                </p>

            </div>



            <!-- SECTION 6 -->

            <div class="policy-section">

                <div class="section-title">

                    <div class="section-number">6</div>

                    Access to Information

                </div>

                <p>
                    Personal and request-related information is accessible
                    only to authorized users of the EcoScrap system when
                    required for managing and processing collection
                    activities.
                </p>

            </div>



            <!-- SECTION 7 -->

            <div class="policy-section">

                <div class="section-title">

                    <div class="section-number">7</div>

                    Changes to This Policy

                </div>

                <p>
                    EcoScrap may update this Privacy Policy when necessary.
                    Any updates will be reflected on this page.
                </p>

            </div>



            <!-- INFORMATION BOX -->

            <div class="info-box">

                <i class="ph ph-shield-check"></i>

                <p>
                    EcoScrap is committed to handling user information
                    responsibly and using it only for purposes related
                    to the operation and management of the Smart Scrap
                    Management System.
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
