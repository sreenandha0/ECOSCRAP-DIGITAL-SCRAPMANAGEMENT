<?php
session_start();
require_once "../includes/functions.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collector Registration | EcoScrap</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Design System CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--bg-color);
            position: relative;
            padding: 32px 24px;
            overflow-x: hidden;
        }

        /* Ambient Mesh Gradient Blur Orbs */
        .ambient-blur {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.55;
        }

        .blur-1 {
            width: 550px;
            height: 550px;
            top: -15%;
            left: -10%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.2) 0%, transparent 70%);
        }

        .blur-2 {
            width: 550px;
            height: 550px;
            bottom: -15%;
            right: -10%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, transparent 70%);
        }

        /* Floating Split-Screen Container */
        .split-card-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1100px;
        }

        .split-card {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.06);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
        }

        /* Left Side: Registration Form */
        .card-left {
            padding: 44px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
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

        .register-header h2 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .register-header p {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        /* Flash Message Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #059669;
        }

        /* Form Layout & Fields */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .col-span-2 {
            grid-column: span 2;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
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
            font-size: 17px;
            pointer-events: none;
            transition: var(--transition);
        }

        .form-input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            font-size: 13.5px;
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

        /* Approval Notice Banner */
        .notice-banner {
            grid-column: span 2;
            background: rgba(14, 165, 233, 0.08);
            border: 1px solid rgba(14, 165, 233, 0.2);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 12.5px;
            color: #0284c7;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.4;
            margin-top: 4px;
        }

        .notice-banner i {
            font-size: 16px;
            margin-top: 1px;
        }

        .submit-btn {
            width: 100%;
            margin-top: 24px;
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--primary) 0%, #059669 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
        }

        .login-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .login-footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .login-footer a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        /* Right Side: 3D Isometric Brand Panel */
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

        .card-right::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.22) 0%, transparent 50%);
            animation: rotateBg 22s linear infinite;
            pointer-events: none;
        }

        @keyframes rotateBg {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

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

        .iso-illustration-container {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto 0;
        }

        .iso-svg {
            width: 100%;
            height: 100%;
            max-width: 360px;
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

        /* Responsive Breakpoints */
        @media (max-width: 992px) {
            .split-card {
                grid-template-columns: 1fr;
            }
            .card-right {
                display: none;
            }
            .card-left {
                padding: 32px 24px;
            }
        }

        @media (max-width: 576px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .col-span-2 {
                grid-column: span 1;
            }
            .notice-banner {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>

    <!-- Ambient Gradient Blur Backgrounds -->
    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <div class="split-card-wrapper">
        <div class="split-card mouse-glow">

            <!-- LEFT COLUMN: REGISTRATION FORM -->
            <div class="card-left">
                <a href="../index.php" class="back-home">
                    <i class="ri-arrow-left-line"></i> Back to EcoScrap
                </a>

                <div class="brand-badge">
                    <i class="ri-truck-line"></i> Partner Program
                </div>

                <div class="register-header">
                    <h2>Collector Registration</h2>
                    <p>Join our green network and start processing scrap requests in your area.</p>
                </div>

                <!-- Flash Message Display -->
                <?php
                if (isset($_SESSION['message'])) {
                    $type = $_SESSION['message']['type'];
                    $text = $_SESSION['message']['text'];
                    $icon = ($type === "success") ? "ri-checkbox-circle-line" : "ri-error-warning-line";
                ?>
                    <div class="alert alert-<?php echo htmlspecialchars($type); ?>">
                        <i class="<?php echo $icon; ?>"></i>
                        <span><?php echo htmlspecialchars($text); ?></span>
                    </div>
                <?php
                    unset($_SESSION['message']);
                }
                ?>

                <form action="register_process.php" method="POST">
                    <div class="form-grid">

                        <!-- Full Name -->
                        <div class="form-group col-span-2">
                            <label class="form-label" for="name">Full Name</label>
                            <div class="input-group-custom">
                                <input type="text" id="name" name="name" class="form-input" placeholder="e.g. John Doe" required>
                                <i class="ri-user-line input-icon"></i>
                            </div>
                        </div>

                        <!-- Email Address -->
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <div class="input-group-custom">
                                <input type="email" id="email" name="email" class="form-input" placeholder="name@domain.com" required>
                                <i class="ri-mail-line input-icon"></i>
                            </div>
                        </div>

                        <!-- Phone Number -->
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <div class="input-group-custom">
                                <input type="tel" id="phone" name="phone" class="form-input" placeholder="10-digit mobile" maxlength="10" required>
                                <i class="ri-phone-line input-icon"></i>
                            </div>
                        </div>

                        <!-- Vehicle Registration Number -->
                        <div class="form-group">
                            <label class="form-label" for="vehicle_no">Vehicle Reg. No.</label>
                            <div class="input-group-custom">
                                <input type="text" id="vehicle_no" name="vehicle_no" class="form-input" placeholder="e.g. KL-07-AB-1234" required>
                                <i class="ri-truck-line input-icon"></i>
                            </div>
                        </div>

                        <!-- Service Area Pincode -->
                        <div class="form-group">
                            <label class="form-label" for="pincode">Service Pincode</label>
                            <div class="input-group-custom">
                                <input type="text" id="pincode" name="pincode" class="form-input" placeholder="6-digit pincode" maxlength="6" required>
                                <i class="ri-map-pin-line input-icon"></i>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group-custom">
                                <input type="password" id="password" name="password" class="form-input" placeholder="Min. 8 characters" required>
                                <i class="ri-lock-line input-icon"></i>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm Password</label>
                            <div class="input-group-custom">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Re-enter password" required>
                                <i class="ri-lock-check-line input-icon"></i>
                            </div>
                        </div>

                        <!-- Approval Notice Banner -->
                        <div class="notice-banner">
                            <i class="ri-information-line"></i>
                            <span>Collector accounts undergo admin review before you can accept pickup requests in your area.</span>
                        </div>

                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="submit-btn">
                        <span>Submit Partner Application</span>
                        <i class="ri-arrow-right-line"></i>
                    </button>
                </form>

                <p class="login-footer">
                    Already registered as a collector? <a href="../login.php">Sign In</a>
                </p>
            </div>

            <!-- RIGHT COLUMN: 3D ISOMETRIC BRAND PANEL -->
            <div class="card-right">

                <div class="iso-pill">
                    <span></span> Green Rewards Network
                </div>

                <!-- 3D Isometric Vector Illustration -->
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

                        <!-- Isometric Base Platform -->
                        <path d="M200 280 L340 200 L200 120 L60 200 Z" fill="url(#isoGrad2)" opacity="0.6" />
                        <path d="M200 280 L340 200 L340 220 L200 300 Z" fill="#022c22" opacity="0.8" />
                        <path d="M200 280 L60 200 L60 220 L200 300 Z" fill="#0f172a" opacity="0.9" />

                        <!-- Isometric House & Recycling Bin Elements -->
                        <path d="M150 170 L230 124 L230 64 L150 110 Z" fill="url(#isoGrad1)" />
                        <path d="M230 124 L270 147 L270 87 L230 64 Z" fill="#047857" />
                        <path d="M150 170 L230 124 L270 147 L190 193 Z" fill="#024e37" />

                        <!-- Isometric Floating Eco Badges -->
                        <path d="M90 150 L130 127 L170 150 L130 173 Z" fill="url(#isoGrad3)" />
                        <path d="M230 210 L270 187 L310 210 L270 233 Z" fill="url(#isoGrad3)" />

                        <!-- Micro Character Figures -->
                        <circle cx="140" cy="105" r="7" fill="#F8FAFC" />
                        <path d="M136 113 L144 113 L146 135 L134 135 Z" fill="#10B981" />

                        <circle cx="260" cy="165" r="7" fill="#F8FAFC" />
                        <path d="M256 173 L264 173 L266 195 L254 195 Z" fill="#38BDF8" />

                        <!-- Floating Leaf Symbol -->
                        <path d="M200 50 L215 40 L230 50 L215 60 Z" fill="#38BDF8" />
                        <path d="M200 50 L215 60 L215 68 L200 58 Z" fill="#0284c7" />
                        <path d="M230 50 L215 60 L215 68 L230 58 Z" fill="#0369a1" />
                    </svg>
                </div>

                <div class="right-content">
                    <h3>Recycle & Earn Value</h3>
                    <p>Turn household waste into real savings. Request verified pickups directly from your personalized dashboard.</p>
                </div>

            </div>

        </div>
    </div>

    <!-- Mouse Position Interactive Script -->
    <script>
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