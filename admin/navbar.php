<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <img
            src="../assets/logo/ecoscrap-logo.png"
            alt="EcoScrap logo"
        >

        <div class="brand-text">
            <strong>EcoScrap</strong>
            <span>Admin workspace</span>
        </div>

    </div>


    <div class="nav-title">
        Workspace
    </div>


    <nav class="sidebar-nav">

        <a
            href="dashboard.php"
            class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>"
        >
            <i class="ri-dashboard-line"></i>
            <span>Dashboard</span>
        </a>


        <a
            href="manage.php"
            class="<?php echo $currentPage === 'manage.php' ? 'active' : ''; ?>"
        >
            <i class="ri-recycle-line"></i>
            <span>Scrap Requests</span>
        </a>


        <a
            href="approve_collectors.php"
            class="<?php echo $currentPage === 'approve_collectors.php' ? 'active' : ''; ?>"
        >
            <i class="ri-truck-line"></i>
            <span>Collectors</span>
        </a>


        <a
            href="manageuser.php"
            class="<?php echo $currentPage === 'manageuser.php' ? 'active' : ''; ?>"
        >
            <i class="ri-group-line"></i>
            <span>Users</span>
        </a>


        <a
            href="reports.php"
            class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>"
        >
            <i class="ri-bar-chart-line"></i>
            <span>Reports</span>
        </a>


        <a
            href="profile.php"
            class="<?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>"
        >
            <i class="ri-settings-3-line"></i>
            <span>Profile</span>
        </a>

    </nav>


    <div class="sidebar-bottom">

        <div class="admin-profile">

            <div class="admin-avatar">
                <?php
                echo strtoupper(
                    substr($_SESSION['name'] ?? 'A', 0, 1)
                );
                ?>
            </div>


            <div class="admin-details">

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $_SESSION['name'] ?? 'Admin',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </strong>

                <span>
                    Administrator
                </span>

            </div>

        </div>


        <a
            href="../logout.php"
            class="logout-link"
        >
            <i class="ri-logout-box-r-line"></i>
            <span>Logout</span>
        </a>

    </div>

</aside>