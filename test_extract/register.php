<?php
// register.php
// Registration Portal for new players/customers

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: " . getDashboardUrl($_SESSION['user_role']));
    exit;
}

$error_msg = '';
$success_msg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/db.php';
    
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_msg = 'Please fill in all registration fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Invalid email address format.';
    } elseif (strlen($password) < 6) {
        $error_msg = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_msg = 'Passwords do not match.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM `users` WHERE `email` = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $error_msg = 'Email address is already registered.';
            } else {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert User (Default role is customer/player, active status)
                $insertUser = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES (:name, :email, :password, 'customer', 'active')");
                $insertUser->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashedPassword
                ]);
                
                $newUserId = $pdo->lastInsertId();
                
                // Insert default Wallet
                $insertWallet = $pdo->prepare("INSERT INTO `wallets` (`user_id`, `balance`, `coins`) VALUES (:user_id, 100.00, 200)");
                $insertWallet->execute(['user_id' => $newUserId]);
                
                // Commit transaction
                $pdo->commit();
                
                $success_msg = 'Registration successful! Redirecting to login...';
                
                // Output redirect script to login page
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                </script>";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_msg = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create E-Sports Account — Clash Arena</title>
    
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
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
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
                <h1 class="display-5 fw-extrabold mb-4 text-adaptive font-display" style="line-height: 1.2;">Compete, Win, Conquer the Leaderboard</h1>
                <p class="text-secondary mb-5" style="font-size: 1.05rem;">Register a player account to form squads, enter brackets, challenge other teams, and win prize pools in our automated gaming system.</p>
                
                <div class="login-illustration-graphic">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-success text-adaptive d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.8rem;"><i class="fa-solid fa-check"></i></div>
                        <div class="fw-semibold">Automated Tournament Matchmaking</div>
                    </div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-success text-adaptive d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.8rem;"><i class="fa-solid fa-check"></i></div>
                        <div class="fw-semibold">Live Brackets & Real-Time Standings</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success text-adaptive d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.8rem;"><i class="fa-solid fa-check"></i></div>
                        <div class="fw-semibold">Personal Wallet and Coins Rewards</div>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-3">
                <span class="text-muted fs-8">&copy; 2026 Clash Arena. Crafted for professional E-Sports.</span>
            </div>
        </div>
        
        <!-- Split Right Screen: Register Card -->
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
                        <h2 class="fw-bold mb-1 font-display" style="letter-spacing: 0.02em;">Register</h2>
                        <p class="text-secondary" style="font-size: 0.9rem;">Sign up to join tournaments and claim rewards.</p>
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

                    <form id="auth-form" method="POST" action="register.php">
                        <!-- Full Name Input -->
                        <div class="mb-3">
                            <label for="name" class="form-label-custom">Full Name</label>
                            <input type="text" name="name" id="name" class="form-control form-control-custom ps-3" placeholder="John Doe" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>

                        <!-- Email Address Input -->
                        <div class="mb-3">
                            <label for="email" class="form-label-custom">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control form-control-custom ps-3" placeholder="player@clasharena.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <!-- Password Input -->
                        <div class="mb-3">
                            <label for="password" class="form-label-custom">Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" name="password" id="password" class="form-control form-control-custom ps-3" placeholder="••••••••" required>
                                <button type="button" class="password-toggle-btn shadow-none" id="password-toggle" aria-label="Toggle Password Visibility">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Confirm Password Input -->
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label-custom">Confirm Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-custom ps-3" placeholder="••••••••" required>
                                <button type="button" class="password-toggle-btn shadow-none" id="confirm-password-toggle" aria-label="Toggle Confirm Password Visibility">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Registration Button -->
                        <button type="submit" class="btn btn-gaming-purple w-100 py-3" id="auth-submit-btn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" id="spinner" aria-hidden="true"></span>
                            <span id="btn-text">Sign Up</span>
                        </button>
                    </form>
                    
                    <div class="mt-3 text-center" style="font-size: 0.85rem;">
                        <div class="text-secondary mb-1">Already have an account?</div>
                        <a href="login.php" class="fw-bold text-accent-cyan">Login here</a>
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
        if (form && submitBtn) {
            form.addEventListener('submit', () => {
                const spinner = document.getElementById('spinner');
                const btnText = document.getElementById('btn-text');
                if (spinner && btnText) {
                    spinner.classList.remove('d-none');
                    btnText.textContent = 'Creating Account...';
                }
                submitBtn.disabled = true;
            });
        }


        // Toggle Confirm Password visibility logic
        const confirmToggle = document.getElementById('confirm-password-toggle');
        const confirmField = document.getElementById('confirm_password');
        if (confirmToggle && confirmField) {
            confirmToggle.addEventListener('click', () => {
                const type = confirmField.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmField.setAttribute('type', type);
                const icon = confirmToggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            });
        }
    </script>
</body>
</html>
