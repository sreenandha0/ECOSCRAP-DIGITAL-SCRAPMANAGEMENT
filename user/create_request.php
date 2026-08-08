<?php
session_start();

require_once "../includes/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM user WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Pickup Request | EcoScrap</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Design System CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        :root {
            --bg-main: #f8fafc;
            --surface: rgba(255, 255, 255, 0.85);
            --surface-border: rgba(226, 232, 240, 0.8);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #10b981;
            --primary-hover: #059669;
            --primary-glow: rgba(16, 185, 129, 0.15);
            --radius-lg: 20px;
            --radius-md: 12px;
            --shadow-soft: 0 20px 40px -15px rgba(15, 23, 42, 0.05);
            --shadow-glow: 0 0 25px rgba(16, 185, 129, 0.12);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background-color: var(--bg-main);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
            display: flex;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Background Blur Orbs */
        .ambient-blur {
            position: fixed;
            border-radius: 50%;
            filter: blur(100px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.6;
        }

        .blur-1 {
            width: 550px;
            height: 550px;
            top: -12%;
            right: -5%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.2) 0%, transparent 70%);
        }

        .blur-2 {
            width: 500px;
            height: 500px;
            bottom: -10%;
            left: 15%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.18) 0%, transparent 70%);
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 270px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--surface);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid var(--surface-border);
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 100;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            text-decoration: none;
            margin-bottom: 36px;
            letter-spacing: -0.03em;
        }

        .sidebar-brand i {
            color: var(--primary);
            font-size: 26px;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .nav-link i {
            font-size: 20px;
        }

        .nav-link:hover {
            color: var(--text-main);
            background: rgba(15, 23, 42, 0.04);
            transform: translateX(3px);
        }

        .nav-link.active {
            color: var(--primary);
            background: rgba(16, 185, 129, 0.1);
        }

        .sidebar-footer {
            padding-top: 20px;
            border-top: 1px solid var(--surface-border);
        }

        .logout-link {
            color: #ef4444;
        }

        .logout-link:hover {
            background: rgba(239, 68, 68, 0.08);
            color: #dc2626;
        }

        /* Main Workspace Container */
        .main-content {
            margin-left: 270px;
            flex: 1;
            padding: 48px 40px;
            max-width: 960px;
            position: relative;
            z-index: 1;
        }

        /* Top Bar Header */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 36px;
        }

        .topbar-title h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.03em;
        }

        .topbar-title p {
            font-size: 14.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid var(--surface-border);
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--text-main);
            background: #ffffff;
            border-color: rgba(15, 23, 42, 0.15);
            transform: translateX(-3px);
        }

        /* Glassmorphic Form Card */
        .form-card {
            position: relative;
            background: var(--surface);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 40px;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        .form-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: var(--radius-lg);
            padding: 1px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.6), rgba(255, 255, 255, 0));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        /* Form Grid Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .col-span-2 {
            grid-column: span 2;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 19px;
            pointer-events: none;
            transition: var(--transition);
            z-index: 2;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 13px 16px 13px 46px;
            font-size: 14.5px;
            font-family: inherit;
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.95);
            border: 1.5px solid var(--surface-border);
            border-radius: var(--radius-md);
            transition: var(--transition);
            outline: none;
            appearance: none;
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2364748B'%3E%3Cpath d='M12 16L6 10H18L12 16Z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 18px;
            padding-right: 44px;
            cursor: pointer;
        }

        textarea.form-input {
            padding-top: 13px;
            resize: vertical;
            min-height: 100px;
        }

        .textarea-icon {
            top: 15px;
        }

        .form-input:focus, .form-select:focus {
            background-color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        .form-input:focus ~ .input-icon, .form-select:focus ~ .input-icon {
            color: var(--primary);
        }

        /* Custom File Dropzone UI */
        .file-upload-wrapper {
            position: relative;
            width: 100%;
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius-md);
            padding: 24px;
            text-align: center;
            background: rgba(255, 255, 255, 0.5);
            transition: var(--transition);
            cursor: pointer;
        }

        .file-upload-wrapper:hover {
            border-color: var(--primary);
            background: rgba(16, 185, 129, 0.03);
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .file-upload-text {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
        }

        .file-upload-subtext {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .file-name-preview {
            margin-top: 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
            display: none;
        }

        /* Action Buttons */
        .submit-btn {
            width: 100%;
            margin-top: 32px;
            padding: 15px 24px;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            background: var(--primary);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: inline-flex;
            gap: 10px;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.4);
            transition: var(--transition);
        }

        .submit-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -5px rgba(16, 185, 129, 0.5);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Responsive Styles */
        @media (max-width: 850px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
                padding: 24px 16px;
            }
            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            .form-card {
                padding: 24px 20px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .col-span-2 {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>

    <!-- Ambient Gradient Blur Orbs -->
    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <!-- Sidebar Drawer Navigation -->
    <aside class="sidebar">
        <div>
            <a href="dashboard.php" class="sidebar-brand">
                <i class="ri-leaf-line"></i>
                <span>EcoScrap</span>
            </a>
            <ul class="nav-menu">
                <li>
                    <a href="dashboard.php" class="nav-link">
                        <i class="ri-dashboard-line"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="create_request.php" class="nav-link active">
                        <i class="ri-add-circle-line"></i> Schedule Pickup
                    </a>
                </li>
                <li>
                    <a href="requests.php" class="nav-link">
                        <i class="ri-history-line"></i> My Requests
                    </a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-link logout-link">
                <i class="ri-logout-box-r-line"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
        
        <!-- Top Navigation Header -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>Schedule Scrap Pickup</h1>
                <p>Provide details about your recyclable scrap to request a doorstep pickup.</p>
            </div>
            <a href="dashboard.php" class="back-link">
                <i class="ri-arrow-left-line"></i> Back to Dashboard
            </a>
        </header>

        <!-- Form Glass Container -->
        <div class="form-card mouse-glow">
            <form action="create_request_process.php" method="POST" enctype="multipart/form-data">
                
                <div class="form-grid">

                    <!-- Scrap Type -->
                    <div class="form-group">
                        <label for="scrap_type" class="form-label">Scrap Type</label>
                        <div class="input-group-custom">
                            <select id="scrap_type" name="scrap_type" class="form-select" required>
                                <option value="">Select Category</option>
                                <option>Paper</option>
                                <option>Plastic</option>
                                <option>Metal</option>
                                <option>Glass</option>
                                <option>E-Waste</option>
                                <option>Mixed Waste</option>
                            </select>
                            <i class="ri-recycle-line input-icon"></i>
                        </div>
                    </div>

                    <!-- Estimated Weight -->
                    <div class="form-group">
                        <label for="scrap_weight" class="form-label">Estimated Weight (kg)</label>
                        <div class="input-group-custom">
                            <input 
                                type="number" 
                                id="scrap_weight" 
                                name="scrap_weight" 
                                step="0.01" 
                                class="form-input" 
                                placeholder="e.g. 15.5" 
                                required
                            >
                            <i class="ri-scales-3-line input-icon"></i>
                        </div>
                    </div>

                    <!-- Scrap Image Dropzone -->
                    <div class="form-group col-span-2">
                        <label class="form-label">Scrap Image</label>
                        <div class="file-upload-wrapper" id="uploadWrapper">
                            <input 
                                type="file" 
                                id="scrap_image" 
                                name="scrap_image" 
                                accept=".jpg,.jpeg,.png" 
                                required
                            >
                            <div class="upload-icon-circle">
                                <i class="ri-image-add-line"></i>
                            </div>
                            <div class="file-upload-text">Click or drag an image here to upload</div>
                            <div class="file-upload-subtext">Supports JPG, JPEG, PNG (Max 5MB)</div>
                            <div class="file-name-preview" id="fileNamePreview"></div>
                        </div>
                    </div>

                    <!-- Pickup Address -->
                    <div class="form-group col-span-2">
                        <label for="pickup_address" class="form-label">Pickup Address</label>
                        <div class="input-group-custom">
                            <textarea 
                                id="pickup_address" 
                                name="pickup_address" 
                                class="form-input" 
                                rows="3" 
                                placeholder="Enter house/building number, street name..." 
                                required
                            ><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            <i class="ri-map-pin-line input-icon textarea-icon"></i>
                        </div>
                    </div>

                    <!-- Pincode -->
                    <div class="form-group">
                        <label for="pickup_pincode" class="form-label">Pincode</label>
                        <div class="input-group-custom">
                            <input 
                                type="text" 
                                id="pickup_pincode" 
                                name="pickup_pincode" 
                                class="form-input" 
                                value="<?php echo htmlspecialchars($user['pincode'] ?? ''); ?>" 
                                required
                            >
                            <i class="ri-map-pin-user-line input-icon"></i>
                        </div>
                    </div>

                    <!-- Preferred Date -->
                    <div class="form-group">
                        <label for="preferred_pickup_date" class="form-label">Preferred Date</label>
                        <div class="input-group-custom">
                            <input 
                                type="date" 
                                id="preferred_pickup_date" 
                                name="preferred_pickup_date" 
                                class="form-input" 
                                min="<?php echo date('Y-m-d');?>" 
                                required
                            >
                            <i class="ri-calendar-line input-icon"></i>
                        </div>
                    </div>

                    <!-- Preferred Time -->
                    <div class="form-group">
                        <label for="pickup_time" class="form-label">Preferred Time</label>
                        <div class="input-group-custom">
                            <input 
                                type="time" 
                                id="pickup_time" 
                                name="pickup_time" 
                                class="form-input" 
                                required
                            >
                            <i class="ri-time-line input-icon"></i>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="form-group">
                        <label for="remarks" class="form-label">Remarks / Instructions</label>
                        <div class="input-group-custom">
                            <input 
                                type="text" 
                                id="remarks" 
                                name="remarks" 
                                class="form-input" 
                                placeholder="e.g. Call before arrival (Optional)"
                            >
                            <i class="ri-chat-1-line input-icon"></i>
                        </div>
                    </div>

                </div>

                <!-- Submit Button -->
                <button type="submit" class="submit-btn">
                    <span>Submit Pickup Request</span>
                    <i class="ri-arrow-right-line"></i>
                </button>

            </form>
        </div>

    </main>

    <!-- UI Enhancements Script -->
    <script>
        // File upload interactive name preview
        const fileInput = document.getElementById('scrap_image');
        const fileNamePreview = document.getElementById('fileNamePreview');

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                fileNamePreview.textContent = `Selected: ${e.target.files[0].name}`;
                fileNamePreview.style.display = 'block';
            } else {
                fileNamePreview.style.display = 'none';
            }
        });

        // Mouse Spotlight / Glow Tracking Effect
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