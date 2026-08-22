<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | EcoScrap</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-header {
            padding: 120px 20px 60px;
            text-align: center;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(14, 165, 233, 0.05) 100%);
        }
        .page-header h1 {
            font-size: 3rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 15px;
        }
        .page-header p {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
        }
        .content-section {
            padding: 80px 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
            margin-bottom: 60px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .card h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #10b981;
        }
        .card p {
            color: #475569;
            line-height: 1.7;
        }
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="navbar">
    <a href="index.php" class="logo">
        <img src="assets/logo/ecoscrap-logo.png" alt="EcoScrap Logo" class="navbar-logo">
    </a>
    <nav class="nav-links">
        <a href="index.php">Home</a>
        <a href="about_us.php">About Us</a>
        <a href="contact_us.php">Contact Us</a>
    </nav>
    <a href="login.php" class="nav-button">Get Started <span>→</span></a>
</header>

<div class="page-header">
    <h1>About EcoScrap</h1>
    <p>We are building a smarter, greener, and more connected recycling ecosystem for a sustainable tomorrow.</p>
</div>

<div class="content-section">
    <div class="grid-2">
        <div class="card">
            <h2>Our Purpose</h2>
            <p>EcoScrap was founded with a singular vision: to turn waste into value. We aim to revolutionize the traditional scrap collection process by bridging the gap between households, scrap collectors, and recycling facilities using modern technology.</p>
        </div>
        <div class="card">
            <h2>The Problem</h2>
            <p>Millions of tons of recyclable waste end up in landfills due to unorganized collection systems. Households struggle to find reliable scrap collectors, and collectors lack efficient route planning. This friction harms the environment and wastes valuable resources.</p>
        </div>
    </div>

    <div class="card" style="margin-bottom: 60px;">
        <h2>How EcoScrap Works</h2>
        <p>Our platform digitizes the entire recycling supply chain:</p>
        <ul style="color: #475569; line-height: 1.7; margin-top: 15px; padding-left: 20px;">
            <li><strong>Users</strong> can schedule scrap pickups from the comfort of their homes.</li>
            <li><strong>Administrators</strong> manage requests and assign the best-suited scrap collectors based on location.</li>
            <li><strong>Scrap Collectors</strong> receive notifications, accept requests, and verify pickups securely using QR codes.</li>
        </ul>
    </div>

    <div class="grid-2">
        <div class="card">
            <h2>Main Features</h2>
            <p>We provide location-based assignment, real-time pickup tracking, secure QR verification, and comprehensive impact reports. Our system ensures transparency and reliability at every step.</p>
        </div>
        <div class="card">
            <h2>Environmental Objective</h2>
            <p>Every piece of scrap collected through EcoScrap is a step toward a cleaner planet. We strive to maximize recycling rates, reduce landfill overflow, and promote a circular economy.</p>
        </div>
    </div>
</div>

<footer>
    <div class="footer-logo">
        <div class="logo-symbol">E</div>
        <span>Eco<span>Scrap</span></span>
    </div>
    <p>© 2026 EcoScrap. Turn Waste Into Value.</p>
    <p>Smart • Sustainable • Connected</p>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
