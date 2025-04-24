<?php
session_start(); // Access the existing session.

// Unset all session variables.
$_SESSION = array();

// If using session cookies, expire the cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page.
header('Location: login.php?logged_out=1'); // Add param for optional message
exit;
?>