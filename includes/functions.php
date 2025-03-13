<?php
/**
 * Utility functions for Afrigig
 */

// Clean input data
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Flash messages
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Redirect to a page
function redirect($url) {
    header("Location: $url");
    exit;
}

// Get current user ID
function get_current_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Format date
function format_date($date, $format = 'd M Y, H:i') {
    return date($format, strtotime($date));
}

// Format money
function format_money($amount) {
    return '$' . number_format($amount, 2);
}

// Calculate time remaining until deadline
function time_remaining($deadline) {
    $deadline_time = strtotime($deadline);
    $current_time = time();
    $remaining_seconds = $deadline_time - $current_time;
    
    if ($remaining_seconds <= 0) {
        return 'Expired';
    }
    
    $days = floor($remaining_seconds / 86400);
    $remaining_seconds %= 86400;
    
    $hours = floor($remaining_seconds / 3600);
    $remaining_seconds %= 3600;
    
    if ($days > 0) {
        return $days . 'd ' . $hours . 'h';
    } else {
        return $hours . 'h';
    }
}

// Calculate bid fee based on job value
function calculate_bid_fee($job_salary, $feature_type) {
    if ($feature_type === 'hide') {
        // 5% of job salary, minimum $3, maximum $8
        $fee = $job_salary * 0.05;
        return max(3, min(8, $fee));
    } elseif ($feature_type === 'feature') {
        // 6% of job salary, minimum $3, maximum $8
        $fee = $job_salary * 0.06;
        return max(3, min(8, $fee));
    }
    return 0;
}

// Check if user has verified skill
function has_verified_skill($user_id, $skill_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT is_verified FROM user_skills WHERE user_id = ? AND skill_id = ?");
    $stmt->bind_param("ii", $user_id, $skill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['is_verified'] == 1;
    }
    
    return false;
}

// Get unread notifications count
function get_unread_notifications_count($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get unread messages count
function get_unread_messages_count($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get cart items count
function get_cart_items_count($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart_items WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get user balance
function get_user_balance($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['balance'];
}

// Insert notification
function create_notification($user_id, $title, $message, $link = null) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $link);
    return $stmt->execute();
}

// Insert message
function send_message($sender_id, $receiver_id, $message) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    return $stmt->execute();
}

// Get user theme preference
function get_user_theme($user_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['theme'] ?? 'light';
}

// Set user theme preference
function set_user_theme($user_id, $theme) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
    $stmt->bind_param("si", $theme, $user_id);
    return $stmt->execute();
}

/**
 * Get the appropriate color class for a job status
 */
function get_status_color($status) {
    return match($status) {
        'open' => 'success',
        'in_progress' => 'primary',
        'completed' => 'info',
        'cancelled' => 'danger',
        'expired' => 'warning',
        default => 'secondary'
    };
}

/**
 * Truncate text to a specified length
 */
function truncate_text($text, $length = 100, $ending = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length - strlen($ending)) . $ending;
}

/**
 * Format a timestamp into a human-readable time ago string
 */
function time_ago($timestamp) {
    $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'just now';
    }
    
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute'
    ];
    
    foreach ($intervals as $seconds => $label) {
        $interval = floor($diff / $seconds);
        if ($interval >= 1) {
            return $interval . ' ' . $label . ($interval > 1 ? 's' : '') . ' ago';
        }
    }
    
    return date('M j, Y', $time);
} 