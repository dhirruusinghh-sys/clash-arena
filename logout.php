<?php
// logout.php
// Log out session and destroy data

require_once __DIR__ . '/config/session.php';

// Unset all session variables
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session on server
session_unset();
session_destroy();

// Redirect back to landing page with logout flag
header("Location: index.php?logout=1");
exit;
