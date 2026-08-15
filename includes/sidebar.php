<?php
// includes/sidebar.php
// Dynamic role-based sidebar navigation for Clash Arena

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user_role'] ?? 'customer'; // customer = player
$tab = $_GET['tab'] ?? '';
?>
<!-- Sidebar Component -->
<aside class="dashboard-sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="sidebar-brand d-flex align-items-center"><img src="../assets/images/logo.jpg" alt="Logo" class="me-2" style="height: 30px; width: auto; border-radius: 6px;">CLASH<span>ARENA</span></a>
    </div>

    <ul class="sidebar-menu">
        <?php if ($role === 'superadmin'): ?>
            <!-- Super Admin Navigation Links -->
            <li class="sidebar-menu-item <?php echo ($current_page == 'dashboard.php' && $tab === '') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php" class="sidebar-menu-link">
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                <a href="../superadmin/users.php" class="sidebar-menu-link">
                    <i class="fa-solid fa-users-gear"></i> Manage Players
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'admins') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=admins" class="sidebar-menu-link">
                    <i class="fa-solid fa-user-shield"></i> Manage Admins
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'games') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=games" class="sidebar-menu-link">
                    <i class="fa-solid fa-gamepad"></i> Manage Games
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'categories') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=categories" class="sidebar-menu-link">
                    <i class="fa-solid fa-layer-group"></i> Tournament Categories
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'payments') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=payments" class="sidebar-menu-link">
                    <i class="fa-solid fa-credit-card"></i> Payment Settings
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'revenue') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=revenue" class="sidebar-menu-link">
                    <i class="fa-solid fa-money-bill-trend-up"></i> Revenue Analytics
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'reports') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=reports" class="sidebar-menu-link">
                    <i class="fa-regular fa-clipboard"></i> Reports
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'settings') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=settings" class="sidebar-menu-link">
                    <i class="fa-solid fa-gears"></i> System Settings
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'permissions') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=permissions" class="sidebar-menu-link">
                    <i class="fa-solid fa-shield-halved"></i> Role & Permissions
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'audit') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=audit" class="sidebar-menu-link">
                    <i class="fa-solid fa-database"></i> Audit Logs
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'cms') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=cms" class="sidebar-menu-link">
                    <i class="fa-solid fa-window-restore"></i> Website CMS
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'security') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=security" class="sidebar-menu-link">
                    <i class="fa-solid fa-lock"></i> Security
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'backup') ? 'active' : ''; ?>">
                <a href="../superadmin/dashboard.php?tab=backup" class="sidebar-menu-link">
                    <i class="fa-solid fa-file-shield"></i> Backup & Restore
                </a>
            </li>

        <?php elseif ($role === 'admin'): ?>
            <!-- Admin Navigation Links -->
            <li class="sidebar-menu-item <?php echo ($current_page == 'dashboard.php' && $tab === '') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php" class="sidebar-menu-link">
                    <i class="fa-solid fa-house-laptop"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'tournaments') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=tournaments" class="sidebar-menu-link">
                    <i class="fa-solid fa-trophy"></i> Tournament Mgmt
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'brackets') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=brackets" class="sidebar-menu-link">
                    <i class="fa-solid fa-sitemap"></i> Bracket Generator
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'matches') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=matches" class="sidebar-menu-link">
                    <i class="fa-solid fa-calendar-days"></i> Match Mgmt
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'teams') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=teams" class="sidebar-menu-link">
                    <i class="fa-solid fa-people-group"></i> Teams
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'players') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=players" class="sidebar-menu-link">
                    <i class="fa-solid fa-users"></i> Players
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'results') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=results" class="sidebar-menu-link">
                    <i class="fa-solid fa-square-poll-vertical"></i> Results
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'leaderboard') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=leaderboard" class="sidebar-menu-link">
                    <i class="fa-solid fa-medal"></i> Leaderboard
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'rewards') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=rewards" class="sidebar-menu-link">
                    <i class="fa-solid fa-gift"></i> Rewards
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'announcements') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=announcements" class="sidebar-menu-link">
                    <i class="fa-solid fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'reports') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=reports" class="sidebar-menu-link">
                    <i class="fa-solid fa-chart-simple"></i> Reports
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'profile') ? 'active' : ''; ?>">
                <a href="../admin/dashboard.php?tab=profile" class="sidebar-menu-link">
                    <i class="fa-solid fa-user-check"></i> Profile
                </a>
            </li>

        <?php else: ?>
            <!-- Player (User) Navigation Links -->
            <li class="sidebar-menu-item <?php echo ($current_page == 'dashboard.php' && $tab === '') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php" class="sidebar-menu-link">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'tournaments') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=tournaments" class="sidebar-menu-link">
                    <i class="fa-solid fa-trophy"></i> Tournaments
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'schedule') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=schedule" class="sidebar-menu-link">
                    <i class="fa-solid fa-calendar-days"></i> Match Schedule
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'teams') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=teams" class="sidebar-menu-link">
                    <i class="fa-solid fa-people-group"></i> My Teams
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'leaderboard') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=leaderboard" class="sidebar-menu-link">
                    <i class="fa-solid fa-medal"></i> Leaderboard
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'rewards') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=rewards" class="sidebar-menu-link">
                    <i class="fa-solid fa-gift"></i> Rewards
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'wallet') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=wallet" class="sidebar-menu-link">
                    <i class="fa-solid fa-wallet"></i> Wallet
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'notifications') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=notifications" class="sidebar-menu-link">
                    <i class="fa-regular fa-bell"></i> Notifications
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'profile') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=profile" class="sidebar-menu-link">
                    <i class="fa-solid fa-user-ninja"></i> My Profile
                </a>
            </li>
            <li class="sidebar-menu-item <?php echo ($tab === 'settings') ? 'active' : ''; ?>">
                <a href="../user/dashboard.php?tab=settings" class="sidebar-menu-link">
                    <i class="fa-solid fa-sliders"></i> Settings
                </a>
            </li>
        <?php endif; ?>

        <li class="sidebar-menu-item mt-4">
            <hr style="border-color: rgba(255,255,255,0.1); margin: 0;">
        </li>
        <li class="sidebar-menu-item">
            <a href="../logout.php" class="sidebar-menu-link text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket text-danger"></i> Logout
            </a>
        </li>
    </ul>

    <div class="sidebar-footer text-center">
        <small style="font-size: 0.7rem; color: var(--accent-cyan); font-weight: bold; letter-spacing: 0.05em; text-transform: uppercase;">
            <i class="fa-solid fa-shield-halved me-1"></i> Session Protected
        </small>
    </div>
</aside>
