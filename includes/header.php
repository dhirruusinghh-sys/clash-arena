<?php
// includes/header.php
// Common dashboard header layout for Clash Arena

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Access validation
if (!isLoggedIn()) {
    $redirectUrl = (strpos($_SERVER['REQUEST_URI'], '/superadmin/') !== false || strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || strpos($_SERVER['REQUEST_URI'], '/user/') !== false) ? '../login.php?error=login_required' : 'login.php?error=login_required';
    header("Location: " . $redirectUrl);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? '';
$user_role = $_SESSION['user_role'] ?? 'customer'; // customer = player

// Map customer role name to Player in UI
$display_role = $user_role;
if ($user_role === 'customer') {
    $display_role = 'player';
}

// Detect base path for local vs production
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
$basePath = $is_localhost ? '/final' : '';

// Fetch user wallet coins and balance
$wallet_balance = 0.00;
$wallet_coins = 0;
try {
    $stmtWallet = $pdo->prepare("SELECT balance, coins FROM `wallets` WHERE `user_id` = :uid");
    $stmtWallet->execute(['uid' => $user_id]);
    $wallet = $stmtWallet->fetch();
    if ($wallet) {
        $wallet_balance = $wallet['balance'];
        $wallet_coins = $wallet['coins'];
    }
} catch (PDOException $e) {
    // Fail silently
}

// Fetch notifications
$notifications = [];
$unreadCount = 0;
try {
    $stmtNotif = $pdo->prepare("SELECT * FROM `notifications` WHERE `user_id` = :uid ORDER BY `created_at` DESC LIMIT 5");
    $stmtNotif->execute(['uid' => $user_id]);
    $notifications = $stmtNotif->fetchAll();

    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = :uid AND `is_read` = 0");
    $stmtUnread->execute(['uid' => $user_id]);
    $unreadCount = $stmtUnread->fetchColumn();
} catch (PDOException $e) {
    // Fail silently
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clash Arena Console — <?php echo ucfirst(htmlspecialchars($display_role)); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Chart.js for Dashboards -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- App Custom CSS -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/styles.css">
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation Component -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <!-- Dashboard Main Viewport -->
        <main class="dashboard-main">
            <!-- Header Top Bar -->
            <header class="dashboard-header">
                <div class="d-flex align-items-center">
                    <button class="sidebar-toggle-btn me-3 text-adaptive" id="sidebar-toggle" aria-label="Toggle navigation">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <!-- E-sports status line -->
                    <div class="d-none d-md-flex align-items-center gap-3">
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20 font-game" style="font-size: 0.8rem;">
                            <i class="fa-solid fa-circle-play me-1"></i> Live Arena Active
                        </span>
                        <?php if ($user_role === 'customer'): ?>
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-20 font-game" style="font-size: 0.8rem;">
                                <i class="fa-solid fa-coins me-1"></i> <?php echo intval($wallet_coins); ?> Coins
                            </span>
                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-20 font-game" style="font-size: 0.8rem;">
                                <i class="fa-solid fa-wallet me-1"></i> $<?php echo number_format($wallet_balance, 2); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <!-- Theme Toggle Button -->
                    <button class="theme-toggle-btn shadow-none" type="button" aria-label="Toggle Theme">
                        <i class="fa-solid fa-sun"></i>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-link text-adaptive p-1 position-relative shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-regular fa-bell fs-5"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-3" style="width: 320px !important; max-width: 85vw !important; background: var(--bg-secondary) !important; border: 1px solid var(--card-border) !important; border-radius: 12px; backdrop-filter: blur(15px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25) !important;">
                            <h6 class="dropdown-header fw-bold px-0 mb-3 font-display border-bottom pb-2" style="font-size: 0.95rem; color: var(--text-primary) !important; border-color: rgba(123, 97, 255, 0.15) !important; letter-spacing: 0.05em; text-transform: uppercase;">Notifications</h6>
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $n): ?>
                                    <div class="p-2 mb-2 rounded" style="background: rgba(123, 97, 255, 0.04); border: 1px solid rgba(123, 97, 255, 0.06); border-radius: 8px;">
                                        <div class="d-flex justify-content-between align-items-center mb-1" style="gap: 10px;">
                                            <span class="fw-bold" style="font-size: 0.85rem; font-family: var(--font-game); color: var(--accent-purple) !important;"><?php echo htmlspecialchars($n['title']); ?></span>
                                            <span class="text-nowrap" style="font-size: 0.7rem; color: var(--text-muted) !important;"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></span>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary) !important; line-height: 1.4;"><?php echo htmlspecialchars($n['message']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-3" style="font-size: 0.85rem; color: var(--text-muted) !important;">No notifications.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-link text-decoration-none text-adaptive d-flex align-items-center gap-2 p-0 border-0 shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold font-display" style="width: 38px; height: 38px; font-size: 0.9rem; background: linear-gradient(135deg, var(--accent-purple) 0%, var(--accent-cyan) 100%); color: #000000;">
                                <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                            </div>
                            <div class="text-start d-none d-lg-block">
                                <div class="fw-bold fs-7 lh-1 mb-1 text-adaptive" style="font-size: 0.85rem;"><?php echo htmlspecialchars($user_name); ?></div>
                                <div class="text-secondary text-uppercase lh-1" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em; color: var(--accent-cyan) !important;"><?php echo htmlspecialchars($display_role); ?></div>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end nav-profile-dropdown p-2 shadow-sm bg-secondary border-0" style="border: 1px solid var(--card-border) !important;">
                              <li><a class="dropdown-item rounded py-2" href="<?php echo $basePath . ($user_role === 'customer' ? '/user/dashboard.php' : ($user_role === 'admin' ? '/admin/dashboard.php' : '/superadmin/dashboard.php')); ?>"><i class="fa-solid fa-gauge me-2 text-accent-cyan"></i> Console Home</a></li>
                              <li><a class="dropdown-item rounded py-2" href="#"><i class="fa-regular fa-user me-2 text-accent-cyan"></i> My Profile</a></li>
                              <li><hr class="dropdown-divider"></li>
                              <li><a class="dropdown-item rounded text-danger py-2" href="<?php echo $basePath; ?>/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>
