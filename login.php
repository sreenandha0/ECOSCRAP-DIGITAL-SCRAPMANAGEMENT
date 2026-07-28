<?php
session_start();
require_once 'includes/functions.php';

// If already logged in, redirect to appropriate portal
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'Admin':
            header("Location: admin/dashboard.php");
            exit();
        case 'User':
            header("Location: user/dashboard.php");
            exit();
        case 'Collector':
            header("Location: scrapcollector/dashboard.php");
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | EcoScrap</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Remix Icon (Icons) -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Link your style.css -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--bg-color);
            position: relative;
            padding: 24px;
            overflow-x: hidden;
        }

        /* Ambient Background Mesh Gradient */
        .ambient-blur {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.6;
        }

        .blur-1 {
            width: 500px;
            height: 500px;
            top: -10%;
            left: 10%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, transparent 70%);
        }

        .blur-2 {
            width: 500px;
            height: 500px;
            bottom: -10%;
            right: 10%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.18) 0%, transparent 70%);
        }

        /* Floating Split Card Layout */
        .split-card-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 980px;
        }

        .split-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.06);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
        }

        /* --- Left Side: Form --- */
        .card-left {
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 24px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .back-home:hover {
            color: var(--text-main);
            transform: translateX(-3px);
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 12px;
            width: fit-content;
        }

        .login-header h2 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .login-header p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 18px;
            pointer-events: none;
            transition: var(--transition);
        }

        .form-input {
            width: 100%;
            padding: 12px 16px 12px 42px;
            font-size: 14px;
            font-family: var(--font-main);
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--surface-border);
            border-radius: 10px;
            transition: var(--transition);
            outline: none;
        }

        .form-input:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .form-input:focus ~ .input-icon {
            color: var(--primary);
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            padding: 0;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--text-main);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 13px;
        }

        .custom-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--text-muted);
            user-select: none;
        }

        .custom-checkbox input {
            accent-color: var(--primary);
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .forgot-link {
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-link:hover {
            color: var(--primary);
        }

        .submit-btn {
            width: 100%;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }

        .register-section {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--surface-border);
            text-align: center;
        }

        .register-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: block;
        }

        .register-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .reg-card {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid var(--surface-border);
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 13px;
            font-weight: 500;
            transition: var(--transition);
        }

        .reg-card i {
            font-size: 16px;
            color: var(--primary);
        }

        .reg-card:hover {
            background: #ffffff;
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* --- Right Side: Isometric Illustration Panel --- */
        .card-right {
            background: linear-gradient(135deg, #0F172A 0%, #047857 100%);
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            color: #ffffff;
        }

        /* Subtly animated gradient mesh overlay */
        .card-right::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.25) 0%, transparent 50%);
            animation: rotateBg 20s linear infinite;
            pointer-events: none;
        }

        @keyframes rotateBg {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .iso-illustration-container {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* SVG Vector Isometric Art Styling */
        .iso-svg {
            width: 100%;
            height: 100%;
            max-width: 320px;
            filter: drop-shadow(0 15px 25px rgba(0,0,0,0.3));
        }

        .right-content {
            position: relative;
            z-index: 2;
        }

        .right-content h3 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .right-content p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.5;
        }

        /* Floating Pill Badge on Visual Panel */
        .iso-pill {
            position: absolute;
            top: 24px;
            right: 24px;
            z-index: 3;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .iso-pill span {
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
            display: inline-block;
        }

        /* Responsive Split Screen Switch */
        @media (max-width: 850px) {
            .split-card {
                grid-template-columns: 1fr;
            }
            .card-right {
                display: none; /* Hide visual panel on mobile devices */
            }
            .card-left {
                padding: 32px 24px;
            }
        }
    </style>
</head>

<body>

    <!-- Ambient Mesh Blurs -->
    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <div class="split-card-wrapper">
        <div class="split-card mouse-glow">
            
            <!-- LEFT COLUMN: FUNCTIONAL LOGIN FORM -->
            <div class="card-left">
                <a href="index.php" class="back-home">
                    <i class="ri-arrow-left-line"></i> Back to Home
                </a>

                <div class="brand-badge">
                    <i class="ri-shield-keyhole-line"></i> Secure Portal
                </div>

                <div class="login-header">
                    <h2>Welcome back</h2>
                    <p>Log in to access your EcoScrap account.</p>
                </div>

                <!-- PHP Message Alert -->
                <?php displayMessage(); ?>

                <form action="includes/login_process.php" method="POST">
                    
                    <!-- Email Input -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group-custom">
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input" 
                                placeholder="name@example.com" 
                                required 
                                autocomplete="email"
                            >
                            <i class="ri-mail-line input-icon"></i>
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group-custom">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="••••••••" 
                                style="padding-right: 42px;" 
                                required 
                                autocomplete="current-password"
                            >
                            <i class="ri-lock-2-line input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <i id="eye-icon" class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Options Row -->
                    <div class="form-options">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-primary submit-btn btn-lg">
                        <span>Log In</span>
                        <i class="ri-arrow-right-line"></i>
                    </button>
                </form>

                <!-- Registration Options -->
                <div class="register-section">
                    <span class="register-title">Need an account?</span>
                    <div class="register-grid">
                        <a href="user/register.php" class="reg-card">
                            <i class="ri-user-3-line"></i>
                            <span>As User</span>
                        </a>
                        <a href="scrapcollector/register.php" class="reg-card">
                            <i class="ri-truck-line"></i>
                            <span>As Collector</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: 3D ISOMETRIC BRAND PANEL -->
            <div class="card-right">
                
                <div class="iso-pill">
                    <span></span> Real-Time Tracking
                </div>

                <!-- 3D Isometric Vector Illustration (30-degree isometric grid look with cyan-to-green vector gradients) -->
                <div class="iso-illustration-container">
                    <svg class="iso-svg" viewBox="0 0 400 350" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="isoGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#10B981" />
                                <stop offset="100%" stop-color="#0EA5E9" />
                            </linearGradient>
                            <linearGradient id="isoGrad2" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#047857" />
                                <stop offset="100%" stop-color="#0F172A" />
                            </linearGradient>
                            <linearGradient id="isoGrad3" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#38BDF8" />
                                <stop offset="100%" stop-color="#10B981" />
                            </linearGradient>
                        </defs>

                        <!-- Isometric Base Platform (30 deg angle) -->
                        <path d="M200 280 L340 200 L200 120 L60 200 Z" fill="url(#isoGrad2)" opacity="0.6"/>
                        <path d="M200 280 L340 200 L340 220 L200 300 Z" fill="#022c22" opacity="0.8"/>
                        <path d="M200 280 L60 200 L60 220 L200 300 Z" fill="#0f172a" opacity="0.9"/>

                        <!-- Isometric Screen / Dashboard Card -->
                        <path d="M140 180 L260 110 L260 50 L140 120 Z" fill="url(#isoGrad1)"/>
                        <path d="M260 110 L280 120 L280 60 L260 50 Z" fill="#047857"/>
                        <path d="M140 180 L260 110 L280 120 L160 190 Z" fill="#024e37"/>

                        <!-- Isometric Recycle Nodes & Floating Elements -->
                        <path d="M100 160 L140 137 L180 160 L140 183 Z" fill="url(#isoGrad3)"/>
                        <path d="M220 220 L260 197 L300 220 L260 243 Z" fill="url(#isoGrad3)"/>

                        <!-- Miniaturized Isometric Human Figures (Representing Collectors & Users) -->
                        <!-- Tiny Character 1 -->
                        <circle cx="150" cy="115" r="7" fill="#F8FAFC"/>
                        <path d="M146 123 L154 123 L156 145 L144 145 Z" fill="#10B981"/>
                        
                        <!-- Tiny Character 2 -->
                        <circle cx="250" cy="175" r="7" fill="#F8FAFC"/>
                        <path d="M246 183 L254 183 L256 205 L244 205 Z" fill="#38BDF8"/>

                        <!-- 3D Recycling Symbol floating above -->
                        <path d="M190 70 L210 58 L230 70 L210 82 Z" fill="#38BDF8"/>
                        <path d="M190 70 L210 82 L210 90 L190 78 Z" fill="#0284c7"/>
                        <path d="M230 70 L210 82 L210 90 L230 78 Z" fill="#0369a1"/>
                    </svg>
                </div>

                <div class="right-content">
                    <h3>Smart Circular Economy</h3>
                    <p>EcoScrap connects households and verified collectors with live scrap rates and instant carbon offset metrics.</p>
                </div>

            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Password Visibility Toggle
        function togglePassword() {
            const passInput = document.getElementById("password");
            const eyeIcon = document.getElementById("eye-icon");

            if (passInput.type === "password") {
                passInput.type = "text";
                eyeIcon.className = "ri-eye-off-line";
            } else {
                passInput.type = "password";
                eyeIcon.className = "ri-eye-line";
            }
        }

        // Mouse Radial Glow Tracking
        const card = document.querySelector('.mouse-glow');
        if (card) {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.style.setProperty('--mouse-x', `${x}px`);
                card.style.setProperty('--mouse-y', `${y}px`);
            });
        }
    </script>
</body>

</html>