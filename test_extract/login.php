<?php
// login.php
// Common Login Portal for all roles (Super Admin, Admin, Customer)

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: " . getDashboardUrl($_SESSION['user_role']));
    exit;
}

$error_msg = '';
$success_msg = '';

// Check query parameter errors
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'login_required') {
        $error_msg = 'Authentication required to access workspace.';
    } elseif ($_GET['error'] === 'unauthorized') {
        $error_msg = 'Access denied. You are not authorized for that view.';
    }
}
if (isset($_GET['timeout'])) {
    $error_msg = 'Session expired due to inactivity. Please log in again.';
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/db.php';
    
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($email) || empty($password)) {
        $error_msg = 'Please fill in all credentials.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Invalid email address format.';
    } else {
        try {
            // Find user in database
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Verify account status
                if ($user['status'] !== 'active') {
                    $error_msg = 'Your account has been deactivated. Please contact support.';
                } else {
                    // Start secure session and write info
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_status'] = $user['status'];
                    $_SESSION['last_activity'] = time();
                    
                    $success_msg = 'Authentication successful! Redirecting...';
                    
                    // Direct role-based redirection
                    $redirectTarget = getDashboardUrl($user['role']);
                    
                    // Output redirect script to show the success state before transition
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = '" . $redirectTarget . "';
                        }, 1200);
                    </script>";
                }
            } else {
                $error_msg = 'Invalid email address or password.';
            }
        } catch (PDOException $e) {
            $error_msg = 'Authentication service failure: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure E-Sports Portal Authentication — Clash Arena</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- App Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <style>
        .login-illustration-graphic {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(6, 182, 212, 0.15) 100%);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid var(--card-border);
        }
    </style>
</head>
<body>

    <div class="login-container">
        <!-- Split Left Screen: Brand Banner -->
        <div class="login-left d-none d-lg-flex">
            <div class="mb-5">
                <a href="index.php" class="text-adaptive text-decoration-none fs-4 fw-bold font-display d-flex align-items-center" style="letter-spacing: 0.05em;">
                    <img src="assets/images/logo.jpg" alt="Logo" class="me-2" style="height: 38px; width: auto; border-radius: 6px;">CLASH<span style="color: var(--accent-cyan);">ARENA</span>
                </a>
            </div>
            
            <div class="my-auto" style="max-width: 480px;">
                <h1 class="display-5 fw-extrabold mb-4 text-adaptive font-display" style="line-height: 1.2;">Conquer the Arena, Track the Ranks</h1>
                <p class="text-secondary mb-5" style="font-size: 1.05rem;">Manage gaming profiles, join upcoming brackets, approve squad registrations, and track wallet prize distributions within a unified console.</p>
                
                <div class="login-illustration-graphic">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-success text-adaptive d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.8rem;"><i class="fa-solid fa-check"></i></div>
                        <div class="fw-semibold">Granular Access Routing (RBAC)</div>
                    </div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-success text-adaptive d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.8rem;"><i class="fa-solid fa-check"></i></div>
                        <div class="fw-semibold">Single Elimination Bracket Generator</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success text-adaptive d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.8rem;"><i class="fa-solid fa-check"></i></div>
                        <div class="fw-semibold">Secure Wallet Transactions & Rewards</div>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-3">
                <span class="text-muted fs-8">&copy; 2026 Clash Arena. Crafted for professional E-Sports.</span>
            </div>
        </div>
        
        <!-- Split Right Screen: Login Card -->
        <div class="login-right">
            <div class="login-form-card">
                <div class="mb-4 text-center d-block d-lg-none">
                    <a href="index.php" class="fs-4 fw-extrabold font-display d-flex align-items-center justify-content-center" style="color: var(--text-primary); letter-spacing: 0.05em;">
                        <img src="assets/images/logo.jpg" alt="Logo" class="me-2" style="height: 38px; width: auto; border-radius: 6px;">CLASH<span style="color: var(--accent-cyan);">ARENA</span>
                    </a>
                </div>
                
                <div class="glass-card p-4">
                    <div class="mb-4 text-center">
                        <img src="assets/images/logo.jpg" alt="Logo" class="img-fluid mb-3 shadow-lg" style="max-height: 90px; border-radius: 12px; border: 1px solid var(--accent-cyan-glow);">
                        <h2 class="fw-bold mb-1 font-display" style="letter-spacing: 0.02em;">Login</h2>
                        <p class="text-secondary" style="font-size: 0.9rem;">Sign in to access your customized role workspace.</p>
                    </div>

                    <!-- Alerts Box -->
                    <?php if ($error_msg): ?>
                        <div class="alert alert-gaming alert-danger alert-dismissible fade show rounded-3 p-3 mb-4" role="alert" style="font-size: 0.85rem;">
                            <i class="fa-solid fa-circle-exclamation me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="alert" aria-label="Close" style="padding: 1.25rem 1rem;"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_msg): ?>
                        <div class="alert alert-gaming alert-success alert-dismissible fade show rounded-3 p-3 mb-4" role="alert" style="font-size: 0.85rem;">
                            <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
                            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="alert" aria-label="Close" style="padding: 1.25rem 1rem;"></button>
                        </div>
                    <?php endif; ?>

                    <form id="auth-form" method="POST" action="login.php">
                        <!-- Email Address Input -->
                        <div class="mb-3">
                            <label for="email" class="form-label-custom">Email Address</label>
                            <div class="position-relative">
                                <input type="email" name="email" id="email" class="form-control form-control-custom ps-3" placeholder="player@clasharena.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Password Input with Toggle visibility -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="form-label-custom">Password</label>
                            </div>
                            <div class="password-input-wrapper">
                                <input type="password" name="password" id="password" class="form-control form-control-custom ps-3" placeholder="••••••••" required>
                                <button type="button" class="password-toggle-btn shadow-none" id="password-toggle" aria-label="Toggle Password Visibility">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Actions row -->
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2 mb-4">
                            <div class="form-check">
                                <input class="form-check-input shadow-none" type="checkbox" id="remember-me" name="remember" style="background-color: var(--bg-tertiary); border-color: var(--card-border);">
                                <label class="form-check-label text-secondary fs-8" for="remember-me" style="user-select: none;">
                                    Remember Me
                                </label>
                            </div>
                            <a href="#" class="fs-8 fw-semibold text-accent-cyan">Forgot Password?</a>
                        </div>

                        <!-- Submit Authentication Button -->
                        <button type="submit" class="btn btn-gaming-purple w-100 py-3" id="auth-submit-btn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" id="spinner" aria-hidden="true"></span>
                            <span id="btn-text">Sign In</span>
                        </button>
                    </form>
                    
                    <div class="mt-3 text-center" style="font-size: 0.85rem;">
                        <div class="text-secondary mb-1">Don't have an account?</div>
                        <a href="register.php" class="fw-bold text-accent-cyan">Register here</a>
                    </div>
                    

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Custom Main JS -->
    <script src="assets/js/main.js"></script>

    <script>
        // Form Loading Animation Trigger
        const form = document.getElementById('auth-form');
        const submitBtn = document.getElementById('auth-submit-btn');
        const spinner = document.getElementById('spinner');
        const btnText = document.getElementById('btn-text');

        if (form && submitBtn) {
            form.addEventListener('submit', () => {
                submitBtn.disabled = true;
                spinner.classList.remove('d-none');
                btnText.textContent = 'Verifying...';
            });
        }
    </script>
</body>
</html>
