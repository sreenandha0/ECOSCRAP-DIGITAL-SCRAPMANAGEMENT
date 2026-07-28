<?php
// Get current script name to handle active links
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Admin Navigation Bar -->
<style>
    .admin-navbar {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 16px;
        padding: 12px 24px;
        margin-bottom: 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        position: relative;
        z-index: 100;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: #0F172A;
        font-weight: 800;
        font-size: 20px;
        letter-spacing: -0.03em;
    }

    .nav-brand i {
        color: #10B981;
        font-size: 26px;
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 8px;
        list-style: none;
    }

    .nav-item a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 12px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        color: #64748B;
        transition: all 0.25s ease;
    }

    .nav-item a:hover {
        color: #10B981;
        background: rgba(16, 185, 129, 0.08);
    }

    .nav-item a.active {
        background: #10B981;
        color: #FFFFFF;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    }

    .nav-user {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .nav-logout {
        padding: 8px 14px;
        border-radius: 10px;
        border: 1px solid rgba(239, 68, 68, 0.2);
        background: #FEE2E2;
        color: #DC2626;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.25s ease;
    }

    .nav-logout:hover {
        background: #DC2626;
        color: #FFFFFF;
    }

    @media (max-width: 768px) {
        .admin-navbar {
            flex-direction: column;
            gap: 14px;
            align-items: flex-start;
            padding: 16px;
        }
        .nav-links {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .nav-user {
            width: 100%;
            justify-content: space-between;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            padding-top: 10px;
        }
    }
</style>

<nav class="admin-navbar">
    <!-- Brand / Logo -->
    <a href="dashboard.php" class="nav-brand">
        <i class="ph-bold ph-recycle"></i>
        <span>EcoScrap <span style="font-size: 12px; color: #10B981; background: rgba(16,185,129,0.1); padding: 2px 8px; border-radius: 6px; margin-left: 4px;">Admin</span></span>
    </a>

    <!-- Navigation Links -->
    <ul class="nav-links">
        <li class="nav-item">
            <a href="dashboard.php" class="<?= ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="ph ph-squares-four"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="manageuser.php" class="<?= ($currentPage == 'manageuser.php') ? 'active' : ''; ?>">
                <i class="ph ph-users"></i> Users
            </a>
        </li>
        <li class="nav-item">
            <a href="manage_activities.php" class="<?= ($currentPage == 'manage_activities.php') ? 'active' : ''; ?>">
                <i class="ph ph-truck"></i> Pickups
            </a>
        </li>
        <li class="nav-item">
            <a href="rates.php" class="<?= ($currentPage == 'rates.php') ? 'active' : ''; ?>">
                <i class="ph ph-currency-inr"></i> Scrap Rates
            </a>
        </li>
    </ul>

    <!-- User Profile & Logout -->
    <div class="nav-user">
        <span style="font-size: 13px; font-weight: 600; color: #0F172A;">
            👋 Admin
        </span>
        <a href="../logout.php" class="nav-logout">
            <i class="ph ph-sign-out"></i> Logout
        </a>
    </div>
</nav>