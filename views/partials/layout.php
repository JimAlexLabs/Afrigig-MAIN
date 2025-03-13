<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user theme preference
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <!-- Additional page-specific styles -->
    <?php if (isset($additional_styles)) echo $additional_styles; ?>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/" class="logo">
                    <img src="/assets/images/logo.png" alt="<?php echo SITE_NAME; ?>" height="40">
                </a>
                <button id="mobile-menu-btn" class="mobile-menu-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                    </svg>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <?php if (is_logged_in()): ?>
                    <a href="/dashboard" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        Dashboard
                    </a>
                    
                    <a href="/jobs" class="nav-item <?php echo $current_page === 'jobs' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Jobs
                    </a>
                    
                    <a href="/cart" class="nav-item <?php echo $current_page === 'cart' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Cart
                        <?php if (($cart_count = get_cart_items_count(get_current_user_id())) > 0): ?>
                            <span class="badge badge-primary cart-count"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/notifications" class="nav-item <?php echo $current_page === 'notifications' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Notifications
                        <?php if (($notification_count = get_unread_notifications_count(get_current_user_id())) > 0): ?>
                            <span class="badge badge-primary"><?php echo $notification_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="/messages" class="nav-item <?php echo $current_page === 'messages' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        Messages
                        <?php if (($message_count = get_unread_messages_count(get_current_user_id())) > 0): ?>
                            <span class="badge badge-primary"><?php echo $message_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (is_admin()): ?>
                        <a href="/admin" class="nav-item <?php echo $current_page === 'admin' ? 'active' : ''; ?>">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Admin Panel
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/login" class="nav-item <?php echo $current_page === 'login' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        Login
                    </a>
                    
                    <a href="/register" class="nav-item <?php echo $current_page === 'register' ? 'active' : ''; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        Register
                    </a>
                <?php endif; ?>
            </nav>
            
            <?php if (is_logged_in()): ?>
                <div class="sidebar-footer">
                    <div class="user-info">
                        <img src="<?php echo isset($_SESSION['profile_image']) ? $_SESSION['profile_image'] : '/assets/images/default-avatar.png'; ?>" 
                             alt="<?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>" 
                             class="avatar">
                        <div class="user-details">
                            <div class="user-name"><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></div>
                            <div class="user-balance">Balance: <?php echo format_money(get_user_balance(get_current_user_id())); ?></div>
                        </div>
                    </div>
                    
                    <div class="settings-menu">
                        <button id="theme-toggle" class="btn btn-secondary">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                            </svg>
                            Toggle Theme
                        </button>
                        
                        <a href="/settings" class="btn btn-secondary">
                            <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Settings
                        </a>
                        
                        <form action="/logout" method="POST" class="logout-form">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <button type="submit" class="btn btn-secondary">
                                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </aside>
        
        <!-- Main content -->
        <main class="main-content">
            <?php
            // Display flash messages
            $flash = get_flash_message();
            if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> fade-in">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>
            
            <?php
            // Include the page content
            if (isset($content)) {
                echo $content;
            }
            ?>
        </main>
    </div>
    
    <!-- Scripts -->
    <script src="/assets/js/main.js"></script>
    
    <!-- Additional page-specific scripts -->
    <?php if (isset($additional_scripts)) echo $additional_scripts; ?>
    
    <?php if (isset($_SESSION['show_welcome_tour']) && $_SESSION['show_welcome_tour']): ?>
        <script src="https://cdn.jsdelivr.net/npm/shepherd.js@9.1.1/dist/js/shepherd.min.js"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@9.1.1/dist/css/shepherd.css"/>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                startWelcomeTour();
                <?php unset($_SESSION['show_welcome_tour']); ?>
            });
        </script>
    <?php endif; ?>
</body>
</html> 