<?php
ini_set('display_errors', 1); // For debugging
error_reporting(E_ALL);   // For debugging

require_once __DIR__ . '/config/config.php'; // To ensure session_start() is called

error_log("LOGIN.PHP: Checking session. 'is_logged_in' value: " . (isset($_SESSION['is_logged_in']) ? ($_SESSION['is_logged_in'] ? 'true' : 'false (but set)') : 'NOT SET'));
error_log("LOGIN.PHP: Current SESSION data: " . print_r($_SESSION, true));

// If already logged in, redirect to index.php
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    error_log("LOGIN.PHP: User already logged in. Redirecting to index.php.");
    header('Location: index.php');
    exit;
}

$error_message = '';
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Clear the error message after displaying
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ad Brainstormer</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
        }
        .login-container {
            background: #fff;
            padding: 2rem 3rem;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-container h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .login-button {
            width: 100%;
            padding: 0.8rem;
            background-color: #5cb85c;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .login-button:hover {
            background-color: #4cae4c;
        }
        .error-message {
            color: red;
            background-color: #fdd;
            border: 1px solid red;
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Ad Brainstormer Login</h1>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="auth.php?action=login" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
    </div>
</body>
</html> 