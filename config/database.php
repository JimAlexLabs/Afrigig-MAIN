<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'afrigig_user');     // Database username
define('DB_PASS', 'Afrigig@2024');    // Database password
define('DB_NAME', 'afrigig_db');       // Database name

// Create connection
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
} 