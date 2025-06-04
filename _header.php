<?php
// This file assumes session_start() has been called by the including file (e.g., index.php)
// and config.php has been included.

$currentPage = $_GET['page'] ?? 'home'; // Default to 'home' if no page is specified
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontext - AI Creative Suite</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> <!-- Example font -->
</head>
<body>
    <header class="app-header">
        <div class="header-content">
            <div class="logo-and-tabs">
                <a href="index.php?page=home" class="logo">Kontext</a>
                <nav class="main-nav">
                    <a href="index.php?page=home" class="tab-link <?php echo ($currentPage === 'home') ? 'active' : ''; ?>">Home</a>
                    <a href="index.php?page=ad_brainstormer" class="tab-link <?php echo ($currentPage === 'ad_brainstormer') ? 'active' : ''; ?>">Ad Brainstormer</a>
                    <a href="index.php?page=conversational_editing" class="tab-link <?php echo ($currentPage === 'conversational_editing') ? 'active' : ''; ?>">Conversational Editing</a>
                    <a href="index.php?page=multi_image" class="tab-link <?php echo ($currentPage === 'multi_image') ? 'active' : ''; ?>">Multi Image</a>
                </nav>
            </div>
            <div class="user-area">
                <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true): ?>
                    <span class="welcome-user">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    <a href="auth.php?action=logout" class="logout-link">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php /* The old .tab-navigation div is removed entirely */ ?>

    <main>
        <?php /* Main content will be included by index.php after this header */ ?> 