<?php
// index.php
// Premium Landing Page for Clash Arena – E-Sports Tournament Platform

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

// If already logged in, show a quick link to dashboard
$dashboardLink = '';
if (isLoggedIn()) {
    $dashboardLink = getDashboardUrl($_SESSION['user_role']);
}

// Fetch upcoming and live tournaments
$tournaments = [];
try {
    $stmtT = $pdo->query("SELECT t.*, g.name as game_name, g.slug as game_slug 
                          FROM `tournaments` t 
                          JOIN `games` g ON t.game_id = g.id 
                          WHERE t.status IN ('live', 'registration_open', 'upcoming') 
                          ORDER BY t.start_date ASC LIMIT 6");
    $tournaments = $stmtT->fetchAll();
} catch (PDOException $e) {
    // Fallback
}

// Fetch all popular games
$games = [];
try {
    $stmtG = $pdo->query("SELECT * FROM `games` ORDER BY `id` ASC LIMIT 8");
    $games = $stmtG->fetchAll();
} catch (PDOException $e) {
    // Fallback
}

// Fetch live leaderboard preview
$leaderboard = [];
try {
    $stmtL = $pdo->query("SELECT l.*, u.name as player_name, g.name as game_name, t.name as team_name
                          FROM `leaderboard` l 
                          LEFT JOIN `users` u ON l.user_id = u.id 
                          LEFT JOIN `teams` t ON l.team_id = t.id
                          JOIN `games` g ON l.game_id = g.id 
                          ORDER BY l.points DESC LIMIT 5");
    $leaderboard = $stmtL->fetchAll();
} catch (PDOException $e) {
    // Fallback
}

// Game slug to image URL mapping
$gameImages = [
    'valorant' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=600&q=80',
    'cs2' => 'https://images.unsplash.com/photo-1553481187-be93c21490a9?auto=format&fit=crop&w=600&q=80',
    'bgmi' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=600&q=80',
    'freefire' => 'https://images.unsplash.com/photo-1593305841991-05c297ba4575?auto=format&fit=crop&w=600&q=80',
    'eafc' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=600&q=80',
    'rocketleague' => 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=600&q=80',
    'pubgpc' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&w=600&q=80',
    'codm' => 'https://images.unsplash.com/photo-1627856013091-fed6e4e30025?auto=format&fit=crop&w=600&q=80'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clash Arena — E-Sports Tournament Platform</title>
    <meta name="description" content="Compete in premium Valorant, CS2, BGMI, and EA FC tournaments. Build your teams, conquer brackets, and win prize pools.">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- App Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <style>
        /* Specific landing page layout tweaks */
        .faq-accordion .accordion-item {
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            border-radius: 12px;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .faq-accordion .accordion-button {
            font-weight: 600;
            color: var(--text-primary);
            background: transparent;
            padding: 20px;
        }
        .faq-accordion .accordion-button:not(.collapsed) {
            background-color: rgba(168, 85, 247, 0.1);
            color: var(--accent-cyan);
            box-shadow: none;
        }
        .testimonial-card {
            border-radius: 16px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 30px;
        }
        .process-step {
            position: relative;
        }
        .process-step::after {
            content: '\f105';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: -15px;
            top: 30%;
            font-size: 2rem;
            color: var(--card-border);
        }
        @media (max-width: 767.98px) {
            .process-step::after {
                display: none;
            }
        }
    </style>
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body>

    <!-- Sticky Navigation -->
    <nav class="navbar navbar-expand-lg landing-navbar fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#"><img src="assets/images/logo.jpg" alt="Logo" class="me-2" style="height: 38px; width: auto; border-radius: 6px;">CLASH<span>ARENA</span></a>
            <button class="navbar-toggler border-0 text-adaptive" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav" aria-controls="landingNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>
            <div class="collapse navbar-collapse" id="landingNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#tournaments">Tournaments</a></li>
                    <li class="nav-item"><a class="nav-link" href="#games">Games</a></li>
                    <li class="nav-item"><a class="nav-link" href="#leaderboard">Leaderboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="#process">Process</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <button class="theme-toggle-btn shadow-none me-1" type="button" aria-label="Toggle Theme">
                        <i class="fa-solid fa-sun"></i>
                    </button>
                    <?php if ($dashboardLink): ?>
                        <a href="<?php echo htmlspecialchars($dashboardLink); ?>" class="btn btn-gaming-purple btn-sm text-nowrap"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-gaming-cyan btn-sm text-nowrap"><i class="fa-solid fa-arrow-right-to-bracket me-2 text-dark"></i>Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-wrapper" id="home">
        <div class="container">
            <div class="row align-items-center g-5">
                <!-- Hero Left Content -->
                <div class="col-lg-6 text-start">
                    <div class="badge bg-opacity-25 bg-purple text-accent-cyan px-3 py-2 rounded-pill mb-3 fw-bold border border-info border-opacity-25 font-game" style="background-color: rgba(6, 182, 212, 0.1);"><i class="fa-solid fa-fire me-2"></i>Next-Gen E-Sports Hub</div>
                    <h1 class="hero-title">
                        Compete. Conquer.<br>
                        <span>Become a Champion.</span>
                    </h1>
                    <p class="hero-subtitle text-secondary">
                        Welcome to Clash Arena, the definitive E-Sports tournament system. Team up with teammates, participate in automated single-elimination brackets, track your points on live leaderboards, and claim prize pools.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#tournaments" class="btn btn-gaming-purple py-3 px-4"><i class="fa-solid fa-gamepad me-2"></i>Join Tournament</a>
                        <a href="#games" class="btn btn-gaming-outline py-3 px-4">Explore Games</a>
                    </div>
                </div>
                
                <!-- Hero Right Illustration -->
                <div class="col-lg-6 text-center">
                    <div class="position-relative d-inline-block">
                        <!-- Premium visual mockup representation -->
                        <div class="glass-card p-4 mx-auto floating-illustration" style="max-width: 480px; border-radius: 20px;">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom border-secondary border-opacity-25">
                                <span class="rounded-circle bg-danger" style="width: 12px; height: 12px;"></span>
                                <span class="rounded-circle bg-warning" style="width: 12px; height: 12px;"></span>
                                <span class="rounded-circle bg-success" style="width: 12px; height: 12px;"></span>
                                <span class="ms-auto badge bg-dark bg-opacity-30 text-accent-cyan fs-8 font-game">VALORANT_FINALS.EXE</span>
                            </div>
                            
                            <div class="text-start mb-4">
                                <div class="h6 fw-bold mb-1 text-adaptive font-game">VALORANT CHAMPIONS CUP</div>
                                <div class="text-secondary fs-8">Automated single-elimination bracket round 2</div>
                            </div>
                            
                            <!-- Custom mockup graphic bracket diagram -->
                            <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: rgba(168, 85, 247, 0.1); border: 1px solid var(--accent-purple-glow);">
                                <div class="text-start">
                                    <div class="fw-bold font-game text-adaptive" style="font-size: 0.9rem;"><i class="fa-solid fa-shield-halved me-2 text-accent-cyan"></i>Sentinels Alpha</div>
                                    <div class="fw-medium font-game" style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.55);"><i class="fa-solid fa-shield-halved me-2" style="opacity: 0.5;"></i>Fnatic Storm</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge font-game" style="background: rgba(220, 53, 69, 0.15); color: #ff6b6b; border: 1px solid rgba(220, 53, 69, 0.4);">LIVE IN 5m</span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background: rgba(6, 182, 212, 0.05); border: 1px solid var(--card-border-cyan);">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-trophy text-warning"></i>
                                    <span class="fw-bold font-game fs-7 text-adaptive">PRIZE POOL: ₹5,000.00</span>
                                </div>
                                <span class="badge bg-info bg-opacity-10 text-accent-cyan font-game">Tier 1</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Cumulative Statistics Section -->
    <section class="border-top border-bottom border-secondary border-opacity-25 py-5" style="background: var(--bg-secondary);">
        <div class="container">
            <div class="row text-center g-4">
                <div class="col-md-3">
                    <div class="display-6 fw-extrabold text-accent-purple font-display">₹19,500</div>
                    <div class="text-secondary fw-semibold mt-1 font-game text-uppercase">Cumulative Prize Pools</div>
                </div>
                <div class="col-md-3">
                    <div class="display-6 fw-extrabold text-accent-cyan font-display">8+</div>
                    <div class="text-secondary fw-semibold mt-1 font-game text-uppercase">Elite Games Supported</div>
                </div>
                <div class="col-md-3">
                    <div class="display-6 fw-extrabold text-success font-display">120+</div>
                    <div class="text-secondary fw-semibold mt-1 font-game text-uppercase">Matches Played Weekly</div>
                </div>
                <div class="col-md-3">
                    <div class="display-6 fw-extrabold text-warning font-display">100%</div>
                    <div class="text-secondary fw-semibold mt-1 font-game text-uppercase">Fair-Play Guarantee</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Upcoming Tournaments Section -->
    <section class="py-5" id="tournaments">
        <div class="container text-center">
            <div class="max-width-600 mx-auto mb-5">
                <span class="text-accent-cyan fw-bold text-uppercase font-game" style="font-size: 0.85rem; letter-spacing: 0.15em;">Join the Fight</span>
                <h2 class="fw-bold mt-2 text-adaptive font-display">Active & Upcoming Leagues</h2>
                <p class="text-secondary">Register solo or with your team before the registration grids close.</p>
            </div>
            
            <div class="row g-4">
                <?php if (!empty($tournaments)): ?>
                    <?php foreach ($tournaments as $t): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="glass-card p-4 text-start h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <?php
                                        $statusClass = 'badge-superadmin';
                                        $statusText = str_replace('_', ' ', $t['status']);
                                        if ($t['status'] === 'live') {
                                            $statusClass = 'badge-superadmin';
                                        } elseif ($t['status'] === 'registration_open') {
                                            $statusClass = 'badge-player';
                                        } elseif ($t['status'] === 'upcoming') {
                                            $statusClass = 'badge-admin';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?> font-game" style="text-transform: uppercase;">
                                            <?php echo htmlspecialchars($statusText); ?>
                                        </span>
                                        <span class="text-accent-cyan font-game fw-bold" style="font-size: 0.9rem; text-transform: uppercase;">
                                            <i class="fa-solid fa-gamepad me-1"></i> <?php echo htmlspecialchars($t['game_name']); ?>
                                        </span>
                                    </div>
                                    <h4 class="fw-bold mb-3 text-adaptive"><?php echo htmlspecialchars($t['name']); ?></h4>
                                    <ul class="list-unstyled text-secondary fs-8 mb-4">
                                         <li class="mb-2"><i class="fa-solid fa-sitemap text-accent-purple me-2"></i> Format: <?php echo ucfirst(htmlspecialchars($t['type'])); ?> Matchmaking</li>
                                         <li class="mb-2"><i class="fa-solid fa-trophy text-warning me-2"></i> Prize Pool: <strong>₹<?php echo number_format($t['prize_pool'], 2); ?></strong></li>
                                         <li class="mb-2"><i class="fa-solid fa-ticket text-accent-cyan me-2"></i> Entry Fee: <?php echo $t['entry_fee'] > 0 ? '₹' . number_format($t['entry_fee'], 2) : 'FREE'; ?></li>
                                        <li><i class="fa-solid fa-calendar-check text-success me-2"></i> Starts: <?php echo date('M d, Y H:i', strtotime($t['start_date'])); ?></li>
                                    </ul>
                                </div>
                                <a href="login.php" class="btn btn-gaming-purple w-100 py-2">
                                    <i class="fa-solid fa-right-to-bracket me-2"></i> Register Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="p-4 bg-secondary bg-opacity-35 rounded border border-secondary text-center text-muted">
                            No active tournaments found at the moment.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Popular Games Section -->
    <section class="py-5" id="games" style="background-color: var(--bg-secondary);">
        <div class="container text-center">
            <div class="max-width-600 mx-auto mb-5">
                <span class="text-accent-purple fw-bold text-uppercase font-game" style="font-size: 0.85rem; letter-spacing: 0.15em;">Supported Arenas</span>
                <h2 class="fw-bold mt-2 text-adaptive font-display">Popular E-Sports Games</h2>
                <p class="text-secondary">Conquer different game titles with customized rulesets and tournament frameworks.</p>
            </div>
            
            <div class="row g-4">
                <?php if (!empty($games)): ?>
                    <?php foreach ($games as $g): ?>
                        <div class="col-lg-3 col-md-6 col-sm-6">
                            <div class="glass-card-cyan p-3 text-start h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <?php 
                                    $imgUrl = $gameImages[$g['slug']] ?? '';
                                    if ($imgUrl): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($g['name']); ?>" class="w-100 rounded-3 mb-3" style="height: 140px; object-fit: cover; border: 1px solid var(--card-border-cyan);" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="h-40 rounded-3 mb-3 bg-secondary bg-opacity-50 border border-secondary border-opacity-25 align-items-center justify-content-center text-adaptive" style="height: 140px; background: radial-gradient(circle, var(--accent-cyan-glow) 0%, transparent 100%); display: none;">
                                            <i class="fa-solid fa-gamepad display-5 text-accent-cyan"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="h-40 rounded-3 mb-3 bg-secondary bg-opacity-50 border border-secondary border-opacity-25 d-flex align-items-center justify-content-center text-adaptive" style="height: 140px; background: radial-gradient(circle, var(--accent-cyan-glow) 0%, transparent 100%);">
                                            <i class="fa-solid fa-gamepad display-5 text-accent-cyan"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="fw-bold mb-2 text-adaptive"><?php echo htmlspecialchars($g['name']); ?></h5>
                                    <p class="text-secondary fs-8 mb-3" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars($g['rules']); ?></p>
                                </div>
                                <div class="border-top border-secondary border-opacity-25 pt-2 d-flex justify-content-between align-items-center">
                                    <span class="fs-8" style="color: rgba(255, 255, 255, 0.75);">Prize: ₹<?php echo number_format($g['prize_pool']); ?></span>
                                    <span class="badge badge-badge font-game">Fee: ₹<?php echo intval($g['entry_fee']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Leaderboard Preview Section -->
    <section class="py-5" id="leaderboard">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5">
                    <span class="text-accent-cyan fw-bold text-uppercase font-game" style="font-size: 0.85rem; letter-spacing: 0.15em;">Dynamic Standings</span>
                    <h2 class="fw-bold text-adaptive font-display mt-2 mb-4">Live Arena Leaderboard</h2>
                    <p class="text-secondary mb-4">Review the current top-performing players across the Clash Arena workspace. Points are dynamically calculated based on registered bracket tournament wins and MVP performance scores.</p>
                    <a href="login.php" class="btn btn-gaming-outline py-2 px-4"><i class="fa-solid fa-chart-simple me-2"></i>View Full Leaderboards</a>
                </div>
                
                <div class="col-lg-7">
                    <div class="glass-card p-4 shadow-lg">
                        <h5 class="fw-bold mb-3 text-adaptive font-game"><i class="fa-solid fa-ranking-star me-2 text-accent-cyan"></i>Top Fragger Standings</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 text-adaptive table-gaming" style="font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Player Profile</th>
                                        <th>Game Arena</th>
                                        <th>Win Rate</th>
                                        <th class="text-end">Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($leaderboard)): ?>
                                        <?php $rank = 1; foreach ($leaderboard as $l): ?>
                                            <tr>
                                                <td class="font-display fw-bold text-accent-cyan">#<?php echo $rank++; ?></td>
                                                <td>
                                                    <div class="fw-semibold text-adaptive">
                                                        <?php echo htmlspecialchars($l['player_name'] ?? 'Guest Roster'); ?>
                                                        <?php if ($l['team_name']): ?>
                                                            <small class="d-block text-muted" style="font-size: 0.7rem;">[<?php echo htmlspecialchars($l['team_name']); ?>]</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="text-secondary font-game"><?php echo htmlspecialchars($l['game_name']); ?></td>
                                                <td class="text-success font-display"><?php echo $l['win_rate']; ?>%</td>
                                                <td class="font-display text-end text-adaptive fw-bold"><?php echo number_format($l['points']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No standings recorded yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tournament Process Section -->
    <section class="py-5 bg-secondary bg-opacity-10" id="process" style="background-color: var(--bg-secondary);">
        <div class="container text-center">
            <div class="max-width-600 mx-auto mb-5">
                <span class="text-accent-purple fw-bold text-uppercase font-game" style="font-size: 0.85rem; letter-spacing: 0.15em;">How it Works</span>
                <h2 class="fw-bold mt-2 text-adaptive font-display">The Tournament Process</h2>
                <p class="text-secondary">Get started in under 5 minutes with our automated bracket system.</p>
            </div>
            
            <div class="row g-4 mt-2">
                <div class="col-md-3 process-step">
                    <div class="glass-card p-4 h-100">
                        <div class="stat-card-icon primary mx-auto mb-3"><i class="fa-solid fa-user-plus"></i></div>
                        <h5 class="fw-bold text-adaptive mb-2">1. Register Account</h5>
                        <p class="text-secondary fs-8 mb-0">Create a secure profile and claim your initial welcome coins.</p>
                    </div>
                </div>
                <div class="col-md-3 process-step">
                    <div class="glass-card p-4 h-100">
                        <div class="stat-card-icon secondary mx-auto mb-3"><i class="fa-solid fa-people-group"></i></div>
                        <h5 class="fw-bold text-adaptive mb-2">2. Form Team</h5>
                        <p class="text-secondary fs-8 mb-0">Create your squad, invite other players, and confirm rosters.</p>
                    </div>
                </div>
                <div class="col-md-3 process-step">
                    <div class="glass-card p-4 h-100">
                        <div class="stat-card-icon success mx-auto mb-3"><i class="fa-solid fa-code-fork"></i></div>
                        <h5 class="fw-bold text-adaptive mb-2">3. Join Bracket</h5>
                        <p class="text-secondary fs-8 mb-0">Admin approves rosters and generates the single elimination grid.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="glass-card p-4 h-100">
                        <div class="stat-card-icon warning mx-auto mb-3"><i class="fa-solid fa-gift"></i></div>
                        <h5 class="fw-bold text-adaptive mb-2">4. Claim Prize</h5>
                        <p class="text-secondary fs-8 mb-0">Play matches, submit scores, and earn automatic wallet coins.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5">
        <div class="container text-center">
            <div class="max-width-600 mx-auto mb-5">
                <span class="text-accent-cyan fw-bold text-uppercase font-game" style="font-size: 0.85rem; letter-spacing: 0.15em;">Pro Endorsements</span>
                <h2 class="fw-bold mt-2 text-adaptive font-display">Endorsed by Pro Players</h2>
                <p class="text-secondary">See how Clash Arena helps competitive gaming organizations run brackets.</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="testimonial-card text-start h-100 d-flex flex-column justify-content-between">
                        <p class="fst-italic text-secondary">
                            "The automated single elimination bracket generator is outstanding. We hosted our first regional 16-team Valorant tournament, and scores were calculated flawlessly."
                        </p>
                        <div class="d-flex align-items-center gap-3 mt-4">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-dark" style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--accent-purple) 0%, var(--accent-cyan) 100%);">
                                Sh
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-adaptive">Mike Shroud</h6>
                                <small class="text-accent-cyan font-game">Pro Streamer & Competitor</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="testimonial-card text-start h-100 d-flex flex-column justify-content-between">
                        <p class="fst-italic text-secondary">
                            "Having wallet balances connected directly to tournament wins makes it feel incredibly high-stakes. Instantly credits coins on bracket finalizations."
                        </p>
                        <div class="d-flex align-items-center gap-3 mt-4">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-dark" style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--accent-purple) 0%, var(--accent-cyan) 100%);">
                                Fa
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-adaptive">Lee Faker</h6>
                                <small class="text-accent-cyan font-game">Mid Laner, T1 Legends</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-5 bg-secondary bg-opacity-10" id="faq" style="background-color: var(--bg-secondary);">
        <div class="container" style="max-width: 800px;">
            <div class="text-center mb-5">
                <span class="text-accent-purple fw-bold text-uppercase font-game" style="font-size: 0.85rem; letter-spacing: 0.15em;">Answers Portal</span>
                <h2 class="fw-bold mt-2 text-adaptive font-display">Frequently Asked Questions</h2>
                <p class="text-secondary">Get instant answers regarding rules, fees, and wallets.</p>
            </div>
            
            <div class="accordion faq-accordion mt-4" id="faqAccordion">
                <!-- FAQ 1 -->
                <div class="accordion-item">
                    <h3 class="accordion-header" id="headingOne">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                            How do I register for a tournament?
                        </button>
                    </h3>
                    <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-adaptive opacity-75">
                            You must log in to your Player Dashboard, navigate to the "Tournaments" tab, and click "Register Solo" or choose a team from your roster profile. If the tournament requires an entry fee, it will be automatically debited from your wallet coins balance.
                        </div>
                    </div>
                </div>
                
                <!-- FAQ 2 -->
                <div class="accordion-item">
                    <h3 class="accordion-header" id="headingTwo">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                            How are brackets generated?
                        </button>
                    </h3>
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-adaptive opacity-75">
                            Brackets are generated automatically by administrators once registration closes. Our Single Elimination algorithm pairs approved teams/players in the scheduled round matching matrices. Winner nodes progress until a champion is declared.
                        </div>
                    </div>
                </div>
                
                <!-- FAQ 3 -->
                <div class="accordion-item">
                    <h3 class="accordion-header" id="headingThree">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                            What are default test logins?
                        </button>
                    </h3>
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-adaptive opacity-75">
                            Verify and explore the three custom dashboards using these seeded credentials (password: <strong>password123</strong>):
                            <ul class="mb-0 mt-2">
                                <li>Super Admin Portal: <code>superadmin@app.com</code></li>
                                <li>Admin Console: <code>admin@app.com</code></li>
                                <li>Player Dashboard: <code>customer@app.com</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-5" id="contact">
        <div class="container" style="max-width: 900px;">
            <div class="row g-5">
                <div class="col-md-5">
                    <span class="text-accent-cyan fw-bold text-uppercase font-game" style="font-size: 0.85rem; letter-spacing: 0.15em;">Support Hub</span>
                    <h3 class="fw-bold mt-2 mb-4 text-adaptive font-display">Get In Touch</h3>
                    <p class="text-secondary mb-4">Have questions about setting up customized tournaments, brackets, or API keys? Shoot us a message.</p>
                    
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-card-icon primary"><i class="fa-regular fa-envelope"></i></div>
                        <div>
                            <div class="fw-bold text-adaptive font-game" style="font-size: 0.9rem;">Email Support</div>
                            <a href="mailto:support@clasharena.com" class="text-secondary text-decoration-none">support@clasharena.com</a>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-card-icon secondary"><i class="fa-solid fa-location-dot"></i></div>
                        <div>
                            <div class="fw-bold text-adaptive font-game" style="font-size: 0.9rem;">HQ Office</div>
                            <span class="text-secondary">E-Sports Boulevard, California</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-7">
                    <div class="glass-card p-4 shadow-lg">
                        <form onsubmit="event.preventDefault(); document.getElementById('contact-success').style.display='block'; this.reset();">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Your Gamertag</label>
                                    <input type="text" class="form-control form-control-custom" required placeholder="e.g. Shroud">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Email Address</label>
                                    <input type="email" class="form-control form-control-custom" required placeholder="name@email.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label-custom">Enquiry Details</label>
                                    <textarea class="form-control form-control-custom" rows="4" required placeholder="State your tournament query..."></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-gaming-purple w-100 py-3">Send Enquiry</button>
                                </div>
                            </div>
                        </form>
                        <div class="alert alert-gaming alert-success mt-3 fw-semibold text-center" id="contact-success" style="display: none; font-size: 0.85rem;"><i class="fa-solid fa-circle-check me-2"></i>Thank you! Your ticket enquiry has been filed.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Professional Premium Footer -->
    <footer class="custom-footer">
        <div class="container">
            <div class="row g-5">
                <!-- Column 1: Brand Info -->
                <div class="col-md-4">
                    <div class="footer-brand font-display d-flex align-items-center"><img src="assets/images/logo.jpg" alt="Logo" class="me-2" style="height: 38px; width: auto; border-radius: 6px;">CLASH<span>ARENA</span></div>
                    <p class="footer-desc">An executive-grade tournament dashboard console celebrating fair e-sports matchmaking, real-time single-elimination brackets, and wallet transactions.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="btn btn-dark btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fa-brands fa-x-twitter fs-5 text-adaptive"></i></a>
                        <a href="#" class="btn btn-dark btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fa-brands fa-discord fs-5 text-adaptive"></i></a>
                        <a href="#" class="btn btn-dark btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fa-brands fa-youtube fs-5 text-adaptive"></i></a>
                    </div>
                </div>
                
                <!-- Column 2: Navigation -->
                <div class="col-md-2 col-6">
                    <div class="footer-title">Navigation</div>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#tournaments">Tournaments</a></li>
                        <li><a href="#games">Games</a></li>
                        <li><a href="#leaderboard">Leaderboard</a></li>
                    </ul>
                </div>
                
                <!-- Column 3: Resources -->
                <div class="col-md-3 col-6">
                    <div class="footer-title">Access Gates</div>
                    <ul class="footer-links">
                        <li><a href="login.php">Shared Portal Gate</a></li>
                        <li><a href="superadmin/dashboard.php">Super Admin Space</a></li>
                        <li><a href="admin/dashboard.php">Admin Space</a></li>
                        <li><a href="user/dashboard.php">Player Space</a></li>
                    </ul>
                </div>
                
                <!-- Column 4: Contact info -->
                <div class="col-md-3">
                    <div class="footer-title">Administration</div>
                    <ul class="footer-links">
                        <li><a href="mailto:support@clasharena.com"><i class="fa-regular fa-envelope me-2 text-accent-cyan"></i>support@clasharena.com</a></li>
                        <li><span class="text-secondary"><i class="fa-solid fa-headset me-2 text-accent-cyan"></i>Live Ticket Support</span></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom d-flex flex-column flex-sm-row justify-content-between align-items-center">
                <p class="mb-2 mb-sm-0">&copy; 2026 CLASH ARENA. All rights reserved.</p>
                <p class="mb-0 text-secondary">Designed for local XAMPP & production environment compatibility.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Custom Main JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
