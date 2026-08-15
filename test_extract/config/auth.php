<?php
// config/auth.php
// Role-Based Access Control and CSRF Security Middleware

require_once __DIR__ . '/session.php';

/**
 * Returns the target dashboard URL for a given role.
 * @param string $role
 * @return string
 */
function getDashboardUrl($role) {
    // Detect if running locally or on production/InfinityFree server
    $is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');
    $basePath = $is_localhost ? '/final' : '';

    switch ($role) {
        case 'superadmin':
            return $basePath . '/superadmin/dashboard.php';
        case 'admin':
            return $basePath . '/admin/dashboard.php';
        case 'customer':
            return $basePath . '/user/dashboard.php';
        default:
            return $basePath . '/index.php';
    }
}

/**
 * Validates if the current user session is authorized for the given roles.
 * Redirects if unauthorized.
 * @param array $allowedRoles
 */
function checkRole($allowedRoles) {
    if (!isLoggedIn()) {
        // Check if inside a subdirectory to dynamically resolve redirection path
        $redirectPath = (strpos($_SERVER['REQUEST_URI'], '/superadmin/') !== false || 
                         strpos($_SERVER['REQUEST_URI'], '/admin/') !== false || 
                         strpos($_SERVER['REQUEST_URI'], '/user/') !== false) ? '../login.php' : 'login.php';
        header("Location: " . $redirectPath . "?error=login_required");
        exit;
    }

    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        // Access Denied: redirect to their corresponding authorized dashboard
        $targetDashboard = getDashboardUrl($_SESSION['user_role']);
        header("Location: " . $targetDashboard . "?error=unauthorized");
        exit;
    }
}

/**
 * Generate a CSRF token and store it in session
 * @return string
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token
 * @param string $token
 * @return bool
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
