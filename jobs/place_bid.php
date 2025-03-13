<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Debug information
error_log('place_bid.php accessed: ' . print_r($_POST, true));

// Check if user is logged in
if (!is_logged_in()) {
    set_flash_message('error', 'You must be logged in to bid on jobs');
    header('Location: /login');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Invalid CSRF token');
        header('Location: /jobs');
        exit;
    }
    
    // Get job ID
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    
    if ($job_id <= 0) {
        set_flash_message('error', 'Invalid job ID');
        header('Location: /jobs');
        exit;
    }
    
    // Get job details
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        set_flash_message('error', 'Job not found');
        header('Location: /jobs');
        exit;
    }
    
    // Check if job is still open
    if ($job['status'] !== 'open') {
        set_flash_message('error', 'This job is no longer accepting bids');
        header("Location: /jobs/view.php?id={$job_id}");
        exit;
    }
    
    // Check if user already has a bid on this job
    $stmt = $conn->prepare("
        SELECT * FROM bids 
        WHERE job_id = ? AND user_id = ?
    ");
    $user_id = get_current_user_id();
    $stmt->bind_param('ii', $job_id, $user_id);
    $stmt->execute();
    $existing_bid = $stmt->get_result()->fetch_assoc();
    
    if ($existing_bid) {
        set_flash_message('error', 'You have already placed a bid on this job');
        header("Location: /jobs/view.php?id={$job_id}");
        exit;
    }
    
    // Validate bid amount
    $amount = (float)$_POST['amount'];
    if ($amount <= 0) {
        set_flash_message('error', 'Bid amount must be greater than 0');
        header("Location: /jobs/view.php?id={$job_id}");
        exit;
    }
    
    // Validate timeline
    $timeline = (int)$_POST['timeline'];
    if ($timeline <= 0) {
        set_flash_message('error', 'Timeline must be greater than 0 days');
        header("Location: /jobs/view.php?id={$job_id}");
        exit;
    }
    
    // Validate proposal
    $proposal = clean_input($_POST['proposal']);
    if (empty($proposal)) {
        set_flash_message('error', 'Please provide a proposal');
        header("Location: /jobs/view.php?id={$job_id}");
        exit;
    }
    
    // Insert bid
    $stmt = $conn->prepare("
        INSERT INTO bids (job_id, user_id, amount, proposal, timeline)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('iidsi', $job_id, $user_id, $amount, $proposal, $timeline);
    
    if ($stmt->execute()) {
        // Update bid count in jobs table
        $stmt = $conn->prepare("
            UPDATE jobs 
            SET bid_count = bid_count + 1 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $job_id);
        $stmt->execute();
        
        // Notify job owner
        create_notification(
            $job['admin_id'],
            'New bid received',
            "A new bid of " . format_money($amount) . " was placed on your job '{$job['title']}'",
            'bid',
            $job_id
        );
        
        set_flash_message('success', 'Your bid has been placed successfully');
    } else {
        set_flash_message('error', 'Failed to place bid. Please try again. Error: ' . $conn->error);
    }
    
    header("Location: /jobs/view.php?id={$job_id}");
    exit;
}

// If we get here, redirect to jobs page
header('Location: /jobs');
exit; 