<?php
/**
 * Authentication related functions
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Register a new user
 */
function register_user($first_name, $last_name, $email, $password) {
    $conn = getDbConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return [
            'success' => false,
            'message' => 'Email already exists. Please use a different email or login.'
        ];
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Create welcome notification
        create_notification(
            $user_id,
            'Welcome to Afrigig!',
            'Thank you for joining our platform. Complete your profile to start bidding on jobs.',
            '/settings'
        );
        
        return [
            'success' => true,
            'message' => 'Registration successful. You can now log in.',
            'user_id' => $user_id
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Registration failed. Please try again.'
        ];
    }
}

/**
 * Login a user
 */
function login_user($email, $password) {
    $conn = getDbConnection();
    
    // Get user by email
    $stmt = $conn->prepare("SELECT id, password, first_name, last_name, is_admin, theme, is_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['is_admin'] = $user['is_admin'] == 1;
            $_SESSION['theme'] = $user['theme'];
            $_SESSION['is_verified'] = $user['is_verified'] == 1;
            
            // Update last login time
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Login successful.',
                'user_id' => $user['id'],
                'is_admin' => $user['is_admin'] == 1
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'Invalid email or password.'
    ];
}

/**
 * Logout user
 */
function logout_user() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    return [
        'success' => true,
        'message' => 'Logout successful.'
    ];
}

/**
 * Change password
 */
function change_password($user_id, $current_password, $new_password) {
    $conn = getDbConnection();
    
    // Get current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Password changed successfully.'
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Current password is incorrect.'
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'Failed to change password.'
    ];
}

/**
 * Reset password request (generates token and sends email)
 */
function reset_password_request($email) {
    $conn = getDbConnection();
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
        
        // Store token in database
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user['id'], $token, $expires);
        
        if ($stmt->execute()) {
            // Send email with reset link
            $reset_link = SITE_URL . '/reset-password.php?token=' . $token;
            
            // In a real application, you would send an actual email here
            // For now, we'll just return the reset link
            return [
                'success' => true,
                'message' => 'Password reset link has been sent to your email.',
                'reset_link' => $reset_link // For development purposes only
            ];
        }
    }
    
    // Even if email doesn't exist, return success to prevent user enumeration
    return [
        'success' => true,
        'message' => 'If your email is registered, you will receive a password reset link.'
    ];
}

/**
 * Reset password with token
 */
function reset_password_with_token($token, $new_password) {
    $conn = getDbConnection();
    
    // Check if token is valid and not expired
    $stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $reset = $result->fetch_assoc();
        $user_id = $reset['user_id'];
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            // Delete used token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Password has been reset successfully. You can now log in with your new password.'
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'Invalid or expired token. Please request a new password reset link.'
    ];
} 