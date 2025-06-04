<?php
ini_set('display_errors', 1); // For debugging
error_reporting(E_ALL);   // For debugging

require_once __DIR__ . '/config/config.php'; // Ensures session_start() and defines LOGIN_USER, LOGIN_PASSWORD

// Login check (moved before any output from _header.php)
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include the header
require_once __DIR__ . '/_header.php';

// Determine which page to load
$page = $_GET['page'] ?? 'home'; // Default to home

// Whitelist allowed pages to prevent arbitrary file inclusion
$allowedPages = [
    'home' => __DIR__ . '/pages/home.php',
    'ad_brainstormer' => __DIR__ . '/pages/ad_brainstormer.php',
    'conversational_editing' => __DIR__ . '/pages/conversational_editing.php',
    'multi_image' => __DIR__ . '/pages/multi_image.php'
];

if (array_key_exists($page, $allowedPages) && file_exists($allowedPages[$page])) {
    require_once $allowedPages[$page];
} else {
    // Page not found or not allowed, load a 404 or redirect to home
    require_once __DIR__ . '/pages/home.php'; // Default to home for simplicity
    // Or you could create a pages/404.php
    // http_response_code(404);
    // require_once __DIR__ . '/pages/404.php';
}

// Include the footer
require_once __DIR__ . '/_footer.php';

?>
<?php /* Removed all HTML content and the inline openTab script from here */ ?> 