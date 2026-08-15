<?php
// admin/dashboard.php
// Premium Admin Dashboard Workspace - Clash Arena

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';

// Verify role access
checkRole(['admin']);

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

    // 1. Create Tournament
    if ($action === 'create_tournament') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
        $type = isset($_POST['type']) ? $_POST['type'] : 'solo';
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $max_participants = isset($_POST['max_participants']) ? intval($_POST['max_participants']) : 8;
        $prize_pool = isset($_POST['prize_pool']) ? floatval($_POST['prize_pool']) : 0.00;
        $entry_fee = isset($_POST['entry_fee']) ? floatval($_POST['entry_fee']) : 0.00;

        if (empty($name) || $game_id <= 0 || empty($start_date)) {
            $error_msg = 'Please fill in all required fields.';
        } else {
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO `tournaments` (`game_id`, `name`, `type`, `status`, `start_date`, `max_participants`, `prize_pool`, `entry_fee`) VALUES (:gid, :name, :type, 'registration_open', :sdate, :max_p, :prize, :fee)");
                $stmtInsert->execute([
                    'gid' => $game_id,
                    'name' => $name,
                    'type' => $type,
                    'sdate' => $start_date,
                    'max_p' => $max_participants,
                    'prize' => $prize_pool,
                    'fee' => $entry_fee
                ]);
                
                // Add Audit Log
                $stmtAudit = $pdo->prepare("INSERT INTO `audit_logs` (`user_id`, `action`, `ip_address`, `details`) VALUES (:uid, 'Created Tournament', :ip, :details)");
                $stmtAudit->execute(['uid' => $user_id, 'ip' => $_SERVER['REMOTE_ADDR'], 'details' => "Created tournament: $name"]);

                $success_msg = "Tournament \"$name\" created successfully!";
            } catch (PDOException $e) {
                $error_msg = 'Failed to create tournament: ' . $e->getMessage();
            }
        }
    }

    // 2. Delete Tournament
    elseif ($action === 'delete_tournament') {
        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        try {
            $stmtDel = $pdo->prepare("DELETE FROM `tournaments` WHERE `id` = :id");
            $stmtDel->execute(['id' => $tournament_id]);
            $success_msg = "Tournament deleted successfully.";
        } catch (PDOException $e) {
            $error_msg = 'Failed to delete tournament: ' . $e->getMessage();
        }
    }

    // 3. Approve Registration
    elseif ($action === 'approve_registration') {
        $reg_id = isset($_POST['reg_id']) ? intval($_POST['reg_id']) : 0;
        try {
            $pdo->beginTransaction();

            // Fetch registration details
            $stmtReg = $pdo->prepare("SELECT * FROM `tournament_registrations` WHERE `id` = :id");
            $stmtReg->execute(['id' => $reg_id]);
            $reg = $stmtReg->fetch();

            if ($reg) {
                // Update status
                $stmtUp = $pdo->prepare("UPDATE `tournament_registrations` SET `status` = 'approved' WHERE `id` = :id");
                $stmtUp->execute(['id' => $reg_id]);

                // Notify User
                $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES (:uid, 'Registration Approved', 'Your entry registration has been approved by admin.', 'success')");
                $stmtNotif->execute(['uid' => $reg['user_id']]);

                $pdo->commit();
                $success_msg = "Registration approved successfully.";
            } else {
                $pdo->rollBack();
                $error_msg = "Registration records not found.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = 'Error approving registration: ' . $e->getMessage();
        }
    }

    // 4. Generate Single-Elimination Bracket
    elseif ($action === 'generate_bracket') {
        $tournament_id = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        try {
            $pdo->beginTransaction();

            // Fetch tournament details
            $stmtT = $pdo->prepare("SELECT * FROM `tournaments` WHERE `id` = :tid");
            $stmtT->execute(['tid' => $tournament_id]);
            $tour = $stmtT->fetch();

            if (!$tour) {
                throw new Exception('Tournament not found.');
            }

            // Fetch approved registrations
            $stmtRegs = $pdo->prepare("SELECT * FROM `tournament_registrations` WHERE `tournament_id` = :tid AND `status` = 'approved'");
            $stmtRegs->execute(['tid' => $tournament_id]);
            $regs = $stmtRegs->fetchAll();

            $participantsCount = count($regs);
            if ($participantsCount < 2) {
                throw new Exception('Need at least 2 approved registrations to generate a matchmaking bracket.');
            }

            // Ensure bracket matches are empty first
            $stmtClearMatches = $pdo->prepare("DELETE FROM `matches` WHERE `tournament_id` = :tid");
            $stmtClearMatches->execute(['tid' => $tournament_id]);

            // Create Round 1 matches
            // Pair up sequentially
            $matchOrder = 1;
            for ($i = 0; $i < $participantsCount; $i += 2) {
                // If odd participant left, they get a bye later, or we check array boundary
                $p1 = $regs[$i];
                $p2 = isset($regs[$i + 1]) ? $regs[$i + 1] : null;

                $stmtInsertM = $pdo->prepare("INSERT INTO `matches` (`tournament_id`, `round`, `match_order`, `player1_id`, `player2_id`, `team1_id`, `team2_id`, `status`, `scheduled_time`) VALUES (:tid, 1, :order, :p1, :p2, :t1, :t2, 'scheduled', :sdate)");
                $stmtInsertM->execute([
                    'tid' => $tournament_id,
                    'order' => $matchOrder++,
                    'p1' => $tour['type'] === 'solo' ? $p1['user_id'] : null,
                    'p2' => ($p2 && $tour['type'] === 'solo') ? $p2['user_id'] : null,
                    't1' => $tour['type'] === 'team' ? $p1['team_id'] : null,
                    't2' => ($p2 && $tour['type'] === 'team') ? $p2['team_id'] : null,
                    'sdate' => $tour['start_date']
                ]);
            }

            // Calculate total rounds (2^R >= participants)
            $totalRounds = ceil(log($participantsCount, 2));

            // Insert Bracket info
            $stmtDelB = $pdo->prepare("DELETE FROM `brackets` WHERE `tournament_id` = :tid");
            $stmtDelB->execute(['tid' => $tournament_id]);

            $stmtB = $pdo->prepare("INSERT INTO `brackets` (`tournament_id`, `total_rounds`, `current_round`, `bracket_type`) VALUES (:tid, :tot, 1, 'single_elimination')");
            $stmtB->execute(['tid' => $tournament_id, 'tot' => $totalRounds]);

            // Update tournament status to Live
            $stmtUpT = $pdo->prepare("UPDATE `tournaments` SET `status` = 'live' WHERE `id` = :tid");
            $stmtUpT->execute(['tid' => $tournament_id]);

            // Send notification to participants
            foreach ($regs as $r) {
                $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES (:uid, 'Tournament Brackets Generated', :msg, 'warning')");
                $stmtNotif->execute([
                    'uid' => $r['user_id'],
                    'msg' => "Match brackets for \"{$tour['name']}\" are live! Check your dashboard for match schedule details."
                ]);
            }

            // Audit log
            $stmtAudit = $pdo->prepare("INSERT INTO `audit_logs` (`user_id`, `action`, `ip_address`, `details`) VALUES (:uid, 'Generated Bracket', :ip, :details)");
            $stmtAudit->execute(['uid' => $user_id, 'ip' => $_SERVER['REMOTE_ADDR'], 'details' => "Generated bracket round 1 for tournament: " . $tour['name']]);

            $pdo->commit();
            $success_msg = "Matchmaking brackets generated successfully and tournament status set to LIVE!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = $e->getMessage();
        }
    }

    // 5. Update Score and Progress Bracket
    elseif ($action === 'update_score') {
        $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
        $score1 = isset($_POST['score1']) ? intval($_POST['score1']) : 0;
        $score2 = isset($_POST['score2']) ? intval($_POST['score2']) : 0;

        try {
            $pdo->beginTransaction();

            // Fetch match details
            $stmtMatch = $pdo->prepare("SELECT m.*, t.name as tour_name, t.prize_pool as tour_prize, t.type as tour_type, t.winner_id as tour_winner
                                        FROM `matches` m
                                        JOIN `tournaments` t ON m.tournament_id = t.id
                                        WHERE m.id = :mid");
            $stmtMatch->execute(['mid' => $match_id]);
            $match = $stmtMatch->fetch();

            if (!$match) {
                throw new Exception('Match records not found.');
            }

            if ($score1 === $score2) {
                throw new Exception('Draw matches are not supported. Brackets require a winner.');
            }

            // Determine winner ID
            $winner_id = ($score1 > $score2) ? $match['player1_id'] : $match['player2_id'];
            $winner_team_id = ($score1 > $score2) ? $match['team1_id'] : $match['team2_id'];

            // Update current match
            $stmtUpM = $pdo->prepare("UPDATE `matches` SET `score1` = :s1, `score2` = :s2, `winner_id` = :win, `winner_team_id` = :wt, `status` = 'completed' WHERE `id` = :mid");
            $stmtUpM->execute([
                's1' => $score1,
                's2' => $score2,
                'win' => $winner_id,
                'wt' => $winner_team_id,
                'mid' => $match_id
            ]);

            // Fetch bracket settings
            $stmtB = $pdo->prepare("SELECT * FROM `brackets` WHERE `tournament_id` = :tid");
            $stmtB->execute(['tid' => $match['tournament_id']]);
            $bracket = $stmtB->fetch();

            if ($bracket) {
                $totalRounds = $bracket['total_rounds'];
                $currentRound = $match['round'];

                // Check if this is the final round
                if ($currentRound >= $totalRounds) {
                    // This was the finals! Declare tournament champion!
                    $finalWinnerUser = $winner_id;
                    if ($match['tour_type'] === 'team') {
                        // Winner user is the team captain
                        $stmtCap = $pdo->prepare("SELECT captain_id FROM `teams` WHERE `id` = :tid");
                        $stmtCap->execute(['tid' => $winner_team_id]);
                        $finalWinnerUser = $stmtCap->fetchColumn();
                    }

                    // Update tournament winner
                    $stmtUpT = $pdo->prepare("UPDATE `tournaments` SET `winner_id` = :win, `status` = 'completed' WHERE `id` = :tid");
                    $stmtUpT->execute(['win' => $finalWinnerUser, 'tid' => $match['tournament_id']]);

                    // Add Prize pool to winning captain's wallet
                    if ($match['tour_prize'] > 0) {
                        $stmtW = $pdo->prepare("SELECT id FROM `wallets` WHERE `user_id` = :uid");
                        $stmtW->execute(['uid' => $finalWinnerUser]);
                        $wid = $stmtW->fetchColumn();

                        if ($wid) {
                            // Update balance
                            $stmtUpW = $pdo->prepare("UPDATE `wallets` SET `balance` = `balance` + :prize, `coins` = `coins` + 500 WHERE `id` = :wid");
                            $stmtUpW->execute(['prize' => $match['tour_prize'], 'wid' => $wid]);

                            // Record Transaction
                            $stmtTx = $pdo->prepare("INSERT INTO `transactions` (`wallet_id`, `amount`, `type`, `status`, `description`) VALUES (:wid, :amount, 'reward', 'completed', :desc)");
                            $stmtTx->execute([
                                'wid' => $wid,
                                'amount' => $match['tour_prize'],
                                'desc' => "Prize pool distribution for winning " . $match['tour_name']
                            ]);
                        }
                    }

                    // Send notifications to everyone
                    $stmtNotif = $pdo->prepare("INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`) VALUES (:uid, 'Tournament Champion Crowned', :msg, 'success')");
                    $stmtNotif->execute([
                        'uid' => $finalWinnerUser,
                        'msg' => "Congratulations! You won first place in \"{$match['tour_name']}\" and claimed your ₹$match[tour_prize] prize pool!"
                    ]);
                } else {
                    // Progress to next round
                    $nextRound = $currentRound + 1;
                    $nextMatchOrder = ceil($match['match_order'] / 2);
                    $slot = ($match['match_order'] % 2 !== 0) ? 1 : 2; // Odd order goes to player1, even goes to player2

                    // Check if the next round match is already generated
                    $stmtNextM = $pdo->prepare("SELECT id FROM `matches` WHERE `tournament_id` = :tid AND `round` = :r AND `match_order` = :o");
                    $stmtNextM->execute([
                        'tid' => $match['tournament_id'],
                        'r' => $nextRound,
                        'o' => $nextMatchOrder
                    ]);
                    $nextMatchId = $stmtNextM->fetchColumn();

                    if ($nextMatchId) {
                        // Match already exists, update the empty slot
                        if ($slot === 1) {
                            $stmtProg = $pdo->prepare("UPDATE `matches` SET `player1_id` = :pid, `team1_id` = :tid WHERE `id` = :mid");
                        } else {
                            $stmtProg = $pdo->prepare("UPDATE `matches` SET `player2_id` = :pid, `team2_id` = :tid WHERE `id` = :mid");
                        }
                        $stmtProg->execute([
                            'pid' => $match['tour_type'] === 'solo' ? $winner_id : null,
                            'tid' => $match['tour_type'] === 'team' ? $winner_team_id : null,
                            'mid' => $nextMatchId
                        ]);
                    } else {
                        // Create next match placeholder
                        $stmtInsertProg = $pdo->prepare("INSERT INTO `matches` (`tournament_id`, `round`, `match_order`, `player1_id`, `team1_id`, `player2_id`, `team2_id`, `status`, `scheduled_time`) VALUES (:tid, :r, :order, :p1, :t1, NULL, NULL, 'scheduled', :sdate)");
                        $stmtInsertProg->execute([
                            'tid' => $match['tournament_id'],
                            'r' => $nextRound,
                            'order' => $nextMatchOrder,
                            'p1' => $match['tour_type'] === 'solo' ? $winner_id : null,
                            't1' => $match['tour_type'] === 'team' ? $winner_team_id : null,
                            'sdate' => $match['scheduled_time'] // Carry forward same scheduled date or adapt
                        ]);
                    }
                }
            }

            $pdo->commit();
            $success_msg = "Match score recorded successfully. Bracket nodes updated!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = $e->getMessage();
        }
    }
}

// ==========================================
// DYNAMIC METRICS FOR DASHBOARD
// ==========================================
$totalTournaments = 0;
$totalRegistrations = 0;
$totalRevenue = 0.00;
$dailyMatches = 0;

try {
    $totalTournaments = $pdo->query("SELECT COUNT(*) FROM `tournaments`")->fetchColumn();
    $totalRegistrations = $pdo->query("SELECT COUNT(*) FROM `tournament_registrations` WHERE `status` = 'approved'")->fetchColumn();
    
    // Revenue counts (sum of entry fees from approved registrations)
    $stmtRev = $pdo->query("SELECT SUM(t.entry_fee) FROM `tournament_registrations` tr JOIN `tournaments` t ON tr.tournament_id = t.id WHERE tr.status = 'approved'");
    $totalRevenue = $stmtRev->fetchColumn() ?? 0.00;

    $dailyMatches = $pdo->query("SELECT COUNT(*) FROM `matches` WHERE DATE(`scheduled_time`) = CURRENT_DATE()")->fetchColumn();
} catch (PDOException $e) {}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-content">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item text-muted">Admin console</li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo $tab ? ucfirst(htmlspecialchars($tab)) : 'Console Overview'; ?>
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
            <h1 class="fw-bold fs-3 text-adaptive mb-1 font-display">E-Sports Admin Panel</h1>
            <p class="text-secondary mb-0">Build tournaments, manage registrations, review bracket structures, and score matches.</p>
        </div>
        <button class="btn btn-gaming-purple btn-sm" data-bs-toggle="modal" data-bs-target="#createTournamentModal">
            <i class="fa-solid fa-plus me-2"></i>New Tournament
        </button>
    </div>

    <!-- Overview Stats -->
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-info">
                    <h3>Total Leagues</h3>
                    <p class="font-display"><?php echo intval($totalTournaments); ?></p>
                </div>
                <div class="stat-card-icon primary"><i class="fa-solid fa-trophy"></i></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-info">
                    <h3>Registrations</h3>
                    <p class="font-display"><?php echo intval($totalRegistrations); ?></p>
                </div>
                <div class="stat-card-icon secondary"><i class="fa-solid fa-users"></i></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-info">
                    <h3>Daily Matches</h3>
                    <p class="font-display"><?php echo intval($dailyMatches); ?></p>
                </div>
                <div class="stat-card-icon warning"><i class="fa-solid fa-calendar-check"></i></div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-card-info">
                    <h3>Platform Revenue</h3>
                    <p class="font-display">₹<?php echo number_format($totalRevenue, 2); ?></p>
                </div>
                <div class="stat-card-icon success"><i class="fa-solid fa-wallet"></i></div>
            </div>
        </div>
    </div>

    <!-- TAB ROUTER BLOCKS -->
    <?php if ($tab === 'tournaments'): ?>
        <!-- ==========================================
             TAB: TOURNAMENT MANAGEMENT
             ========================================== -->
        <?php
        $tournamentsList = [];
        try {
            $tournamentsList = $pdo->query("SELECT t.*, g.name as game_name FROM `tournaments` t JOIN `games` g ON t.game_id = g.id ORDER BY t.created_at DESC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-trophy text-accent-cyan me-2"></i>Tournament Management</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Game Category</th>
                            <th>Type</th>
                            <th>Prize Pool</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($tournamentsList)): ?>
                            <?php foreach ($tournamentsList as $t): ?>
                                <tr>
                                    <td class="font-display">#TR-<?php echo $t['id']; ?></td>
                                    <td class="fw-bold text-adaptive"><?php echo htmlspecialchars($t['name']); ?></td>
                                    <td><?php echo htmlspecialchars($t['game_name']); ?></td>
                                    <td><span class="badge badge-badge"><?php echo htmlspecialchars($t['type']); ?></span></td>
                                    <td class="text-warning fw-bold font-display">$<?php echo number_format($t['prize_pool'], 2); ?></td>
                                    <td>
                                         <?php
                                         $statusClass = 'badge-superadmin';
                                         $statusText = str_replace('_', ' ', $t['status']);
                                         if ($t['status'] === 'live') {
                                             $statusClass = 'badge-superadmin';
                                         } elseif ($t['status'] === 'completed') {
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
                                        <form method="POST" action="dashboard.php?tab=tournaments" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this tournament?');">
                                            <input type="hidden" name="action" value="delete_tournament">
                                            <input type="hidden" name="tournament_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-3"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">No tournaments created yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'brackets'): ?>
        <!-- ==========================================
             TAB: BRACKET GENERATOR
             ========================================== -->
        <?php
        // Fetch tournaments open or live to render or generate brackets
        $bracketT = [];
        try {
            $bracketT = $pdo->query("SELECT t.*, g.name as game_name FROM `tournaments` t JOIN `games` g ON t.game_id = g.id WHERE t.status IN ('registration_open', 'live')")->fetchAll();
        } catch (PDOException $e) {}

        $selectedTid = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
        $matchesList = [];
        $selectedTour = null;

        if ($selectedTid > 0) {
            try {
                $stmtST = $pdo->prepare("SELECT * FROM `tournaments` WHERE `id` = :id");
                $stmtST->execute(['id' => $selectedTid]);
                $selectedTour = $stmtST->fetch();

                $stmtM = $pdo->prepare("SELECT m.*, 
                                               p1.name as p1_name, p2.name as p2_name,
                                               t1.name as team1_name, t2.name as team2_name
                                        FROM `matches` m
                                        LEFT JOIN `users` p1 ON m.player1_id = p1.id
                                        LEFT JOIN `users` p2 ON m.player2_id = p2.id
                                        LEFT JOIN `teams` t1 ON m.team1_id = t1.id
                                        LEFT JOIN `teams` t2 ON m.team2_id = t2.id
                                        WHERE m.tournament_id = :tid 
                                        ORDER BY m.round ASC, m.match_order ASC");
                $stmtM->execute(['tid' => $selectedTid]);
                $matchesList = $stmtM->fetchAll();
            } catch (PDOException $e) {}
        }
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-sitemap text-accent-cyan me-2"></i>Single-Elimination Bracket Generator</h5>
            
            <div class="row g-3 mb-5 align-items-end">
                <div class="col-md-6">
                    <form method="GET" action="dashboard.php">
                        <input type="hidden" name="tab" value="brackets">
                        <label class="form-label-custom">Select Active Tournament</label>
                        <select class="form-control form-control-custom" name="tournament_id" onchange="this.form.submit()">
                            <option value="0">-- Select Tournament --</option>
                            <?php foreach ($bracketT as $bt): ?>
                                <option value="<?php echo $bt['id']; ?>" <?php echo $selectedTid === $bt['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($bt['name']); ?> (<?php echo htmlspecialchars($bt['status']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-6">
                    <?php if ($selectedTour && $selectedTour['status'] === 'registration_open'): ?>
                        <form method="POST" action="dashboard.php?tab=brackets">
                            <input type="hidden" name="action" value="generate_bracket">
                            <input type="hidden" name="tournament_id" value="<?php echo $selectedTid; ?>">
                            <button type="submit" class="btn btn-gaming-cyan w-100"><i class="fa-solid fa-bolt me-2"></i>Generate Bracket Grid</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selectedTid > 0 && !empty($matchesList)): ?>
                <!-- Visualize Brackets rounds -->
                <div class="bracket-container border border-secondary border-opacity-25 rounded-4 p-4" style="background-color: rgba(0,0,0,0.2);">
                    <?php
                    // Group matches by round
                    $rounds = [];
                    foreach ($matchesList as $m) {
                        $rounds[$m['round']][] = $m;
                    }
                    ksort($rounds);
                    ?>
                    <?php foreach ($rounds as $roundNo => $roundMatches): ?>
                        <div class="bracket-round">
                            <h6 class="text-accent-cyan font-display text-center mb-4">Round <?php echo $roundNo; ?></h6>
                            <?php foreach ($roundMatches as $rm): ?>
                                <?php 
                                $isTeam = $selectedTour['type'] === 'team';
                                $p1_label = $isTeam ? ($rm['team1_name'] ?? 'TBD') : ($rm['p1_name'] ?? 'TBD');
                                $p2_label = $isTeam ? ($rm['team2_name'] ?? 'TBD') : ($rm['p2_name'] ?? 'TBD');
                                ?>
                                <div class="bracket-match">
                                    <div class="bracket-team <?php echo $rm['winner_id'] && ($rm['winner_id'] == $rm['player1_id'] || $rm['winner_team_id'] == $rm['team1_id']) ? 'winner' : ''; ?>">
                                        <span class="bracket-team-name"><i class="fa-solid fa-gamepad me-1 fs-9"></i> <?php echo htmlspecialchars($p1_label); ?></span>
                                        <span class="bracket-team-score"><?php echo $rm['status'] === 'completed' ? $rm['score1'] : '--'; ?></span>
                                    </div>
                                    <div class="border-top border-secondary border-opacity-25 my-1"></div>
                                    <div class="bracket-team <?php echo $rm['winner_id'] && ($rm['winner_id'] == $rm['player2_id'] || $rm['winner_team_id'] == $rm['team2_id']) ? 'winner' : ''; ?>">
                                        <span class="bracket-team-name"><i class="fa-solid fa-gamepad me-1 fs-9"></i> <?php echo htmlspecialchars($p2_label); ?></span>
                                        <span class="bracket-team-score"><?php echo $rm['status'] === 'completed' ? $rm['score2'] : '--'; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($selectedTid > 0): ?>
                <div class="alert alert-gaming alert-warning text-center">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> No bracket matches generated yet for this tournament. Approved registrations can generate brackets.
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($tab === 'matches'): ?>
        <!-- ==========================================
             TAB: MATCH MANAGEMENT
             ========================================== -->
        <?php
        $allMatches = [];
        try {
            $allMatches = $pdo->query("SELECT m.*, t.name as tournament_name, t.type as tour_type,
                                              p1.name as p1_name, p2.name as p2_name,
                                              t1.name as team1_name, t2.name as team2_name
                                       FROM `matches` m
                                       JOIN `tournaments` t ON m.tournament_id = t.id
                                       LEFT JOIN `users` p1 ON m.player1_id = p1.id
                                       LEFT JOIN `users` p2 ON m.player2_id = p2.id
                                       LEFT JOIN `teams` t1 ON m.team1_id = t1.id
                                       LEFT JOIN `teams` t2 ON m.team2_id = t2.id
                                       ORDER BY m.status DESC, m.scheduled_time ASC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-calendar-days text-accent-cyan me-2"></i>Match Operations</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>Tournament</th>
                            <th>Match Opponents</th>
                            <th>Round</th>
                            <th>Schedule Time</th>
                            <th>Outcome Score</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allMatches)): ?>
                            <?php foreach ($allMatches as $m): ?>
                                <?php 
                                $isTeam = $m['tour_type'] === 'team';
                                $p1 = $isTeam ? $m['team1_name'] : $m['p1_name'];
                                $p2 = $isTeam ? $m['team2_name'] : $m['p2_name'];
                                ?>
                                <tr>
                                    <td class="fw-bold text-adaptive"><?php echo htmlspecialchars($m['tournament_name']); ?></td>
                                    <td>
                                        <strong class="text-accent-cyan"><?php echo htmlspecialchars($p1 ?? 'TBD'); ?></strong>
                                        <span class="text-muted mx-2">vs</span>
                                        <strong class="text-adaptive"><?php echo htmlspecialchars($p2 ?? 'TBD'); ?></strong>
                                    </td>
                                    <td class="font-display">Rd <?php echo $m['round']; ?></td>
                                    <td class="font-display"><?php echo date('M d, H:i', strtotime($m['scheduled_time'])); ?></td>
                                    <td class="font-display fw-bold">
                                        <?php if ($m['status'] === 'completed'): ?>
                                            <span class="text-success"><?php echo $m['score1']; ?></span> - <span class="text-success"><?php echo $m['score2']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                         <?php
                                         $mStatusClass = 'badge-superadmin';
                                         if ($m['status'] === 'live') {
                                             $mStatusClass = 'badge-superadmin';
                                         } elseif ($m['status'] === 'completed') {
                                             $mStatusClass = 'badge-completed';
                                         } else {
                                             $mStatusClass = 'badge-admin';
                                         }
                                         ?>
                                         <span class="badge <?php echo $mStatusClass; ?> font-game" style="text-transform: uppercase;">
                                             <?php echo htmlspecialchars($m['status']); ?>
                                         </span>
                                     </td>
                                    <td class="text-end">
                                        <?php if ($m['status'] !== 'completed' && $p1 && $p2): ?>
                                            <button class="btn btn-gaming-purple btn-sm py-1 px-3" data-bs-toggle="modal" data-bs-target="#scoreModal<?php echo $m['id']; ?>">
                                                Record Score
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm py-1 px-3" disabled>Final</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Score Modal -->
                                <?php if ($m['status'] !== 'completed' && $p1 && $p2): ?>
                                    <div class="modal fade" id="scoreModal<?php echo $m['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content bg-secondary border-0" style="border: 1px solid var(--card-border) !important;">
                                                <div class="modal-header border-bottom border-secondary border-opacity-25">
                                                    <h5 class="modal-title text-adaptive font-game">Record Match Score</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="dashboard.php?tab=matches">
                                                    <input type="hidden" name="action" value="update_score">
                                                    <input type="hidden" name="match_id" value="<?php echo $m['id']; ?>">
                                                    
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-6">
                                                                <label class="form-label-custom"><?php echo htmlspecialchars($p1); ?> Score</label>
                                                                <input type="number" name="score1" min="0" class="form-control form-control-custom" required>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label-custom"><?php echo htmlspecialchars($p2); ?> Score</label>
                                                                <input type="number" name="score2" min="0" class="form-control form-control-custom" required>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted d-block mt-3">The player/team with the highest score is automatically declared the winner and progressed to the next bracket round.</small>
                                                    </div>
                                                    
                                                    <div class="modal-footer border-top border-secondary border-opacity-25">
                                                        <button type="button" class="btn btn-gaming-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-gaming-purple btn-sm">Submit Winner</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">No match grids scheduled.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'leaderboard'): ?>
        <!-- ==========================================
             TAB: LEADERBOARD PREVIEW
             ========================================== -->
        <?php
        $leaderboardStats = [];
        try {
            $leaderboardStats = $pdo->query("SELECT l.*, u.name as player_name, g.name as game_name, t.name as team_name
                                             FROM `leaderboard` l
                                             LEFT JOIN `users` u ON l.user_id = u.id
                                             LEFT JOIN `teams` t ON l.team_id = t.id
                                             JOIN `games` g ON l.game_id = g.id
                                             ORDER BY l.points DESC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-ranking-star text-accent-cyan me-2"></i>Active Platform Leaderboard</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Team</th>
                            <th>Game Arena</th>
                            <th>Points</th>
                            <th>Wins</th>
                            <th>Kills</th>
                            <th>Win Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leaderboardStats)): ?>
                            <?php $rank = 1; foreach ($leaderboardStats as $l): ?>
                                <tr>
                                    <td class="font-display fw-bold text-accent-cyan">#<?php echo $rank++; ?></td>
                                    <td class="fw-bold text-adaptive"><?php echo htmlspecialchars($l['player_name'] ?? 'Guest Roster'); ?></td>
                                    <td><?php echo htmlspecialchars($l['team_name'] ?? '--'); ?></td>
                                    <td><?php echo htmlspecialchars($l['game_name']); ?></td>
                                    <td class="font-display text-adaptive fw-bold"><?php echo $l['points']; ?></td>
                                    <td class="font-display"><?php echo $l['wins']; ?></td>
                                    <td class="font-display"><?php echo $l['kills']; ?></td>
                                    <td class="font-display text-success"><?php echo $l['win_rate']; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">No leaderboard stats logged.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'teams'): ?>
        <!-- ==========================================
             TAB: TEAMS MANAGEMENT
             ========================================== -->
        <?php
        $allTeams = [];
        try {
            $allTeams = $pdo->query("SELECT t.*, u.name as captain_name, 
                                            (SELECT COUNT(*) FROM `team_members` tm WHERE tm.team_id = t.id) as member_count 
                                     FROM `teams` t 
                                     JOIN `users` u ON t.captain_id = u.id 
                                     ORDER BY t.id DESC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-people-group text-accent-purple me-2"></i>Registered Squad Teams</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>Team ID</th>
                            <th>Team Name</th>
                            <th>Captain</th>
                            <th>Roster Size</th>
                            <th>Date Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allTeams)): ?>
                            <?php foreach ($allTeams as $team): ?>
                                <tr>
                                    <td class="font-display text-muted">#<?php echo $team['id']; ?></td>
                                    <td><strong class="text-adaptive"><?php echo htmlspecialchars($team['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($team['captain_name']); ?></td>
                                    <td class="font-display"><?php echo $team['member_count']; ?> players</td>
                                    <td class="font-display"><?php echo date('Y-m-d', strtotime($team['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">No team rosters created yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'players'): ?>
        <!-- ==========================================
             TAB: PLAYERS ROSTER
             ========================================== -->
        <?php
        $allPlayers = [];
        try {
            $allPlayers = $pdo->query("SELECT u.*, w.balance, w.coins 
                                       FROM `users` u 
                                       LEFT JOIN `wallets` w ON u.id = w.user_id 
                                       WHERE u.role = 'customer' 
                                       ORDER BY u.id ASC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-users text-accent-cyan me-2"></i>Active Arena Competitors</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Player Name</th>
                            <th>Email Address</th>
                            <th>Coins Balance</th>
                            <th>Wallet Cash</th>
                            <th>Account Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allPlayers)): ?>
                            <?php foreach ($allPlayers as $player): ?>
                                <tr>
                                    <td class="font-display text-muted">#<?php echo $player['id']; ?></td>
                                    <td><strong class="text-adaptive"><?php echo htmlspecialchars($player['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($player['email']); ?></td>
                                    <td class="font-display text-warning fw-bold"><?php echo intval($player['coins']); ?> C</td>
                                    <td class="font-display text-success">₹<?php echo number_format($player['balance'] ?? 0.00, 2); ?></td>
                                    <td>
                                        <span class="badge badge-badge font-game"><?php echo htmlspecialchars($player['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">No registered players found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'results'): ?>
        <!-- ==========================================
             TAB: COMPLETED MATCH RESULTS
             ========================================== -->
        <?php
        $completedMatches = [];
        try {
            $completedMatches = $pdo->query("SELECT m.*, t.name as tournament_name, g.name as game_name,
                                                    p1.name as p1_name, p2.name as p2_name,
                                                    t1.name as team1_name, t2.name as team2_name
                                             FROM `matches` m
                                             JOIN `tournaments` t ON m.tournament_id = t.id
                                             JOIN `games` g ON t.game_id = g.id
                                             LEFT JOIN `users` p1 ON m.player1_id = p1.id
                                             LEFT JOIN `users` p2 ON m.player2_id = p2.id
                                             LEFT JOIN `teams` t1 ON m.team1_id = t1.id
                                             LEFT JOIN `teams` t2 ON m.team2_id = t2.id
                                             WHERE m.status = 'completed'
                                             ORDER BY m.scheduled_time DESC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-square-poll-vertical text-accent-cyan me-2"></i>Completed Match Standings</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-gaming">
                    <thead>
                        <tr>
                            <th>Match</th>
                            <th>Game Arena</th>
                            <th>Opponents</th>
                            <th>Final Scores</th>
                            <th>Match Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($completedMatches)): ?>
                            <?php foreach ($completedMatches as $m): ?>
                                <tr>
                                    <td class="text-muted">#<?php echo $m['id']; ?></td>
                                    <td><span class="badge badge-badge font-game"><?php echo htmlspecialchars($m['game_name']); ?></span></td>
                                    <td>
                                        <div class="fw-bold text-adaptive">
                                            <?php echo htmlspecialchars($m['team1_name'] ?? $m['p1_name'] ?? 'TBD'); ?> 
                                            <span class="text-secondary fw-normal">vs</span> 
                                            <?php echo htmlspecialchars($m['team2_name'] ?? $m['p2_name'] ?? 'TBD'); ?>
                                        </div>
                                    </td>
                                    <td class="font-display text-accent-cyan fw-bold"><?php echo $m['score1']; ?> - <?php echo $m['score2']; ?></td>
                                    <td class="font-display"><?php echo date('Y-m-d H:i', strtotime($m['scheduled_time'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">No completed match scores found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($tab === 'rewards'): ?>
        <!-- ==========================================
             TAB: REWARDS CONFIGURATION
             ========================================== -->
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_reward') {
            $rname = trim($_POST['reward_name'] ?? '');
            $rdesc = trim($_POST['reward_desc'] ?? '');
            $rtype = $_POST['reward_type'] ?? 'coins';
            $rval = floatval($_POST['reward_val'] ?? 0.00);
            
            if (!empty($rname)) {
                try {
                    $stmtRewardAdd = $pdo->prepare("INSERT INTO `rewards` (`name`, `description`, `type`, `value`) VALUES (?, ?, ?, ?)");
                    $stmtRewardAdd->execute([$rname, $rdesc, $rtype, $rval]);
                    $success_msg = "Reward catalog template created successfully.";
                } catch (PDOException $e) {
                    $error_msg = "Failed to add reward: " . $e->getMessage();
                }
            }
        }
        $rewardsList = [];
        try {
            $rewardsList = $pdo->query("SELECT * FROM `rewards` ORDER BY `id` DESC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-gift text-accent-purple me-2"></i>Create New Reward</h5>
                    <form method="POST" action="dashboard.php?tab=rewards">
                        <input type="hidden" name="action" value="create_reward">
                        <div class="mb-3">
                            <label class="form-label-custom">Reward Name</label>
                            <input type="text" name="reward_name" class="form-control form-control-custom" placeholder="e.g. Master Champion Coins" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Description</label>
                            <textarea name="reward_desc" rows="2" class="form-control form-control-custom" placeholder="State criteria..."></textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Type</label>
                                <select name="reward_type" class="form-control form-control-custom">
                                    <option value="coins">Coins</option>
                                    <option value="badge">Badge/Trophy</option>
                                    <option value="prize">Physical Prize/Cash</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Value (if coins/cash)</label>
                                <input type="number" name="reward_val" class="form-control form-control-custom" value="100">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-gaming-purple w-100 py-2"><i class="fa-solid fa-circle-check me-2"></i>Create Reward Vibe</button>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game">Platform Reward Templates</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-gaming">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Value</th>
                                    <th>Date Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rewardsList)): ?>
                                    <?php foreach ($rewardsList as $rw): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-adaptive"><?php echo htmlspecialchars($rw['name']); ?></div>
                                                <small class="text-secondary"><?php echo htmlspecialchars($rw['description']); ?></small>
                                            </td>
                                            <td><span class="badge badge-badge font-game"><?php echo htmlspecialchars($rw['type']); ?></span></td>
                                            <td class="font-display text-accent-cyan fw-bold"><?php echo intval($rw['value']); ?></td>
                                            <td class="font-display text-muted"><?php echo date('Y-m-d', strtotime($rw['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'announcements'): ?>
        <!-- ==========================================
             TAB: ANNOUNCEMENTS MANAGEMENT
             ========================================== -->
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_announcement') {
            $atitle = trim($_POST['title'] ?? '');
            $acontent = trim($_POST['content'] ?? '');
            
            if (!empty($atitle) && !empty($acontent)) {
                try {
                    $stmtAnnAdd = $pdo->prepare("INSERT INTO `announcements` (`title`, `content`, `created_by`, `status`) VALUES (?, ?, ?, 'active')");
                    $stmtAnnAdd->execute([$atitle, $acontent, $_SESSION['user_id']]);
                    $success_msg = "Platform announcement published successfully.";
                } catch (PDOException $e) {
                    $error_msg = "Publishing failure: " . $e->getMessage();
                }
            }
        }
        $allAnnouncements = [];
        try {
            $allAnnouncements = $pdo->query("SELECT a.*, u.name as author_name 
                                             FROM `announcements` a 
                                             JOIN `users` u ON a.created_by = u.id 
                                             ORDER BY a.created_at DESC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-bullhorn text-accent-cyan me-2"></i>Publish News / Advisory</h5>
                    <form method="POST" action="dashboard.php?tab=announcements">
                        <input type="hidden" name="action" value="create_announcement">
                        <div class="mb-3">
                            <label class="form-label-custom">Announcement Title</label>
                            <input type="text" name="title" class="form-control form-control-custom" placeholder="e.g. Major Patch Rollout" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Message Content</label>
                            <textarea name="content" rows="4" class="form-control form-control-custom" placeholder="Write full news details..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-gaming-cyan w-100 py-2"><i class="fa-solid fa-paper-plane me-2"></i>Post Announcement</button>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game">Announcement History</h5>
                    <div class="timeline-gaming">
                        <?php if (!empty($allAnnouncements)): ?>
                            <?php foreach ($allAnnouncements as $ann): ?>
                                <div class="timeline-gaming-item success">
                                    <div class="fw-bold text-adaptive font-game"><?php echo htmlspecialchars($ann['title']); ?></div>
                                    <div class="text-secondary fs-8 mt-1 mb-2"><?php echo htmlspecialchars($ann['content']); ?></div>
                                    <small class="text-muted font-display">Posted by <?php echo htmlspecialchars($ann['author_name']); ?> on <?php echo $ann['created_at']; ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($tab === 'reports'): ?>
        <!-- ==========================================
             TAB: ANALYTICAL REPORTS
             ========================================== -->
        <?php
        $reportStats = [];
        try {
            $reportStats['total_games'] = $pdo->query("SELECT COUNT(*) FROM `games`")->fetchColumn();
            $reportStats['total_tournaments'] = $pdo->query("SELECT COUNT(*) FROM `tournaments`")->fetchColumn();
            $reportStats['total_matches'] = $pdo->query("SELECT COUNT(*) FROM `matches`")->fetchColumn();
            $reportStats['total_teams'] = $pdo->query("SELECT COUNT(*) FROM `teams`")->fetchColumn();
            $reportStats['live_matches'] = $pdo->query("SELECT COUNT(*) FROM `matches` WHERE `status` = 'live'")->fetchColumn();
            $reportStats['completed_matches'] = $pdo->query("SELECT COUNT(*) FROM `matches` WHERE `status` = 'completed'")->fetchColumn();
        } catch (PDOException $e) {}
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-chart-pie text-accent-purple me-2"></i>Platform E-Sports Audit Summary</h5>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <h3>Total Arenas</h3>
                            <p class="font-display"><?php echo intval($reportStats['total_games']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <h3>Tournaments Held</h3>
                            <p class="font-display"><?php echo intval($reportStats['total_tournaments']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <h3>Matches Scheduled</h3>
                            <p class="font-display"><?php echo intval($reportStats['total_matches']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-card-info">
                            <h3>Registered Teams</h3>
                            <p class="font-display"><?php echo intval($reportStats['total_teams']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
                <h6 class="text-adaptive fw-bold mb-3 font-game">Match Status Breakdown</h6>
                <div class="progress mb-2" style="height: 20px; background-color: var(--bg-tertiary);">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo ($reportStats['total_matches'] > 0) ? ($reportStats['live_matches'] / $reportStats['total_matches'] * 100) : 0; ?>%" aria-valuenow="15" aria-valuemin="0" aria-valuemax="100">Live</div>
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo ($reportStats['total_matches'] > 0) ? ($reportStats['completed_matches'] / $reportStats['total_matches'] * 100) : 0; ?>%" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100">Completed</div>
                </div>
                <small class="text-secondary">Red: Live Matches, Green: Completed Matches.</small>
            </div>
        </div>

    <?php elseif ($tab === 'profile'): ?>
        <!-- ==========================================
             TAB: ADMIN PROFILE EDIT
             ========================================== -->
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
            $aname = trim($_POST['name'] ?? '');
            $apass = $_POST['password'] ?? '';
            
            if (!empty($aname)) {
                try {
                    if (!empty($apass)) {
                        $ahash = password_hash($apass, PASSWORD_DEFAULT);
                        $stmtProfile = $pdo->prepare("UPDATE `users` SET `name` = ?, `password` = ? WHERE `id` = ?");
                        $stmtProfile->execute([$aname, $ahash, $_SESSION['user_id']]);
                    } else {
                        $stmtProfile = $pdo->prepare("UPDATE `users` SET `name` = ? WHERE `id` = ?");
                        $stmtProfile->execute([$aname, $_SESSION['user_id']]);
                    }
                    $_SESSION['user_name'] = $aname;
                    $user_name = $aname;
                    $success_msg = "Admin profile updated successfully.";
                } catch (PDOException $e) {
                    $error_msg = "Failed to update profile: " . $e->getMessage();
                }
            }
        }
        ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-adaptive mb-3 font-game"><i class="fa-solid fa-user-check text-accent-cyan me-2"></i>Admin Account Configuration</h5>
            <p class="text-secondary fs-8 mb-4">View and configure admin access credentials.</p>
            
            <form method="POST" action="dashboard.php?tab=profile">
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">Admin Display Name</label>
                        <input type="text" name="name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($user_name); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Account Password (Leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control form-control-custom" placeholder="••••••••">
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-gaming-cyan px-4 py-2"><i class="fa-solid fa-floppy-disk me-2"></i>Update Admin Credentials</button>
                    </div>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- ==========================================
             TAB: OVERVIEW DEFAULT
             ========================================== -->
        <?php
        // Fetch pending registrations count
        $regsList = [];
        try {
            $regsList = $pdo->query("SELECT tr.*, u.name as user_name, t.name as tournament_name, t.type as tour_type, team.name as team_name
                                     FROM `tournament_registrations` tr
                                     JOIN `users` u ON tr.user_id = u.id
                                     JOIN `tournaments` t ON tr.tournament_id = t.id
                                     LEFT JOIN `teams` team ON tr.team_id = team.id
                                     WHERE tr.status = 'pending'
                                     ORDER BY tr.created_at ASC")->fetchAll();
        } catch (PDOException $e) {}
        ?>
        <div class="row g-4 mb-5">
            <!-- Left Panel: Pending Registrations -->
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-4 font-game"><i class="fa-solid fa-clock text-accent-cyan me-2"></i>Pending Roster Approvals</h5>
                    
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-gaming">
                            <thead>
                                <tr>
                                    <th>Competitor</th>
                                    <th>Tournament Target</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($regsList)): ?>
                                    <?php foreach ($regsList as $reg): ?>
                                        <?php 
                                        $competitor = $reg['tour_type'] === 'team' ? $reg['team_name'] : $reg['user_name'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-adaptive"><?php echo htmlspecialchars($competitor); ?></div>
                                                <small class="text-secondary"><?php echo $reg['tour_type'] === 'team' ? 'Captain: ' . htmlspecialchars($reg['user_name']) : 'Solo Competitor'; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($reg['tournament_name']); ?></td>
                                            <td class="text-end">
                                                <form method="POST" action="dashboard.php" class="d-inline">
                                                    <input type="hidden" name="action" value="approve_registration">
                                                    <input type="hidden" name="reg_id" value="<?php echo $reg['id']; ?>">
                                                    <button type="submit" class="btn btn-gaming-cyan btn-sm py-1 px-3">Approve</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-secondary py-4">No registrations require approval. Ready to play!</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Performance Charts -->
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-adaptive mb-3 font-game"><i class="fa-solid fa-chart-simple text-accent-purple me-2"></i>Platform Registration Statistics</h5>
                    <p class="text-secondary fs-8 mb-4">Live analytics distribution representing tournament registrations volume.</p>
                    
                    <div style="height: 250px; position: relative;">
                        <canvas id="adminPerformanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Tournament Modal -->
<div class="modal fade" id="createTournamentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-secondary border-0" style="border: 1px solid var(--card-border) !important;">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title text-adaptive font-game">Create New Tournament</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="dashboard.php?tab=tournaments">
                <input type="hidden" name="action" value="create_tournament">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label-custom">Tournament Name</label>
                        <input type="text" name="name" class="form-control form-control-custom" placeholder="e.g. Valorant Showdown Season 2" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label-custom">Game Category</label>
                            <select class="form-control form-control-custom" name="game_id" required>
                                <?php
                                try {
                                    $gamesList = $pdo->query("SELECT id, name FROM games")->fetchAll();
                                    foreach ($gamesList as $g) {
                                        echo "<option value='$g[id]'>$g[name]</option>";
                                    }
                                } catch (PDOException $e) {}
                                ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">Format Type</label>
                            <select class="form-control form-control-custom" name="type" required>
                                <option value="solo">Solo Matchmaking</option>
                                <option value="team">Squad Matchmaking</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label-custom">Start Date & Time</label>
                            <input type="datetime-local" name="start_date" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">Max Participants</label>
                            <input type="number" name="max_participants" min="2" max="128" value="8" class="form-control form-control-custom" required>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label-custom">Prize Pool (USD)</label>
                            <input type="number" step="100" name="prize_pool" min="0" value="1000" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom">Entry Fee (USD)</label>
                            <input type="number" step="5" name="entry_fee" min="0" value="10" class="form-control form-control-custom" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-gaming-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gaming-purple btn-sm">Launch Tournament</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctxAdmin = document.getElementById('adminPerformanceChart');
        if (ctxAdmin) {
            new Chart(ctxAdmin.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['CS2 Masters', 'Valorant Cup', 'EA FC Kickoff', 'BGMI Clash'],
                    datasets: [{
                        label: 'Approved Entries',
                        data: [4, 8, 4, 16],
                        backgroundColor: '#a855f7',
                        borderColor: '#a855f7',
                        borderRadius: 6,
                        barThickness: 30
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#9ca3af' } },
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
