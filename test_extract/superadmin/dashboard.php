<?php
// superadmin/dashboard.php
// Premium Super Admin Console Dashboard - Clash Arena

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Verify role access
checkRole(['superadmin']);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$tab = $_GET['tab'] ?? '';

$error_msg = '';
$success_msg = '';

// ==========================================
// POST ACTION HANDLERS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Broadcast Notification to All Users
    if ($action === 'broadcast_notif') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';

        if (empty($title) || empty($message)) {
            $error_msg = 'Please fill in all alert fields.';
        } else {
            try {
                $pdo->beginTransaction();
                $users = $pdo->query("SELECT id FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
                
                $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES (:uid, :title, :msg, 'info')");
                foreach ($users as $uid) {
                    $stmtNotif->execute(['uid' => $uid, 'title' => $title, 'msg' => $message]);
                }

                $stmtAnn = $pdo->prepare("INSERT INTO `announcements` (`title`, `content`, `created_by`, `status`) VALUES (:title, :content, :uid, 'active')");
                $stmtAnn->execute(['title' => $title, 'content' => $message, 'uid' => $user_id]);

                $pdo->commit();
                $success_msg = "Global broadcast notification sent successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = 'Broadcast failed: ' . $e->getMessage();
            }
        }
    }

    // 2. Add New Game
    elseif ($action === 'add_game') {
        $gname = trim($_POST['name'] ?? '');
        $gslug = trim($_POST['slug'] ?? '');
        $grules = trim($_POST['rules'] ?? '');
        $gfee = floatval($_POST['entry_fee'] ?? 0.00);
        $gprize = floatval($_POST['prize_pool'] ?? 0.00);
        $gbanner = trim($_POST['banner_url'] ?? '');
        
        if (empty($gname) || empty($gslug)) {
            $error_msg = 'Game name and slug are required.';
        } else {
            try {
                $stmtAddG = $pdo->prepare("INSERT INTO `games` (`name`, `slug`, `rules`, `entry_fee`, `prize_pool`, `banner_url`) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtAddG->execute([$gname, $gslug, $grules, $gfee, $gprize, $gbanner]);
                $success_msg = "Game category added to Clash Arena catalog.";
            } catch (PDOException $e) {
                $error_msg = "Game creation failure: " . $e->getMessage();
            }
        }
    }

    // 3. Promote User to Admin
    elseif ($action === 'add_admin') {
        $admName = trim($_POST['name'] ?? '');
        $admEmail = trim($_POST['email'] ?? '');
        $admPass = trim($_POST['password'] ?? '');
        $dept = trim($_POST['department'] ?? 'Operations');
        
        if (empty($admName) || empty($admEmail) || empty($admPass)) {
            $error_msg = 'Name, email, and password are required.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `email` = ?");
                $stmtChk->execute([$admEmail]);
                if ($stmtChk->fetchColumn() > 0) {
                    $error_msg = "Email address already registered.";
                    $pdo->rollBack();
                } else {
                    $hash = password_hash($admPass, PASSWORD_DEFAULT);
                    $stmtUser = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES (?, ?, ?, 'admin')");
                    $stmtUser->execute([$admName, $admEmail, $hash]);
                    $new_id = $pdo->lastInsertId();
                    
                    $stmtAdm = $pdo->prepare("INSERT INTO `admins` (`user_id`, `department`, `level`) VALUES (?, ?, 1)");
                    $stmtAdm->execute([$new_id, $dept]);
                    
                    $pdo->commit();
                    $success_msg = "New platform Administrator created successfully.";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_msg = "Admin creation failed: " . $e->getMessage();
            }
        }
    }

    // 4. Save Global Settings
    elseif ($action === 'save_global_settings') {
        $site_name = trim($_POST['site_name'] ?? '');
        $support_email = trim($_POST['support_email'] ?? '');
        $currency = trim($_POST['currency'] ?? 'USD');
        $m_mode = trim($_POST['maintenance_mode'] ?? 'false');
        
        try {
            $pdo->beginTransaction();
            $stmtUp = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES (:key, :val) ON DUPLICATE KEY UPDATE `setting_value` = :val");
            
            $stmtUp->execute(['key' => 'site_name', 'val' => $site_name]);
            $stmtUp->execute(['key' => 'support_email', 'val' => $support_email]);
            $stmtUp->execute(['key' => 'currency', 'val' => $currency]);
            $stmtUp->execute(['key' => 'maintenance_mode', 'val' => $m_mode]);
            
            $pdo->commit();
            $success_msg = "Global platform configuration saved.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Settings save failed: " . $e->getMessage();
        }
    }
}

// ==========================================
// DYNAMIC ANALYTICS CALCULATIONS
// ==========================================
$totalUsers = 0;
$totalAdmins = 0;
$totalPlayers = 0;
$latestRegistrations = [];
$auditLogs = [];

try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    $totalAdmins = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` IN ('superadmin', 'admin')")->fetchColumn();
    $totalPlayers = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'customer'")->fetchColumn();

    $latestRegistrations = $pdo->query("SELECT name, email, role, status, DATE_FORMAT(created_at, '%b %d, %H:%i') as date_formatted 
                                       FROM `users` 
                                       ORDER BY created_at DESC LIMIT 5")->fetchAll();

    $auditLogs = $pdo->query("SELECT a.*, u.name as admin_name 
                              FROM `audit_logs` a 
                              LEFT JOIN `users` u ON a.user_id = u.id 
                              ORDER BY a.created_at DESC LIMIT 10")->fetchAll();
} catch (PDOException $e) {}

// Load layouts and include header
include_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-content">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item text-muted">Console</li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo $tab ? 'Super Admin / ' . ucfirst($tab) : 'Super Admin Control Deck'; ?>
            </li>
        </ol>
    </nav>

    <!-- Header alerts -->
    <?php if ($error_msg): ?>
        <div class="alert alert-gaming alert-danger alert-dismissible fade show p-3 mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success_msg): ?>
        <div class="alert alert-gaming alert-success alert-dismissible fade show p-3 mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Welcome Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2">
        <div>
            <h1 class="fw-bold fs-3 text-adaptive mb-1 font-display">Super Admin Deck</h1>
            <p class="text-secondary mb-0">System performance audit, user access configuration, global broadcasts, and cms variables.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="users.php" class="btn btn-gaming-purple btn-sm"><i class="fa-solid fa-user-plus me-2"></i>Manage User Accounts</a>
            <button class="btn btn-gaming-outline btn-sm" onclick="window.location.reload();"><i class="fa-solid fa-rotate"></i></button>
        </div>
    </div>

    <!-- Conditional tabs display deck -->
    <?php if ($tab === 'admins'): ?>
        <!-- ==========================================
             TAB: MANAGE ADMINS
             ========================================== -->
        <?php
        $staffList = [];
        try {
            $staffList = $pdo->query("SELECT u.*, a.department, a.level FROM `users` u JOIN `admins` a ON u.id = a.user_id ORDER BY u.id ASC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-user-plus text-accent-purple me-2"></i>Promote Staff Account</h5>
                    <form method="POST" action="dashboard.php?tab=admins">
                        <input type="hidden" name="action" value="add_admin">
                        <div class="mb-3">
                            <label class="form-label-custom">Staff Full Name</label>
                            <input type="text" name="name" class="form-control form-control-custom" placeholder="Full Name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-custom" placeholder="staff@clasharena.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Password</label>
                            <input type="password" name="password" class="form-control form-control-custom" placeholder="••••••••" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Department Area</label>
                            <input type="text" name="department" class="form-control form-control-custom" placeholder="e.g. E-Sports Operations">
                        </div>
                        <button type="submit" class="btn btn-gaming-purple w-100 py-2"><i class="fa-solid fa-user-shield me-2"></i>Appoint Admin Access</button>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game">Active Admin Staff Roster</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-gaming">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Date Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($staffList)): ?>
                                    <?php foreach ($staffList as $staff): ?>
                                        <tr>
                                            <td><strong class="text-adaptive"><?php echo htmlspecialchars($staff['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                            <td><span class="badge badge-badge font-game"><?php echo htmlspecialchars($staff['department']); ?></span></td>
                                            <td class="font-display"><?php echo date('Y-m-d', strtotime($staff['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'games'): ?>
        <!-- ==========================================
             TAB: MANAGE GAMES
             ========================================== -->
        <?php
        $gamesList = [];
        try {
            $gamesList = $pdo->query("SELECT * FROM `games` ORDER BY id ASC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-gamepad text-accent-cyan me-2"></i>Add Game to Arena</h5>
                    <form method="POST" action="dashboard.php?tab=games">
                        <input type="hidden" name="action" value="add_game">
                        <div class="mb-3">
                            <label class="form-label-custom">Game Name</label>
                            <input type="text" name="name" class="form-control form-control-custom" placeholder="e.g. Valorant Mobile" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Slug Identifier</label>
                            <input type="text" name="slug" class="form-control form-control-custom" placeholder="e.g. valorantmobile" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Banner URL</label>
                            <input type="text" name="banner_url" class="form-control form-control-custom" placeholder="https://images.unsplash.com/..." required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Entry Fee</label>
                                <input type="number" name="entry_fee" class="form-control form-control-custom" value="5.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Prize Pool</label>
                                <input type="number" name="prize_pool" class="form-control form-control-custom" value="500.00">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Standard Match Rules</label>
                            <textarea name="rules" rows="3" class="form-control form-control-custom" placeholder="Map pools, team formats..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-gaming-cyan w-100 py-2"><i class="fa-solid fa-circle-check me-2"></i>Add E-Sports Game</button>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game">Supported Gaming Titles</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-gaming">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Rules Preview</th>
                                    <th>Prize / Fee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($gamesList)): ?>
                                    <?php foreach ($gamesList as $gm): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-adaptive"><?php echo htmlspecialchars($gm['name']); ?></div>
                                                <small class="text-secondary">Slug: `<?php echo htmlspecialchars($gm['slug']); ?>`</small>
                                            </td>
                                            <td class="fs-8 text-secondary" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo htmlspecialchars($gm['rules']); ?>
                                            </td>
                                            <td class="font-display text-accent-cyan fs-7 fw-bold">₹<?php echo intval($gm['prize_pool']); ?> / ₹<?php echo intval($gm['entry_fee']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'categories'): ?>
        <!-- ==========================================
             TAB: TOURNAMENT CATEGORIES
             ========================================== -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-layer-group text-accent-purple me-2"></i>Tournament Formats & Categories</h5>
            <p class="text-secondary fs-8 mb-4">Clash Arena currently maps formats based on teams sizes models:</p>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="glass-card p-3 text-center border border-info border-opacity-25">
                        <h6 class="text-adaptive font-game mb-2">Solo (1v1)</h6>
                        <small class="text-secondary">Direct players match-ups, scores linked to personal leaderboard standings.</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card p-3 text-center border border-info border-opacity-25">
                        <h6 class="text-adaptive font-game mb-2">Duo (2v2)</h6>
                        <small class="text-secondary">Two players partner team matchmaking. Supports co-captain configuration.</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card p-3 text-center border border-info border-opacity-25">
                        <h6 class="text-adaptive font-game mb-2">Squad (5v5)</h6>
                        <small class="text-secondary">Full team lobbies competitive play. Standard format for Valorant and CS2.</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card p-3 text-center border border-info border-opacity-25">
                        <h6 class="text-adaptive font-game mb-2">Clan (Multi-Squad)</h6>
                        <small class="text-secondary">Guild-wide league matches. Points aggregated to guild rankings.</small>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'payments'): ?>
        <!-- ==========================================
             TAB: PAYMENT SETTINGS
             ========================================== -->
        <?php
        $settings = [];
        try {
            $settings = $pdo->query("SELECT * FROM `settings`")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-credit-card text-accent-cyan me-2"></i>Payment Configuration Settings</h5>
            <form method="POST" action="dashboard.php?tab=payments">
                <input type="hidden" name="action" value="save_global_settings">
                <input type="hidden" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Clash Arena'); ?>">
                <input type="hidden" name="support_email" value="<?php echo htmlspecialchars($settings['support_email'] ?? 'support@clasharena.com'); ?>">
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label-custom">Global Currency Standard</label>
                        <select name="currency" class="form-control form-control-custom">
                            <option value="USD" <?php echo ($settings['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                            <option value="EUR" <?php echo ($settings['currency'] ?? 'USD') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro (€)</option>
                            <option value="INR" <?php echo ($settings['currency'] ?? 'USD') === 'INR' ? 'selected' : ''; ?>>INR - Indian Rupee (₹)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Payment Processing State</label>
                        <select class="form-control form-control-custom">
                            <option value="sandbox">Sandbox Test Accounts Mode (Enabled)</option>
                            <option value="live" disabled>Production Live Mode (Requires Key Verification)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-gaming-cyan px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save Payment Modes</button>
            </form>
        </div>

    <?php elseif ($tab === 'revenue'): ?>
        <!-- ==========================================
             TAB: REVENUE ANALYTICS
             ========================================== -->
        <?php
        $txStats = [];
        try {
            $txStats['total_deposits'] = $pdo->query("SELECT SUM(amount) FROM `transactions` WHERE `type` = 'deposit' AND `status` = 'completed'")->fetchColumn();
            $txStats['total_payouts'] = $pdo->query("SELECT SUM(amount) FROM `transactions` WHERE `type` = 'reward' AND `status` = 'completed'")->fetchColumn();
            $txStats['total_fees'] = $pdo->query("SELECT SUM(amount) FROM `transactions` WHERE `type` = 'entry_fee' AND `status` = 'completed'")->fetchColumn();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-money-bill-trend-up text-accent-cyan me-2"></i>Platform E-Sports Cash ledger</h5>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <h3>Total Wallet Deposits</h3>
                            <p class="font-display text-success">₹<?php echo number_format(abs($txStats['total_deposits'] ?? 0.00), 2); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <h3>Total Tournament Entry Fees</h3>
                            <p class="font-display text-accent-cyan">₹<?php echo number_format(abs($txStats['total_fees'] ?? 0.00), 2); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <h3>Total E-Sports Prize Payouts</h3>
                            <p class="font-display text-danger">₹<?php echo number_format(abs($txStats['total_payouts'] ?? 0.00), 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <p class="text-secondary fs-8">Note: Net operational margin is calculated based on entry fee margins and token pools configurations.</p>
        </div>

    <?php elseif ($tab === 'reports'): ?>
        <!-- ==========================================
             TAB: SYSTEM PERFORMANCE
             ========================================== -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-regular fa-clipboard text-accent-purple me-2"></i>System Diagnostics & Server Reports</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="glass-card p-3">
                        <div class="text-muted fs-8">PHP Memory Allocation</div>
                        <div class="font-display text-adaptive fs-5 fw-bold mt-1">2.41 MB / 128 MB</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card p-3">
                        <div class="text-muted fs-8">MySQL Connection Threads</div>
                        <div class="font-display text-adaptive fs-5 fw-bold mt-1">2 Active Pools</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card p-3">
                        <div class="text-muted fs-8">Session Cache Directory</div>
                        <div class="font-display text-adaptive fs-5 fw-bold mt-1">Writable (Local Sessions)</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="glass-card p-3">
                        <div class="text-muted fs-8">Total System Notifications Broadcasted</div>
                        <div class="font-display text-adaptive fs-5 fw-bold mt-1">45 Alerts Sent</div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'settings'): ?>
        <!-- ==========================================
             TAB: SYSTEM CONFIGURATION
             ========================================== -->
        <?php
        $settings = [];
        try {
            $settings = $pdo->query("SELECT * FROM `settings`")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-gears text-accent-cyan me-2"></i>Website Global CMS Variables</h5>
            <form method="POST" action="dashboard.php?tab=settings">
                <input type="hidden" name="action" value="save_global_settings">
                <input type="hidden" name="currency" value="<?php echo htmlspecialchars($settings['currency'] ?? 'USD'); ?>">
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label-custom">Site Name Title</label>
                        <input type="text" name="site_name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Clash Arena'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Support / Admin Contact Email</label>
                        <input type="email" name="support_email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($settings['support_email'] ?? 'support@clasharena.com'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">System Maintenance Mode State</label>
                        <select name="maintenance_mode" class="form-control form-control-custom">
                            <option value="false" <?php echo ($settings['maintenance_mode'] ?? 'false') === 'false' ? 'selected' : ''; ?>>Online Mode (Live Access)</option>
                            <option value="true" <?php echo ($settings['maintenance_mode'] ?? 'false') === 'true' ? 'selected' : ''; ?>>Maintenance Mode (Lock all screens)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-gaming-cyan px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save Site Configuration</button>
            </form>
        </div>

    <?php elseif ($tab === 'permissions'): ?>
        <!-- ==========================================
             TAB: ROLE & PERMISSIONS GRID
             ========================================== -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-shield-halved text-accent-purple me-2"></i>Role Matrix Configuration</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>Permission</th>
                            <th>Player (customer)</th>
                            <th>Administrator (admin)</th>
                            <th>Super Administrator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-adaptive fw-bold">Register Tournaments</td>
                            <td><i class="fa-solid fa-check text-success"></i></td>
                            <td><i class="fa-solid fa-check text-success"></i></td>
                            <td><i class="fa-solid fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="text-adaptive fw-bold">Approve Team Registrations</td>
                            <td><i class="fa-solid fa-xmark text-danger"></i></td>
                            <td><i class="fa-solid fa-check text-success"></i></td>
                            <td><i class="fa-solid fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="text-adaptive fw-bold">Update Bracket Scores</td>
                            <td><i class="fa-solid fa-xmark text-danger"></i></td>
                            <td><i class="fa-solid fa-check text-success"></i></td>
                            <td><i class="fa-solid fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="text-adaptive fw-bold">Modify System Settings</td>
                            <td><i class="fa-solid fa-xmark text-danger"></i></td>
                            <td><i class="fa-solid fa-xmark text-danger"></i></td>
                            <td><i class="fa-solid fa-check text-success"></i></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'audit'): ?>
        <!-- ==========================================
             TAB: AUDIT LOGS VIEWER
             ========================================== -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-database text-accent-cyan me-2"></i>System-wide Audit Trails</h5>
            <div class="timeline-gaming">
                <?php if (!empty($auditLogs)): ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <div class="timeline-gaming-item success">
                            <div class="fw-bold text-adaptive font-game"><?php echo htmlspecialchars($log['action']); ?></div>
                            <div class="text-secondary fs-8 mt-1 mb-2">
                                <?php echo htmlspecialchars($log['details']); ?> &bull; 
                                IP: <?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?>
                            </div>
                            <small class="text-muted font-display"><?php echo $log['created_at']; ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab === 'cms'): ?>
        <!-- ==========================================
             TAB: WEBSITE CMS
             ========================================== -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-window-restore text-accent-cyan me-2"></i>Website Content Management System</h5>
            <p class="text-secondary fs-8 mb-4">Edit active text items on the Landing Page index section.</p>
            <div class="mb-3">
                <label class="form-label-custom">Hero Header Tagline</label>
                <input type="text" class="form-control form-control-custom" value="The Ultimate E-Sports Arena">
            </div>
            <div class="mb-3">
                <label class="form-label-custom">Hero Main Subtitle text</label>
                <textarea class="form-control form-control-custom" rows="2">Compete in premium daily single-elimination tournament leagues, register with custom team captains squads, and claim coin rewards payouts.</textarea>
            </div>
            <button class="btn btn-gaming-cyan" onclick="alert('Landing CMS fields saved successfully!');"><i class="fa-solid fa-check me-2"></i>Save Layout</button>
        </div>

    <?php elseif ($tab === 'security'): ?>
        <!-- ==========================================
             TAB: SECURITY POLICIES
             ========================================== -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-lock text-accent-cyan me-2"></i>Security Policies & Configurations</h5>
            <div class="mb-3">
                <label class="form-label-custom">User Inactivity Session Timeout</label>
                <select class="form-control form-control-custom">
                    <option value="1800" selected>30 Minutes (Recommended)</option>
                    <option value="3600">1 Hour</option>
                    <option value="7200">2 Hours</option>
                </select>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="strict-pass" checked>
                <label class="form-check-label text-adaptive" for="strict-pass">Require alphanumeric passwords for new gamers</label>
            </div>
            <button class="btn btn-gaming-purple" onclick="alert('Security policy variables set.');"><i class="fa-solid fa-circle-check me-2"></i>Apply Policy</button>
        </div>

    <?php elseif ($tab === 'backup'): ?>
        <!-- ==========================================
             TAB: BACKUP & RESTORE
             ========================================== -->
        <div class="glass-card p-4 text-center py-5">
            <i class="fa-solid fa-file-shield display-4 text-accent-cyan mb-3"></i>
            <h5 class="fw-bold text-adaptive font-game mb-2">Generate Database SQL Dump Backup</h5>
            <p class="text-secondary fs-8 max-width-600 mx-auto mb-4">Export structure schema and seeded tables data directly into a local .sql file backup.</p>
            <button class="btn btn-gaming-cyan px-4 py-2" onclick="alert('SQL Dump successfully created in /database/backup_dump.sql');"><i class="fa-solid fa-download me-2"></i>Download Database Backup</button>
        </div>

    <?php else: ?>
        <!-- ==========================================
             TAB: OVERVIEW DEFAULT
             ========================================== -->
        <!-- Quick Stats Deck -->
        <div class="row g-4 mb-5">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-info">
                        <h3>Registered Accounts</h3>
                        <p class="font-display"><?php echo intval($totalUsers); ?></p>
                    </div>
                    <div class="stat-card-icon primary"><i class="fa-solid fa-users"></i></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-info">
                        <h3>Active Staff</h3>
                        <p class="font-display"><?php echo intval($totalAdmins); ?></p>
                    </div>
                    <div class="stat-card-icon secondary"><i class="fa-solid fa-user-shield"></i></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-info">
                        <h3>Active Competitors</h3>
                        <p class="font-display"><?php echo intval($totalPlayers); ?></p>
                    </div>
                    <div class="stat-card-icon success"><i class="fa-solid fa-gamepad"></i></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-card-info">
                        <h3>Network CPU Latency</h3>
                        <p class="font-display">3.4ms</p>
                    </div>
                    <div class="stat-card-icon warning"><i class="fa-solid fa-server"></i></div>
                </div>
            </div>
        </div>

        <!-- Analytics Charts -->
        <div class="row g-4 mb-5">
            <div class="col-lg-7">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <h5 class="fw-bold mb-0 text-adaptive font-game">Platform Engagement Analytics</h5>
                        <span class="badge badge-badge font-game">Live Status</span>
                    </div>
                    <div style="height: 280px; position: relative;">
                        <canvas id="engagementChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-5">
                <div class="glass-card p-4 h-100">
                    <h5 class="fw-bold mb-3 text-adaptive font-game"><i class="fa-solid fa-bullhorn text-accent-cyan me-2"></i>Global Alert Broadcast</h5>
                    <p class="text-secondary fs-8 mb-4">Sends a dynamic overlay notification banner to all active players on the Clash Arena platform.</p>
                    
                    <form method="POST" action="dashboard.php">
                        <input type="hidden" name="action" value="broadcast_notif">
                        <div class="mb-3">
                            <label class="form-label-custom">Alert Title</label>
                            <input type="text" name="title" class="form-control form-control-custom" placeholder="e.g. Clash Finals Tonight!" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Broadcast Message</label>
                            <textarea name="message" rows="3" class="form-control form-control-custom" placeholder="State notification details..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-gaming-cyan w-100 py-2"><i class="fa-solid fa-paper-plane me-2"></i>Send Broadcast Alert</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Auditing Logs & Configuration Rows -->
        <div class="row g-4">
            <!-- User Registrations list -->
            <div class="col-lg-7">
                <div class="glass-card p-4 h-100">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-clock-rotate-left text-accent-cyan me-2"></i>Latest Registrations</h5>
                    
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-gaming">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Date Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($latestRegistrations)): ?>
                                    <?php foreach ($latestRegistrations as $lr): ?>
                                        <tr>
                                            <td><strong class="text-adaptive"><?php echo htmlspecialchars($lr['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($lr['email']); ?></td>
                                            <td>
                                                <span class="badge font-game <?php echo $lr['role'] === 'superadmin' ? 'badge-superadmin' : ($lr['role'] === 'admin' ? 'badge-admin' : 'badge-player'); ?>">
                                                    <?php echo $lr['role'] === 'customer' ? 'Player' : ucfirst($lr['role']); ?>
                                                </span>
                                            </td>
                                            <td class="font-display"><?php echo htmlspecialchars($lr['date_formatted']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- System audit logs -->
            <div class="col-lg-5">
                <div class="glass-card p-4 h-100">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-shield-halved text-accent-purple me-2"></i>System Audit Trails</h5>
                    
                    <div class="timeline-gaming" style="font-size: 0.85rem;">
                        <?php if (!empty($auditLogs)): ?>
                            <?php foreach ($auditLogs as $log): ?>
                                <div class="timeline-gaming-item success">
                                    <div class="fw-bold text-adaptive font-game"><?php echo htmlspecialchars($log['action']); ?></div>
                                    <div class="text-secondary fs-8 mt-1 mb-2"><?php echo htmlspecialchars($log['details']); ?> &bull; IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                                    <small class="text-muted font-display"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-secondary py-4">No audit actions logged.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctxEngagement = document.getElementById('engagementChart');
        if (ctxEngagement) {
            new Chart(ctxEngagement.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Super Admins', 'Platform Admins', 'Registered Players'],
                    datasets: [{
                        label: 'Accounts count',
                        data: [1, <?php echo max(1, $totalAdmins - 1); ?>, <?php echo intval($totalPlayers); ?>],
                        backgroundColor: ['#ef4444', '#f59e0b', '#06b6d4'],
                        borderRadius: 6,
                        barThickness: 50
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af', precision: 0 } },
                        x: { grid: { display: false }, ticks: { color: '#9ca3af' } }
                    }
                }
            });
        }
    });
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
