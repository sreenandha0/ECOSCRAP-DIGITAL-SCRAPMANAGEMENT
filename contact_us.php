<?php
session_start();
require_once "includes/functions.php";

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verifyCsrfToken();
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($message) > 2000) {
        $error = "Message is too long. Please keep it under 2000 characters.";
    } else {
        // In a real application, this would send an email or store in DB.
        // For this project, we simply show a success message as per rules.
        $success = "Thank you for reaching out, " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "! Your message has been sent successfully. We will get back to you soon.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | EcoScrap</title>
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
            padding: 60px 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .contact-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #0f172a;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }
        .btn-submit {
            background: #10b981;
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background: #059669;
        }
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .contact-info {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 30px;
        }
        .info-item h3 { font-size: 1.1rem; margin-bottom: 5px; color: #0f172a; }
        .info-item p { color: #64748b; }
        @media (max-width: 600px) {
            .contact-info { flex-direction: column; gap: 20px; }
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
    <h1>Contact Us</h1>
    <p>Have a question or need assistance? Reach out to the EcoScrap team and we'll be happy to help.</p>
</div>

<div class="content-section">
    <div class="contact-form">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" action="contact_us.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" required maxlength="150">
            </div>
            
            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="5" required maxlength="2000"></textarea>
            </div>
            
            <button type="submit" class="btn-submit">Send Message</button>
        </form>
        
        <div class="contact-info">
            <div class="info-item">
                <h3>Email Us</h3>
                <p>support@ecoscrap.com</p>
            </div>
            <div class="info-item">
                <h3>Call Us</h3>
                <p>+91 98765 43210</p>
            </div>
            <div class="info-item">
                <h3>Visit Us</h3>
                <p>EcoPark, Tech City, India</p>
            </div>
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
