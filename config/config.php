<?php
// Session settings must be set before session starts
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 0); // Disabled for local development

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Site configuration
define('SITE_NAME', 'Afrigig');
define('SITE_URL', 'http://localhost:8000'); // Local development URL

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 1); // Enabled for local development
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// Time zone setting
date_default_timezone_set('UTC');

// Include database configuration
require_once 'database.php';

// M-Pesa API configuration
define('MPESA_CONSUMER_KEY', 'your_consumer_key');       // Replace with your actual key
define('MPESA_CONSUMER_SECRET', 'your_consumer_secret'); // Replace with your actual secret
define('MPESA_SHORTCODE', 'your_shortcode');             // Replace with your actual shortcode
define('MPESA_PASSKEY', 'your_passkey');                 // Replace with your actual passkey
define('MPESA_CALLBACK_URL', SITE_URL . '/api/mpesa_callback.php');

// Security settings
define('CSRF_TOKEN_SECRET', bin2hex(random_bytes(32))); // For CSRF protection 