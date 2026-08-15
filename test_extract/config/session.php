<?php
// config/session.php
// Secure session manager

if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie parameters
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Implement session inactivity timeout (30 minutes)
$timeout = 1800; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    // Redirect to login page with timeout notice
    $redirectUrl = (strpos($_SERVER['REQUEST_URI'], '/superadmin/') !== false || strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || strpos($_SERVER['REQUEST_URI'], '/user/') !== false) ? '../login.php?timeout=1' : 'login.php?timeout=1';
    header("Location: " . $redirectUrl);
    exit;
}
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created_time'])) {
    $_SESSION['created_time'] = time();
} elseif (time() - $_SESSION['created_time'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created_time'] = time();
}

/**
 * Checks if the user is authenticated.
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && isset($_SESSION['user_status']) && $_SESSION['user_status'] === 'active';
}
