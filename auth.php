<?php
ini_set('display_errors', 1); // For debugging
error_reporting(E_ALL);   // For debugging

require_once __DIR__ . '/config/config.php'; // Includes session_start() and credentials

$action = $_GET['action'] ?? '';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    error_log("AUTH.PHP: Attempting login. Submitted User: '" . $username . "', Submitted Pass: '" . $password . "'");
    error_log("AUTH.PHP: Config User: '" . LOGIN_USER . "', Config Pass: '" . LOGIN_PASSWORD . "'");

    if ($username === LOGIN_USER && $password === LOGIN_PASSWORD) {
        $_SESSION['is_logged_in'] = true;
        $_SESSION['username'] = $username; // Optional: store username
        unset($_SESSION['login_error']); // Clear any previous login error
        error_log("AUTH.PHP: Login SUCCESSFUL. Session 'is_logged_in' set to true. Redirecting to index.php.");
        error_log("AUTH.PHP: Current SESSION data before redirect: " . print_r($_SESSION, true));
        header('Location: index.php'); // Redirect to the main app page
        exit;
    } else {
        $_SESSION['login_error'] = 'Invalid username or password.';
        error_log("AUTH.PHP: Login FAILED. Invalid credentials. Redirecting to login.php.");
        header('Location: login.php'); // Redirect back to login page
        exit;
    }
} elseif ($action === 'logout') {
    // Unset all of the session variables.
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    // Note: This will destroy the session, and not just the session data!
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session.
    session_destroy();

    header('Location: login.php'); // Redirect to login page
    exit;
} else {
    // Invalid action or direct access
    header('Location: login.php');
    exit;
}
?> 