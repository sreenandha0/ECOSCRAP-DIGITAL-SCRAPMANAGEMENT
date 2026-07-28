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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Remix Icon -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <!-- Design System CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Mesh Background */
        .ambient-blur {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
        }

        .blur-1 {
            width: 500px;
            height: 500px;
            top: -10%;
            right: 5%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.18) 0%, transparent 70%);
        }

        .blur-2 {
            width: 500px;
            height: 500px;
            bottom: -10%;
            left: 200px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.18) 0%, transparent 70%);
        }

        /* Sidebar Drawer Navigation */
        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid var(--surface-border);
            padding: 32px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 100;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
            text-decoration: none;
            margin-bottom: 36px;
            padding-left: 8px;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            border-radius: 10px;
            transition: var(--transition);
        }

        .nav-link i {
            font-size: 18px;
        }

        .nav-link:hover {
            color: var(--text-main);
            background: rgba(15, 23, 42, 0.04);
        }

        .nav-link.active {
            color: var(--primary);
            background: rgba(16, 185, 129, 0.1);
            font-weight: 600;
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
            margin-left: 260px;
            flex: 1;
            padding: 40px;
            max-width: 1000px;
            position: relative;
            z-index: 1;
        }

        /* Header Navigation */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .topbar-title h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .topbar-title p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--text-main);
            transform: translateX(-3px);
        }

        /* Form Card Container */
        .form-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            border-radius: 20px;
            padding: 36px 40px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.04);
        }

        /* Form Grid Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
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

        .form-input, .form-select {
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
            appearance: none;
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2064748B'%3E%3Cpath d='M12 16L6 10H18L12 16Z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 18px;
            padding-right: 40px;
        }

        textarea.form-input {
            padding-left: 42px;
            padding-top: 12px;
            resize: vertical;
            min-height: 90px;
        }

        .form-input:focus, .form-select:focus {
            background-color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .form-input:focus ~ .input-icon, .form-select:focus ~ .input-icon {
            color: var(--primary);
        }

        .textarea-icon {
            top: 14px;
        }

        .submit-btn {
            width: 100%;
            margin-top: 28px;
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: center;
        }

        /* Responsive Switch */
        @media (max-width: 850px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
                padding: 24px 16px;
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
                <span class="logo-mark"></span>
                EcoScrap
            </a>

            <ul class="nav-menu">
                <li>
                    <a href="dashboard.php" class="nav-link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="create_request.php" class="nav-link active">
                        <i class="ri-add-box-line"></i>
                        <span>Create Pickup</span>
                    </a>
                </li>
                <li>
                    <a href="history.php" class="nav-link">
                        <i class="ri-history-line"></i>
                        <span>My Requests</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="nav-link">
                        <i class="ri-user-3-line"></i>
                        <span>Profile</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-link logout-link">
                <i class="ri-logout-box-r-line"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
        
        <!-- Top Navigation Header -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>Schedule Scrap Pickup</h1>
                <p>Submit your scrap details to arrange a doorstep pickup.</p>
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

                    <!-- Scrap Image Upload -->
                    <div class="form-group col-span-2">
                        <label for="scrap_image" class="form-label">Scrap Image</label>
                        <div class="input-group-custom">
                            <input 
                                type="file" 
                                id="scrap_image" 
                                name="scrap_image" 
                                class="form-input" 
                                accept=".jpg,.jpeg,.png" 
                                required
                            >
                            <i class="ri-image-add-line input-icon"></i>
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
                                placeholder="Enter street name, door number..." 
                                required
                            ><?php echo htmlspecialchars($user['address']); ?></textarea>
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
                                value="<?php echo htmlspecialchars($user['pincode']); ?>" 
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
                        <label for="remarks" class="form-label">Remarks / Special Instructions</label>
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
                <button type="submit" class="btn-primary submit-btn btn-lg">
                    <span>Submit Pickup Request</span>
                    <i class="ri-arrow-right-line"></i>
                </button>

            </form>
        </div>

    </main>

    <!-- Mouse Track Script -->
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