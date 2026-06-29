<div id="sidebar">
    <aside class="sidebar-inner">

        <div class="sidebar-logo">
            <div class="logo-icon">
                <i class='bx bxs-shield-alt'></i>
            </div>
            <div>
                <span class="logo-text">HyperPrint</span>
                <span class="admin-badge">ADMIN</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-item <?= ($currentPage=='dashboard')?'active':'' ?>">
                <i class='bx bxs-grid-alt'></i><span>Dashboard</span>
            </a>

            <a href="admin_users.php" class="nav-item <?= ($currentPage=='users')?'active':'' ?>">
                <i class='bx bxs-group'></i><span>Manage Users</span>
            </a>

            <a href="admin_jobs.php" class="nav-item <?= ($currentPage=='jobs')?'active':'' ?>">
                <i class='bx bxs-printer'></i><span>Print Jobs</span>
            </a>

            <a href="admin_collection.php" class="nav-item <?= ($currentPage=='collection')?'active':'' ?>">
                <i class='bx bxs-report'></i><span>Daily Collection</span>
            </a>

            <a href="admin_devices.php" class="nav-item <?= ($currentPage=='devices')?'active':'' ?>">
                <i class='bx bxs-devices'></i><span>Devices</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <i class='bx bx-moon' id="themeIcon"></i>
                <span id="themeLabel">Dark Mode</span>
            </button>

            <form action="admin_logout.php" method="POST">
                <button type="submit" class="logout-btn">
                    <i class='bx bx-log-out'></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>

    </aside>
</div>

<div id="overlay"></div>