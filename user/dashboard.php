<?php
// user/dashboard.php
// Premium Player (User) Dashboard Workspace

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Verify role access (maps customer role to Player)
checkRole(['customer']);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

$error_msg = '';
$success_msg = '';
$tab = $_GET['tab'] ?? '';

// ==========================================
// POST ACTION HANDLERS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Create a Team
    if ($action === 'create_team') {
        $team_name = isset($_POST['team_name']) ? trim($_POST['team_name']) : '';
        if (empty($team_name)) {
            $error_msg = 'Please specify a unique team name.';
        } else {
            try {
                // Check if name exists
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `teams` WHERE `name` = :name");
                $stmtCheck->execute(['name' => $team_name]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $error_msg = 'A team with this name already exists.';
                } else {
                    $pdo->beginTransaction();

                    // Insert Team
                    $stmtTeam = $pdo->prepare("INSERT INTO `teams` (`name`, `captain_id`) VALUES (:name, :cap)");
                    $stmtTeam->execute(['name' => $team_name, 'cap' => $user_id]);
                    $team_id = $pdo->lastInsertId();

                    // Insert Team Member
                    $stmtMember = $pdo->prepare("INSERT INTO `team_members` (`team_id`, `user_id`, `role`) VALUES (:tid, :uid, 'captain')");
                    $stmtMember->execute(['tid' => $team_id, 'uid' => $user_id]);

                    // Add Notification
                    $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES (:uid, 'Team Created', :msg, 'success')");
                    $stmtNotif->execute(['uid' => $user_id, 'msg' => "Team \"$team_name\" has been successfully formed under your captaincy."]);

                    $pdo->commit();
                    $success_msg = "Team \"$team_name\" created successfully!";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = 'Failed to create team: ' . $e->getMessage();
            }
        }
    }

    // 2. Deposit Balance (Fake Wallet top-up)
    elseif ($action === 'deposit') {
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;
        if ($amount <= 0) {
            $error_msg = 'Please enter a valid deposit amount.';
        } else {
            try {
                $pdo->beginTransaction();

                // Get wallet id
                $stmtW = $pdo->prepare("SELECT id FROM `wallets` WHERE `user_id` = :uid");
                $stmtW->execute(['uid' => $user_id]);
                $wallet_id = $stmtW->fetchColumn();

                if (!$wallet_id) {
                    // Create wallet if it does not exist
                    $stmtCreateW = $pdo->prepare("INSERT INTO `wallets` (`user_id`, `balance`, `coins`) VALUES (:uid, 0.00, 0)");
                    $stmtCreateW->execute(['uid' => $user_id]);
                    $wallet_id = $pdo->lastInsertId();
                }

                // Update wallet balance
                $stmtUpdateW = $pdo->prepare("UPDATE `wallets` SET `balance` = `balance` + :amount WHERE `id` = :wid");
                $stmtUpdateW->execute(['amount' => $amount, 'wid' => $wallet_id]);

                // Record transaction
                $stmtTx = $pdo->prepare("INSERT INTO `transactions` (`wallet_id`, `amount`, `type`, `status`, `description`) VALUES (:wid, :amount, 'deposit', 'completed', 'Mock credit card payment deposit')");
                $stmtTx->execute(['wid' => $wallet_id, 'amount' => $amount]);

                // Notification
                $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES (:uid, 'Funds Credited', :msg, 'success')");
                $stmtNotif->execute(['uid' => $user_id, 'msg' => "Successfully credited ₹$amount to your wallet balance."]);

                $pdo->commit();
                $success_msg = "Successfully loaded ₹$amount to your wallet!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = 'Deposit failure: ' . $e->getMessage();
            }
        }
    }

    // 3. Register for a Tournament (Solo or Team)
    elseif ($action === 'register_tournament') {
        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        $team_id = isset($_POST['team_id']) && $_POST['team_id'] !== '' ? intval($_POST['team_id']) : null;
        $reg_type = isset($_POST['reg_type']) ? $_POST['reg_type'] : 'solo';

        try {
            $pdo->beginTransaction();

            // Fetch tournament details
            $stmtTour = $pdo->prepare("SELECT t.*, g.name as game_name FROM `tournaments` t JOIN `games` g ON t.game_id = g.id WHERE t.id = :tid");
            $stmtTour->execute(['tid' => $tournament_id]);
            $tour = $stmtTour->fetch();

            if (!$tour) {
                throw new Exception('Selected tournament was not found.');
            }

            if ($tour['status'] !== 'registration_open') {
                throw new Exception('Tournament registrations are currently closed.');
            }

            // Check if already registered
            if ($reg_type === 'team' && $team_id) {
                $stmtCheckReg = $pdo->prepare("SELECT COUNT(*) FROM `tournament_registrations` WHERE `tournament_id` = :tid AND `team_id` = :team_id");
                $stmtCheckReg->execute(['tid' => $tournament_id, 'team_id' => $team_id]);
            } else {
                $stmtCheckReg = $pdo->prepare("SELECT COUNT(*) FROM `tournament_registrations` WHERE `tournament_id` = :tid AND `user_id` = :uid AND `registration_type` = 'solo'");
                $stmtCheckReg->execute(['tid' => $tournament_id, 'uid' => $user_id]);
            }

            if ($stmtCheckReg->fetchColumn() > 0) {
                throw new Exception('You (or your team) are already registered for this tournament.');
            }

            // Fetch User Wallet details
            $stmtWallet = $pdo->prepare("SELECT * FROM `wallets` WHERE `user_id` = :uid");
            $stmtWallet->execute(['uid' => $user_id]);
            $wallet = $stmtWallet->fetch();

            if (!$wallet) {
                // Initialize missing wallet on the fly
                $stmtCreateW = $pdo->prepare("INSERT INTO `wallets` (`user_id`, `balance`, `coins`) VALUES (:uid, 100.00, 200)");
                $stmtCreateW->execute(['uid' => $user_id]);

                // Re-fetch
                $stmtWallet->execute(['uid' => $user_id]);
                $wallet = $stmtWallet->fetch();
            }

            if ($wallet['balance'] < $tour['entry_fee']) {
                throw new Exception('Insufficient funds in your wallet to cover the entry fee.');
            }

            // Deduct entry fee if any
            if ($tour['entry_fee'] > 0) {
                $newBalance = $wallet['balance'] - $tour['entry_fee'];
                $stmtDeduct = $pdo->prepare("UPDATE `wallets` SET `balance` = :bal WHERE `id` = :wid");
                $stmtDeduct->execute(['bal' => $newBalance, 'wid' => $wallet['id']]);

                // Create transaction
                $stmtTx = $pdo->prepare("INSERT INTO `transactions` (`wallet_id`, `amount`, `type`, `status`, `description`) VALUES (:wid, :amount, 'entry_fee', 'completed', :desc)");
                $stmtTx->execute([
                    'wid' => $wallet['id'],
                    'amount' => -$tour['entry_fee'],
                    'desc' => "Entry fee deduction for " . $tour['name']
                ]);
            }

            // Create Registration (automatically mark as approved for solo, or pending for team)
            $reg_status = ($reg_type === 'solo') ? 'approved' : 'pending';
            $stmtReg = $pdo->prepare("INSERT INTO `tournament_registrations` (`tournament_id`, `user_id`, `team_id`, `registration_type`, `status`) VALUES (:tid, :uid, :team_id, :type, :status)");
            $stmtReg->execute([
                'tid' => $tournament_id,
                'uid' => $user_id,
                'team_id' => $team_id,
                'type' => $reg_type,
                'status' => $reg_status
            ]);

            // Notification
            $status_msg = ($reg_status === 'approved') ? 'approved and registered!' : 'submitted and is pending admin approval.';
            $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES (:uid, 'Tournament Registration', :msg, 'success')");
            $stmtNotif->execute([
                'uid' => $user_id,
                'msg' => "Your registration request for \"{$tour['name']}\" has been $status_msg"
            ]);

            $pdo->commit();
            $success_msg = "Successfully registered for the tournament!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = $e->getMessage();
        }
    }
}

// ==========================================
// DYNAMIC DATA QUERY FETCH
// ==========================================

// 1. Stats Counter values
$activeTournamentsCount = 0;
$totalWinsCount = 0;
$currentPlayerRank = 'Unranked';
$walletCoins = 0;
$walletBalance = 0.00;

try {
    // Active tournaments user has registered for
    $stmtActT = $pdo->prepare("SELECT COUNT(DISTINCT tr.tournament_id) 
                               FROM `tournament_registrations` tr 
                               JOIN `tournaments` t ON tr.tournament_id = t.id 
                               WHERE tr.user_id = :uid AND t.status IN ('live', 'registration_open')");
    $stmtActT->execute(['uid' => $user_id]);
    $activeTournamentsCount = $stmtActT->fetchColumn();

    // Wins count
    $stmtWins = $pdo->prepare("SELECT COUNT(*) FROM `tournaments` WHERE `winner_id` = :uid");
    $stmtWins->execute(['uid' => $user_id]);
    $totalWinsCount = $stmtWins->fetchColumn();

    // Get current rank details (based on leaderboard points for primary game, e.g. Valorant)
    $stmtRank = $pdo->prepare("SELECT badge FROM `leaderboard` WHERE `user_id` = :uid LIMIT 1");
    $stmtRank->execute(['uid' => $user_id]);
    $rankBadge = $stmtRank->fetchColumn();
    if ($rankBadge) {
        $currentPlayerRank = $rankBadge;
    }

    // Get wallet contents
    $stmtWall = $pdo->prepare("SELECT balance, coins FROM `wallets` WHERE `user_id` = :uid");
    $stmtWall->execute(['uid' => $user_id]);
    $wall = $stmtWall->fetch();
    if ($wall) {
        $walletBalance = $wall['balance'];
        $walletCoins = $wall['coins'];
    }
} catch (PDOException $e) {
    // Fallback silent
}

// Load dynamic structures based on active tab
include_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-content">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item text-muted">Player console</li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo $tab ? ucfirst(htmlspecialchars($tab)) : 'Overview'; ?>
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

    <!-- Welcome Widget -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2">
        <div>
            <h1 class="fw-bold fs-3 text-adaptive mb-1 font-display">Clash Player Console</h1>
            <p class="text-secondary mb-0">Roster management, match schedule grids, wallet deposits, and tournament grids.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="dashboard.php?tab=tournaments" class="btn btn-gaming-cyan btn-sm"><i class="fa-solid fa-crosshairs me-2"></i>Browse Tournaments</a>
            <button class="btn btn-gaming-outline btn-sm" onclick="window.location.reload();"><i class="fa-solid fa-rotate"></i></button>
        </div>
    </div>

    <!-- Overview Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-info">
                    <h3>Active Games</h3>
                    <p class="font-display"><?php echo intval($activeTournamentsCount); ?></p>
                </div>
                <div class="stat-card-icon primary"><i class="fa-solid fa-trophy"></i></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-info">
                    <h3>Total Victories</h3>
                    <p class="font-display"><?php echo intval($totalWinsCount); ?></p>
                </div>
                <div class="stat-card-icon success"><i class="fa-solid fa-award"></i></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-info">
                    <h3>Current Rank Badge</h3>
                    <p class="font-display" style="font-size: 1.25rem; font-weight: 900; margin-top: 10px;"><?php echo htmlspecialchars($currentPlayerRank); ?></p>
                </div>
                <div class="stat-card-icon warning"><i class="fa-solid fa-shield-halved"></i></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-info">
                    <h3>My Wallet Coins</h3>
                    <p class="font-display"><?php echo number_format($walletCoins); ?></p>
                </div>
                <div class="stat-card-icon secondary"><i class="fa-solid fa-coins"></i></div>
            </div>
        </div>
    </div>

    <!-- TAB ROUTING BLOCKS -->
    <?php if ($tab === 'tournaments'): ?>
        <!-- ==========================================
             TAB: TOURNAMENTS
             ========================================== -->
        <?php
        // Fetch all active, upcoming, live, and registered tournaments
        $allTournaments = [];
        try {
            $stmtAllT = $pdo->query("SELECT t.*, g.name as game_name, g.rules as game_rules FROM `tournaments` t JOIN `games` g ON t.game_id = g.id ORDER BY t.start_date ASC");
            $allTournaments = $stmtAllT->fetchAll();
        } catch (PDOException $e) {}

        // Fetch User's Captained Teams
        $userTeams = [];
        try {
            $stmtUT = $pdo->prepare("SELECT * FROM `teams` WHERE `captain_id` = :uid");
            $stmtUT->execute(['uid' => $user_id]);
            $userTeams = $stmtUT->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-gamepad text-accent-cyan me-2"></i>Available Tournaments</h5>
            
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>Tournament</th>
                            <th>Game</th>
                            <th>Mode</th>
                            <th>Prize Pool</th>
                            <th>Entry Fee</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allTournaments)): ?>
                            <?php foreach ($allTournaments as $tour): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-adaptive"><?php echo htmlspecialchars($tour['name']); ?></div>
                                        <small class="text-secondary">Starts: <?php echo date('M d, Y H:i', strtotime($tour['start_date'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($tour['game_name']); ?></td>
                                    <td><span class="badge badge-badge"><?php echo htmlspecialchars($tour['type']); ?></span></td>
                                    <td class="text-warning fw-bold font-display">₹<?php echo number_format($tour['prize_pool'], 2); ?></td>
                                    <td class="text-accent-cyan font-display"><?php echo $tour['entry_fee'] > 0 ? '₹' . number_format($tour['entry_fee'], 2) : 'FREE'; ?></td>
                                    <td>
                                         <?php
                                         $statusClass = 'badge-superadmin';
                                         $statusText = str_replace('_', ' ', $tour['status']);
                                         if ($tour['status'] === 'live') {
                                             $statusClass = 'badge-superadmin';
                                         } elseif ($tour['status'] === 'registration_open') {
                                             $statusClass = 'badge-completed';
                                         } else {
                                             $statusClass = 'badge-admin';
                                         }
                                         ?>
                                         <span class="badge <?php echo $statusClass; ?> font-game" style="text-transform: uppercase;">
                                             <?php echo htmlspecialchars($statusText); ?>
                                         </span>
                                     </td>
                                    <td class="text-end">
                                        <?php if ($tour['status'] === 'registration_open'): ?>
                                            <button class="btn btn-gaming-purple btn-sm py-1 px-3" data-bs-toggle="modal" data-bs-target="#registerModal<?php echo $tour['id']; ?>">
                                                Register
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm py-1 px-3" disabled>Closed</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>


                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">No leagues found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Registration Modals (Moved outside the table to prevent HTML rendering issues) -->
        <?php if (!empty($allTournaments)): ?>
            <?php foreach ($allTournaments as $tour): ?>
                <?php if ($tour['status'] === 'registration_open'): ?>
                    <div class="modal fade" id="registerModal<?php echo $tour['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content bg-secondary border-0" style="border: 1px solid var(--card-border) !important;">
                                <div class="modal-header border-bottom border-secondary border-opacity-25">
                                    <h5 class="modal-title text-adaptive font-game">Register - <?php echo htmlspecialchars($tour['name']); ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="dashboard.php?tab=tournaments">
                                    <input type="hidden" name="action" value="register_tournament">
                                    <input type="hidden" name="tournament_id" value="<?php echo $tour['id']; ?>">
                                    
                                    <div class="modal-body">
                                        <div class="mb-3 text-adaptive opacity-75" style="font-size: 0.9rem;">
                                            <div class="mb-2"><i class="fa-solid fa-circle-info text-accent-cyan me-1"></i> Entry Fee: <strong class="text-adaptive">₹<?php echo number_format($tour['entry_fee'], 2); ?></strong></div>
                                            <div>Ensure your wallet coins cover this entry fee amount. Solo matches automatically register your single roster profile.</div>
                                        </div>

                                        <?php if ($tour['type'] !== 'solo'): ?>
                                            <div class="mb-3">
                                                <label class="form-label-custom">Registration Type</label>
                                                <select class="form-control form-control-custom" name="reg_type" id="regTypeSelect<?php echo $tour['id']; ?>" onchange="toggleTeamDropdown(this, <?php echo $tour['id']; ?>)">
                                                    <option value="solo">Register Solo Roster</option>
                                                    <option value="team">Register Squad Team</option>
                                                </select>
                                            </div>

                                            <div class="mb-3 d-none" id="teamSelectDiv<?php echo $tour['id']; ?>">
                                                <label class="form-label-custom">Select Your Team</label>
                                                <select class="form-control form-control-custom" name="team_id">
                                                    <?php if (!empty($userTeams)): ?>
                                                        <?php foreach ($userTeams as $team): ?>
                                                            <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <option value="">-- No Captained Teams Found --</option>
                                                    <?php endif; ?>
                                                </select>
                                                <small class="text-muted d-block mt-2">You can only register teams where you are the Captain. Build one in the "My Teams" tab.</small>
                                            </div>
                                        <?php else: ?>
                                            <input type="hidden" name="reg_type" value="solo">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="modal-footer border-top border-secondary border-opacity-25">
                                        <button type="button" class="btn btn-gaming-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-gaming-purple btn-sm">Confirm Registration</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <script>
            function toggleTeamDropdown(selectObj, tourId) {
                const div = document.getElementById('teamSelectDiv' + tourId);
                if (selectObj.value === 'team') {
                    div.classList.remove('d-none');
                } else {
                    div.classList.add('d-none');
                }
            }
        </script>

    <?php elseif ($tab === 'schedule'): ?>
        <!-- ==========================================
             TAB: MATCH SCHEDULE
             ========================================== -->
        <?php
        // Fetch scheduled and completed matches related to the player (where player is participant, or belongs to team)
        $matches = [];
        try {
            // Find player teams
            $stmtMyTeams = $pdo->prepare("SELECT team_id FROM `team_members` WHERE `user_id` = :uid");
            $stmtMyTeams->execute(['uid' => $user_id]);
            $myTeamIds = $stmtMyTeams->fetchAll(PDO::FETCH_COLUMN);
            $myTeamIdsStr = !empty($myTeamIds) ? implode(',', $myTeamIds) : '0';

            // Query matches involving user_id or user's teams
            $queryM = "SELECT m.*, t.name as tournament_name,
                              p1.name as p1_name, p2.name as p2_name,
                              t1.name as team1_name, t2.name as team2_name,
                              g.name as game_name
                       FROM `matches` m
                       JOIN `tournaments` t ON m.tournament_id = t.id
                       JOIN `games` g ON t.game_id = g.id
                       LEFT JOIN `users` p1 ON m.player1_id = p1.id
                       LEFT JOIN `users` p2 ON m.player2_id = p2.id
                       LEFT JOIN `teams` t1 ON m.team1_id = t1.id
                       LEFT JOIN `teams` t2 ON m.team2_id = t2.id
                       WHERE m.player1_id = :uid OR m.player2_id = :uid 
                          OR m.team1_id IN ($myTeamIdsStr) OR m.team2_id IN ($myTeamIdsStr)
                       ORDER BY m.scheduled_time ASC";
            
            $stmtM = $pdo->prepare($queryM);
            $stmtM->execute(['uid' => $user_id]);
            $matches = $stmtM->fetchAll();
        } catch (PDOException $e) {
            $error_msg = "Error loading schedule: " . $e->getMessage();
        }
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-calendar-days text-accent-cyan me-2"></i>My Scheduled Matches</h5>
            
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>Match details</th>
                            <th>Opponents</th>
                            <th>Time Scheduled</th>
                            <th>Scores</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($matches)): ?>
                            <?php foreach ($matches as $match): ?>
                                <?php 
                                // Determine opponent labels
                                $mode = $match['team1_id'] ? 'team' : 'solo';
                                $isP1 = ($mode === 'solo' && $match['player1_id'] == $user_id) || ($mode === 'team' && in_array($match['team1_id'], $myTeamIds));
                                
                                $my_label = $isP1 ? ($mode === 'solo' ? $match['p1_name'] : $match['team1_name']) : ($mode === 'solo' ? $match['p2_name'] : $match['team2_name']);
                                $opp_label = $isP1 ? ($mode === 'solo' ? $match['p2_name'] : $match['team2_name']) : ($mode === 'solo' ? $match['p1_name'] : $match['team1_name']);
                                
                                $my_score = $isP1 ? $match['score1'] : $match['score2'];
                                $opp_score = $isP1 ? $match['score2'] : $match['score1'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-adaptive"><?php echo htmlspecialchars($match['tournament_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($match['game_name']); ?> &bull; Rd <?php echo $match['round']; ?></small>
                                    </td>
                                    <td>
                                        <span class="text-accent-cyan fw-bold"><?php echo htmlspecialchars($my_label ?? 'My Team'); ?></span>
                                        <span class="text-secondary mx-2">vs</span>
                                        <span class="text-adaptive"><?php echo htmlspecialchars($opp_label ?? 'TBD Team'); ?></span>
                                    </td>
                                    <td class="font-display"><?php echo date('M d, Y H:i', strtotime($match['scheduled_time'])); ?></td>
                                    <td class="font-display">
                                        <?php if ($match['status'] === 'completed'): ?>
                                            <strong class="text-success"><?php echo $my_score; ?></strong> - <span class="text-danger"><?php echo $opp_score; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                         <?php
                                         $mStatusClass = 'badge-superadmin';
                                         if ($match['status'] === 'live') {
                                             $mStatusClass = 'badge-superadmin';
                                         } elseif ($match['status'] === 'completed') {
                                             $mStatusClass = 'badge-completed';
                                         } else {
                                             $mStatusClass = 'badge-admin';
                                         }
                                         ?>
                                         <span class="badge <?php echo $mStatusClass; ?> font-game" style="text-transform: uppercase;">
                                             <?php echo htmlspecialchars($match['status']); ?>
                                         </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">No match schedule grids mapped to your active rosters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'teams'): ?>
        <!-- ==========================================
             TAB: MY TEAMS
             ========================================== -->
        <?php
        // Fetch teams user belongs to
        $myTeams = [];
        try {
            $stmtTeams = $pdo->prepare("SELECT t.*, tm.role as member_role, u.name as captain_name
                                        FROM `team_members` tm
                                        JOIN `teams` t ON tm.team_id = t.id
                                        JOIN `users` u ON t.captain_id = u.id
                                        WHERE tm.user_id = :uid");
            $stmtTeams->execute(['uid' => $user_id]);
            $myTeams = $stmtTeams->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-people-group text-accent-cyan me-2"></i>My Squad Rosters</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-gaming">
                            <thead>
                                <tr>
                                    <th>Team Name</th>
                                    <th>Captain</th>
                                    <th>My Role</th>
                                    <th>Created On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($myTeams)): ?>
                                    <?php foreach ($myTeams as $team): ?>
                                        <tr>
                                            <td class="fw-bold text-adaptive">
                                                <i class="fa-solid fa-shield-halved text-accent-cyan me-2"></i>
                                                <?php echo htmlspecialchars($team['name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($team['captain_name']); ?></td>
                                            <td>
                                                 <span class="badge <?php echo $team['member_role'] === 'captain' ? 'badge-superadmin' : 'badge-player'; ?>">
                                                     <?php echo ucfirst($team['member_role']); ?>
                                                 </span>
                                             </td>
                                            <td class="font-display"><?php echo date('Y-m-d', strtotime($team['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-4">You do not belong to any gaming squads. Create one below!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-3 font-game"><i class="fa-solid fa-plus text-accent-purple me-2"></i>Form New Team</h5>
                    <p class="text-secondary fs-8 mb-4">Build your team, invite players, and register squad rosters in premium tournaments.</p>
                    
                    <form method="POST" action="dashboard.php?tab=teams">
                        <input type="hidden" name="action" value="create_team">
                        <div class="mb-3">
                            <label class="form-label-custom">Team Name</label>
                            <input type="text" name="team_name" class="form-control form-control-custom" placeholder="e.g. Sentinels Beta" required>
                        </div>
                        <button type="submit" class="btn btn-gaming-purple w-100 py-2">Create Roster</button>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'leaderboard'): ?>
        <!-- ==========================================
             TAB: LEADERBOARD
             ========================================== -->
        <?php
        // Fetch leaderboard records
        $gameFilter = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;
        $gamesList = [];
        $leadList = [];

        try {
            $gamesList = $pdo->query("SELECT id, name FROM `games`")->fetchAll();

            $queryL = "SELECT l.*, u.name as player_name, g.name as game_name, t.name as team_name
                       FROM `leaderboard` l
                       LEFT JOIN `users` u ON l.user_id = u.id
                       LEFT JOIN `teams` t ON l.team_id = t.id
                       JOIN `games` g ON l.game_id = g.id";
            
            if ($gameFilter > 0) {
                $queryL .= " WHERE l.game_id = :gid";
            }
            $queryL .= " ORDER BY l.points DESC";

            $stmtL = $pdo->prepare($queryL);
            if ($gameFilter > 0) {
                $stmtL->execute(['gid' => $gameFilter]);
            } else {
                $stmtL->execute();
            }
            $leadList = $stmtL->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                <h5 class="fw-bold text-adaptive mb-3 mb-md-0 font-game"><i class="fa-solid fa-ranking-star text-accent-cyan me-2"></i>Global Leaderboard</h5>
                
                <form method="GET" action="dashboard.php" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="tab" value="leaderboard">
                    <label class="text-secondary font-game text-nowrap me-2 mb-0">Filter Game:</label>
                    <select class="form-control form-control-custom py-1" name="game_id" onchange="this.form.submit()">
                        <option value="0">All Games</option>
                        <?php foreach ($gamesList as $g): ?>
                            <option value="<?php echo $g['id']; ?>" <?php echo $gameFilter == $g['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Competitor</th>
                            <th>Game</th>
                            <th>Points</th>
                            <th>Wins</th>
                            <th>Kills</th>
                            <th>Win Rate</th>
                            <th>Badge Achievement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leadList)): ?>
                            <?php $rank = 1; foreach ($leadList as $l): ?>
                                <tr class="<?php echo $l['user_id'] == $user_id ? 'bg-secondary bg-opacity-25 border border-info border-opacity-25' : ''; ?>">
                                    <td class="font-display fw-bold text-accent-cyan">#<?php echo $rank++; ?></td>
                                    <td>
                                        <span class="fw-bold <?php echo $l['user_id'] == $user_id ? 'text-accent-cyan' : 'text-adaptive'; ?>">
                                            <?php echo htmlspecialchars($l['player_name'] ?? 'Guest Player'); ?>
                                        </span>
                                        <?php if ($l['team_name']): ?>
                                            <small class="text-muted d-block">[<?php echo htmlspecialchars($l['team_name']); ?>]</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($l['game_name']); ?></td>
                                    <td class="font-display text-adaptive fw-bold"><?php echo number_format($l['points']); ?></td>
                                    <td class="font-display"><?php echo $l['wins']; ?></td>
                                    <td class="font-display"><?php echo $l['kills']; ?></td>
                                    <td class="font-display text-success"><?php echo $l['win_rate']; ?>%</td>
                                    <td>
                                        <span class="badge badge-badge"><?php echo htmlspecialchars($l['badge']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">No rankings found for this game category.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'rewards'): ?>
        <!-- ==========================================
             TAB: REWARDS & ACHIEVEMENTS
             ========================================== -->
        <?php
        $rewardsList = [];
        try {
            $rewardsList = $pdo->query("SELECT * FROM `rewards` ORDER BY `type` ASC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-gift text-accent-cyan me-2"></i>My Earned Achievements & Rewards</h5>
            
            <div class="row g-4">
                <?php if (!empty($rewardsList)): ?>
                    <?php foreach ($rewardsList as $r): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="achievement-card p-4 rounded-4 text-center h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="stat-card-icon mx-auto mb-3 <?php echo $r['type'] === 'badge' ? 'primary' : 'warning'; ?>" style="width: 60px; height: 60px; font-size: 1.6rem;">
                                        <i class="fa-solid fa-<?php echo htmlspecialchars($r['badge_icon']); ?>"></i>
                                    </div>
                                    <h5 class="fw-bold text-adaptive mb-2"><?php echo htmlspecialchars($r['name']); ?></h5>
                                    <p class="text-secondary fs-8 mb-4"><?php echo htmlspecialchars($r['description']); ?></p>
                                </div>
                                
                                <div class="border-top border-secondary border-opacity-25 pt-3">
                                    <?php if ($r['type'] === 'coins'): ?>
                                        <span class="badge badge-coins font-game">+<?php echo number_format($r['value']); ?> Coins</span>
                                    <?php elseif ($r['type'] === 'prize'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 font-game">₹<?php echo number_format($r['value'], 2); ?> Ticket</span>
                                    <?php else: ?>
                                        <span class="badge badge-badge font-game">Unlocked Title</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab === 'wallet'): ?>
        <!-- ==========================================
             TAB: WALLET & TRANSACTIONS
             ========================================== -->
        <?php
        $txList = [];
        try {
            $stmtTxL = $pdo->prepare("SELECT t.* 
                                      FROM `transactions` t
                                      JOIN `wallets` w ON t.wallet_id = w.id
                                      WHERE w.user_id = :uid 
                                      ORDER BY t.created_at DESC");
            $stmtTxL->execute(['uid' => $user_id]);
            $txList = $stmtTxL->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-receipt text-accent-cyan me-2"></i>Wallet Transaction Ledger</h5>
                    
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-gaming">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($txList)): ?>
                                    <?php foreach ($txList as $tx): ?>
                                        <tr>
                                            <td class="font-display">#TX-<?php echo str_pad($tx['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($tx['created_at'])); ?></td>
                                            <td class="text-secondary"><?php echo htmlspecialchars($tx['description']); ?></td>
                                            <td>
                                                <?php
                                                $txClass = 'badge-superadmin';
                                                if ($tx['type'] === 'deposit') {
                                                    $txClass = 'badge-completed';
                                                } elseif ($tx['type'] === 'reward') {
                                                    $txClass = 'badge-reward';
                                                }
                                                ?>
                                                <span class="badge <?php echo $txClass; ?> font-game" style="text-transform: uppercase;">
                                                    <?php echo str_replace('_', ' ', htmlspecialchars($tx['type'])); ?>
                                                </span>
                                            </td>
                                            <td class="font-display fw-bold <?php echo $tx['amount'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $tx['amount'] >= 0 ? '+' : ''; ?>₹<?php echo number_format(abs($tx['amount']), 2); ?>
                                            </td>
                                            <td>
                                                <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> <?php echo ucfirst($tx['status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-secondary py-4">No financial ledger transactions recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="glass-card p-4 mb-4">
                    <h5 class="fw-bold text-adaptive mb-3 font-game"><i class="fa-solid fa-wallet text-accent-cyan me-2"></i>My Balance Details</h5>
                    
                    <div class="p-3 bg-secondary bg-opacity-30 rounded-3 mb-4 text-center border border-secondary border-opacity-25">
                        <div class="text-secondary fs-8 font-game text-uppercase mb-1">AVAILABLE BALANCE</div>
                        <div class="font-display text-adaptive fw-bold" style="font-size: 1.55rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">₹<?php echo number_format($walletBalance, 2); ?></div>
                        <div class="text-accent-cyan mt-1 font-game fw-bold"><i class="fa-solid fa-coins me-1 text-warning"></i> <?php echo number_format($walletCoins); ?> Coins</div>
                    </div>

                    <form method="POST" action="dashboard.php?tab=wallet">
                        <input type="hidden" name="action" value="deposit">
                        <div class="mb-3">
                            <label class="form-label-custom">Deposit Mock Funds (INR)</label>
                            <div class="input-group">
                                <span class="input-group-text text-accent-cyan" style="background-color: rgba(17, 7, 36, 0.6); border-color: var(--card-border); color: var(--accent-cyan) !important;">₹</span>
                                <input type="number" step="10" min="10" name="amount" class="form-control form-control-custom" placeholder="100" required>
                            </div>
                            <small class="d-block mt-2" style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.55);">Simulate payment topups. Added directly to your live balance.</small>
                        </div>
                        <button type="submit" class="btn btn-gaming-cyan w-100 py-2"><i class="fa-solid fa-money-bill-transfer me-2"></i>Deposit Funds</button>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'notifications'): ?>
        <!-- ==========================================
             TAB: NOTIFICATIONS
             ========================================== -->
        <?php
        // Mark all notifications as read
        try {
            $stmtRead = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `user_id` = :uid");
            $stmtRead->execute(['uid' => $user_id]);
        } catch (PDOException $e) {}

        // Fetch user notifications list
        $allNotif = [];
        try {
            $stmtAN = $pdo->prepare("SELECT * FROM `notifications` WHERE `user_id` = :uid ORDER BY `created_at` DESC");
            $stmtAN->execute(['uid' => $user_id]);
            $allNotif = $stmtAN->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-bell text-accent-cyan me-2"></i>My Security & Match Notifications</h5>
            
            <div class="timeline-gaming">
                <?php if (!empty($allNotif)): ?>
                    <?php foreach ($allNotif as $an): ?>
                        <div class="timeline-gaming-item <?php echo $an['type'] === 'success' ? 'success' : ($an['type'] === 'warning' ? 'warning' : ''); ?>">
                            <div class="fw-bold text-adaptive font-game" style="font-size: 1rem;"><?php echo htmlspecialchars($an['title']); ?></div>
                            <div class="text-secondary fs-8 mt-1 mb-2"><?php echo htmlspecialchars($an['message']); ?></div>
                            <small class="text-muted font-display" style="font-size: 0.7rem;"><?php echo date('Y-m-d H:i:s', strtotime($an['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-secondary py-4">No system alert history found.</div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab === 'live'): ?>
        <!-- ==========================================
             TAB: LIVE MATCHES
             ========================================== -->
        <?php
        $liveMatches = [];
        try {
            $liveMatches = $pdo->query("SELECT m.*, t.name as tournament_name, g.name as game_name,
                                               p1.name as p1_name, p2.name as p2_name,
                                               t1.name as team1_name, t2.name as team2_name
                                        FROM `matches` m
                                        JOIN `tournaments` t ON m.tournament_id = t.id
                                        JOIN `games` g ON t.game_id = g.id
                                        LEFT JOIN `users` p1 ON m.player1_id = p1.id
                                        LEFT JOIN `users` p2 ON m.player2_id = p2.id
                                        LEFT JOIN `teams` t1 ON m.team1_id = t1.id
                                        LEFT JOIN `teams` t2 ON m.team2_id = t2.id
                                        WHERE m.status = 'live'
                                        ORDER BY m.scheduled_time ASC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h5 class="fw-bold text-adaptive mb-0 font-game"><span class="badge bg-danger me-2 animation-pulse">LIVE</span>Active E-Sports Matches</h5>
                <span class="text-secondary fs-8">Updates dynamically</span>
            </div>
            
            <?php if (!empty($liveMatches)): ?>
                <div class="row g-4">
                    <?php foreach ($liveMatches as $lm): ?>
                        <div class="col-md-6">
                            <div class="glass-card-cyan p-3 text-center">
                                <span class="badge badge-badge font-game mb-2"><?php echo htmlspecialchars($lm['game_name']); ?></span>
                                <div class="text-secondary fs-8 mb-3"><?php echo htmlspecialchars($lm['tournament_name']); ?></div>
                                <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
                                    <div class="fw-bold text-adaptive fs-6">
                                        <?php echo htmlspecialchars($lm['team1_name'] ?? $lm['p1_name'] ?? 'TBD'); ?>
                                    </div>
                                    <span class="badge bg-secondary px-2">VS</span>
                                    <div class="fw-bold text-adaptive fs-6">
                                        <?php echo htmlspecialchars($lm['team2_name'] ?? $lm['p2_name'] ?? 'TBD'); ?>
                                    </div>
                                </div>
                                <div class="font-display text-accent-cyan fs-7 fw-bold"><?php echo intval($lm['score1']); ?> - <?php echo intval($lm['score2']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-secondary py-5">
                    <i class="fa-solid fa-gamepad display-4 mb-3 text-secondary" style="opacity: 0.3;"></i>
                    <p>There are no matches currently broadcasted live. Check back when tournament rounds begin!</p>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'profile'): ?>
        <!-- ==========================================
             TAB: MY PROFILE
             ========================================== -->
        <?php
        // Handle profile edits
        $profile_success = '';
        $profile_error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_update'])) {
            $new_name = trim($_POST['name'] ?? '');
            $new_password = $_POST['password'] ?? '';
            
            if (empty($new_name)) {
                $profile_error = "Name cannot be empty.";
            } else {
                try {
                    if (!empty($new_password)) {
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmtP = $pdo->prepare("UPDATE `users` SET `name` = :name, `password` = :pwd WHERE `id` = :uid");
                        $stmtP->execute(['name' => $new_name, 'pwd' => $new_hash, 'uid' => $user_id]);
                    } else {
                        $stmtP = $pdo->prepare("UPDATE `users` SET `name` = :name WHERE `id` = :uid");
                        $stmtP->execute(['name' => $new_name, 'uid' => $user_id]);
                    }
                    $_SESSION['user_name'] = $new_name;
                    $user_name = $new_name;
                    $profile_success = "Profile details updated successfully.";
                } catch (PDOException $e) {
                    $profile_error = "Update failed: " . $e->getMessage();
                }
            }
        }
        
        // Fetch fresh email
        $fresh_email = '';
        try {
            $fresh_email = $pdo->query("SELECT email FROM users WHERE id = $user_id")->fetchColumn();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-3 font-game"><i class="fa-solid fa-user-ninja text-accent-purple me-2"></i>My Gamer Profile</h5>
            <p class="text-secondary fs-8 mb-4">View and modify your user credentials. Keep your contact details up to date.</p>
            
            <?php if ($profile_success): ?>
                <div class="alert alert-gaming alert-success p-3 mb-3"><?php echo $profile_success; ?></div>
            <?php endif; ?>
            <?php if ($profile_error): ?>
                <div class="alert alert-gaming alert-danger p-3 mb-3"><?php echo $profile_error; ?></div>
            <?php endif; ?>

            <form method="POST" action="dashboard.php?tab=profile">
                <input type="hidden" name="profile_update" value="1">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">Gamer ID / Full Name</label>
                        <input type="text" name="name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($user_name); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Register Email Address (Locked)</label>
                        <input type="email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($fresh_email); ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Account Password (Leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control form-control-custom" placeholder="••••••••">
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-gaming-purple px-4 py-2"><i class="fa-solid fa-floppy-disk me-2"></i>Save Account Settings</button>
                    </div>
                </div>
            </form>
        </div>

    <?php elseif ($tab === 'settings'): ?>
        <!-- ==========================================
             TAB: ACCOUNT PREFERENCES
             ========================================== -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-3 font-game"><i class="fa-solid fa-sliders text-accent-cyan me-2"></i>Account Preferences</h5>
            <p class="text-secondary fs-8 mb-4">Customize notifications, game alerts, and dashboard widgets.</p>
            
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="notif-match" checked>
                <label class="form-check-label text-adaptive" for="notif-match">Email notifications for match updates</label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="notif-wallet" checked>
                <label class="form-check-label text-adaptive" for="notif-wallet">Wallet transaction alerts</label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="preference-glow" checked>
                <label class="form-check-label text-adaptive" for="preference-glow">Enable dynamic accent glows (neon mode)</label>
            </div>
            <button class="btn btn-gaming-cyan mt-3 px-4" onclick="alert('Preferences updated successfully!');"><i class="fa-solid fa-check me-2"></i>Save Preferences</button>
        </div>

    <?php else: ?>
        <!-- ==========================================
             TAB: OVERVIEW DEFAULT
             ========================================== -->
        <?php
        // Fetch active announcements
        $announcements = [];
        try {
            $announcements = $pdo->query("SELECT * FROM `announcements` WHERE `status` = 'active' ORDER BY `created_at` DESC LIMIT 2")->fetchAll();
        } catch (PDOException $e) {}

        // Fetch user's team IDs to prevent undefined variable warning in query
        $myTeamIdsStr = '0'; // Default to 0 so IN (0) works and returns empty if no teams
        $myTeamIds = []; // Array for PHP side checks
        try {
            $stmtMyTeams = $pdo->prepare("SELECT team_id FROM `team_members` WHERE `user_id` = :uid");
            $stmtMyTeams->execute(['uid' => $user_id]);
            $myTeams = $stmtMyTeams->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($myTeams)) {
                $myTeamIdsStr = implode(',', array_map('intval', $myTeams));
                $myTeamIds = array_map('intval', $myTeams);
            }
        } catch (PDOException $e) {}

        // Fetch recent match outcomes for user
        $recentMatches = [];
        try {
            $queryRM = "SELECT m.*, t.name as tournament_name, g.name as game_name,
                               p1.name as p1_name, p2.name as p2_name,
                               t1.name as team1_name, t2.name as team2_name
                        FROM `matches` m
                        JOIN `tournaments` t ON m.tournament_id = t.id
                        JOIN `games` g ON t.game_id = g.id
                        LEFT JOIN `users` p1 ON m.player1_id = p1.id
                        LEFT JOIN `users` p2 ON m.player2_id = p2.id
                        LEFT JOIN `teams` t1 ON m.team1_id = t1.id
                        LEFT JOIN `teams` t2 ON m.team2_id = t2.id
                        WHERE (m.player1_id = :uid OR m.player2_id = :uid 
                           OR m.team1_id IN ($myTeamIdsStr) OR m.team2_id IN ($myTeamIdsStr))
                          AND m.status = 'completed'
                        ORDER BY m.scheduled_time DESC LIMIT 3";
            $stmtRM = $pdo->prepare($queryRM);
            $stmtRM->execute(['uid' => $user_id]);
            $recentMatches = $stmtRM->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="row g-4">
            <!-- Left: Match History & Announcements -->
            <div class="col-lg-8">
                <!-- Announcements Widget -->
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="glass-card p-4 mb-4 border border-info border-opacity-25" style="background: linear-gradient(135deg, rgba(6, 182, 212, 0.05) 0%, rgba(168, 85, 247, 0.05) 100%);">
                            <h5 class="fw-bold text-accent-cyan font-game mb-2"><i class="fa-solid fa-bullhorn me-2 text-warning"></i><?php echo htmlspecialchars($ann['title']); ?></h5>
                            <p class="text-secondary fs-8 mb-0"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Match History -->
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-history text-accent-purple me-2"></i>My Recent Match Outcomes</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-gaming">
                            <thead>
                                <tr>
                                    <th>Match Details</th>
                                    <th>Opponent</th>
                                    <th>Results Score</th>
                                    <th>State</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentMatches)): ?>
                                    <?php foreach ($recentMatches as $rm): ?>
                                        <?php
                                        $mode = $rm['team1_id'] ? 'team' : 'solo';
                                        $isP1 = ($mode === 'solo' && $rm['player1_id'] == $user_id) || ($mode === 'team' && in_array($rm['team1_id'], $myTeamIds));
                                        
                                        $oppName = $isP1 ? ($mode === 'solo' ? $rm['p2_name'] : $rm['team2_name']) : ($mode === 'solo' ? $rm['p1_name'] : $rm['team1_name']);
                                        $myScore = $isP1 ? $rm['score1'] : $rm['score2'];
                                        $oppScore = $isP1 ? $rm['score2'] : $rm['score1'];
                                        $won = ($isP1 && $rm['score1'] > $rm['score2']) || (!$isP1 && $rm['score2'] > $rm['score1']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-adaptive"><?php echo htmlspecialchars($rm['tournament_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($rm['game_name']); ?> &bull; Rd <?php echo $rm['round']; ?></small>
                                            </td>
                                            <td>vs <strong class="text-adaptive"><?php echo htmlspecialchars($oppName ?? 'TBD Team'); ?></strong></td>
                                            <td class="font-display fw-bold">
                                                <span class="text-success"><?php echo $myScore; ?></span> - <span class="text-danger"><?php echo $oppScore; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge font-game <?php echo $won ? 'bg-success bg-opacity-25 text-success border border-success border-opacity-25' : 'bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25'; ?>">
                                                    <?php echo $won ? 'VICTORY' : 'DEFEAT'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-4">No recent matches played yet. Join active tournaments to compete!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Activity Logs & Timeline Preview -->
            <div class="col-lg-4">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-clock-rotate-left text-accent-cyan me-2"></i>My Recent Logs</h5>
                    
                    <div class="timeline-gaming" style="font-size: 0.85rem;">
                        <div class="timeline-gaming-item success">
                            <div class="fw-bold text-adaptive">Dashboard Portal Loaded</div>
                            <div class="text-secondary fs-8">Authentication token verified. Session active.</div>
                        </div>
                        <div class="timeline-gaming-item">
                            <div class="fw-bold text-adaptive">Wallet Check Performed</div>
                            <div class="text-secondary fs-8">Verified available Coins balance and history logs.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
