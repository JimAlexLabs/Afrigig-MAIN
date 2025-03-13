<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid job ID');
    header('Location: /jobs');
    exit;
}

$job_id = (int)$_GET['id'];

// Get job details
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT j.*, 
           u.first_name,
           u.last_name,
           u.profile_image,
           COUNT(DISTINCT b.id) as bid_count,
           MIN(b.amount) as lowest_bid,
           MAX(b.amount) as highest_bid,
           AVG(b.amount) as average_bid
    FROM jobs j
    LEFT JOIN users u ON j.admin_id = u.id
    LEFT JOIN bids b ON j.id = b.job_id
    WHERE j.id = ?
    GROUP BY j.id
");

$stmt->bind_param('i', $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    set_flash_message('error', 'Job not found');
    header('Location: /jobs');
    exit;
}

// Get bids for this job
$stmt = $conn->prepare("
    SELECT b.*,
           u.first_name,
           u.last_name,
           u.profile_image
    FROM bids b
    JOIN users u ON b.user_id = u.id
    WHERE b.job_id = ?
    ORDER BY 
        CASE WHEN b.status = 'accepted' THEN 1
             WHEN b.status = 'pending' THEN 2
             ELSE 3
        END,
        b.created_at DESC
");

$stmt->bind_param('i', $job_id);
$stmt->execute();
$bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if current user has already bid
$has_bid = false;
$user_bid = null;
if (is_logged_in()) {
    $stmt = $conn->prepare("
        SELECT * FROM bids 
        WHERE job_id = ? AND user_id = ?
    ");
    $user_id = get_current_user_id();
    $stmt->bind_param('ii', $job_id, $user_id);
    $stmt->execute();
    $user_bid = $stmt->get_result()->fetch_assoc();
    $has_bid = (bool)$user_bid;
}

// Handle bid submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!is_logged_in()) {
        set_flash_message('error', 'You must be logged in to bid on jobs');
        header('Location: /login');
        exit;
    }
    
    if ($_POST['action'] === 'place_bid') {
        // Validate CSRF token
        if (!verify_csrf_token($_POST['csrf_token'])) {
            set_flash_message('error', 'Invalid CSRF token');
            header("Location: {$_SERVER['REQUEST_URI']}");
            exit;
        }
        
        // Validate bid amount
        $amount = (float)$_POST['amount'];
        if ($amount <= 0) {
            set_flash_message('error', 'Bid amount must be greater than 0');
            header("Location: {$_SERVER['REQUEST_URI']}");
            exit;
        }
        
        // Check if job is still open
        if ($job['status'] !== 'open') {
            set_flash_message('error', 'This job is no longer accepting bids');
            header("Location: {$_SERVER['REQUEST_URI']}");
            exit;
        }
        
        // Insert bid
        $stmt = $conn->prepare("
            INSERT INTO bids (job_id, user_id, amount, proposal, timeline)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $user_id = get_current_user_id();
        $proposal = clean_input($_POST['proposal']);
        $timeline = (int)$_POST['timeline'];
        
        $stmt->bind_param('iidsi', $job_id, $user_id, $amount, $proposal, $timeline);
        
        if ($stmt->execute()) {
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
            set_flash_message('error', 'Failed to place bid. Please try again.');
        }
        
        header("Location: {$_SERVER['REQUEST_URI']}");
        exit;
    }
    
    // Handle delete bid action
    if ($_POST['action'] === 'delete_bid') {
        // Validate CSRF token
        if (!verify_csrf_token($_POST['csrf_token'])) {
            set_flash_message('error', 'Invalid CSRF token');
            header("Location: {$_SERVER['REQUEST_URI']}");
            exit;
        }
        
        // Delete the bid
        $stmt = $conn->prepare("
            DELETE FROM bids 
            WHERE job_id = ? AND user_id = ?
        ");
        
        $user_id = get_current_user_id();
        $stmt->bind_param('ii', $job_id, $user_id);
        
        if ($stmt->execute()) {
            set_flash_message('success', 'Your bid has been withdrawn successfully');
        } else {
            set_flash_message('error', 'Failed to withdraw bid. Please try again.');
        }
        
        header("Location: {$_SERVER['REQUEST_URI']}");
        exit;
    }
    
    // Handle accept bid action
    if ($_POST['action'] === 'accept_bid') {
        // Validate CSRF token
        if (!verify_csrf_token($_POST['csrf_token'])) {
            set_flash_message('error', 'Invalid CSRF token');
            header("Location: {$_SERVER['REQUEST_URI']}");
            exit;
        }
        
        // Check if user is the job owner
        if ($job['admin_id'] != get_current_user_id()) {
            set_flash_message('error', 'You are not authorized to accept bids for this job');
            header("Location: {$_SERVER['REQUEST_URI']}");
            exit;
        }
        
        // Check if job is still open
        if ($job['status'] !== 'open') {
            set_flash_message('error', 'This job is no longer accepting bids');
            header("Location: {$_SERVER['REQUEST_URI']}");
            exit;
        }
        
        // Get the bid ID
        $bid_id = (int)$_POST['bid_id'];
        
        // Update the bid status to accepted
        $stmt = $conn->prepare("
            UPDATE bids 
            SET status = 'accepted' 
            WHERE id = ? AND job_id = ?
        ");
        
        $stmt->bind_param('ii', $bid_id, $job_id);
        
        if ($stmt->execute()) {
            // Update job status to assigned
            $stmt = $conn->prepare("
                UPDATE jobs 
                SET status = 'assigned' 
                WHERE id = ?
            ");
            
            $stmt->bind_param('i', $job_id);
            $stmt->execute();
            
            // Get the freelancer ID
            $stmt = $conn->prepare("
                SELECT user_id FROM bids 
                WHERE id = ?
            ");
            
            $stmt->bind_param('i', $bid_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $bid_data = $result->fetch_assoc();
            
            if ($bid_data) {
                // Notify the freelancer
                create_notification(
                    $bid_data['user_id'],
                    'Bid Accepted',
                    "Your bid on '{$job['title']}' has been accepted!",
                    'bid_accepted',
                    $job_id
                );
            }
            
            set_flash_message('success', 'Bid accepted successfully. The job status has been updated to assigned.');
        } else {
            set_flash_message('error', 'Failed to accept bid. Please try again.');
        }
        
        header("Location: {$_SERVER['REQUEST_URI']}");
        exit;
    }
}

// Page title
$page_title = $job['title'];

// Additional styles
$additional_styles = '
<style>
    :root {
        --primary: #4a6cf7;
        --primary-dark: #3d5ed4;
        --secondary: #6E6E6E;
        --success: #219653;
        --danger: #D64545;
        --warning: #F59E0B;
        --info: #3498DB;
        --light-bg: #f5f8ff;
        --dark-bg: #1d2144;
        --border-color: #e2e8f0;
        --card-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }

    body {
        font-family: "Inter", sans-serif;
        line-height: 1.6;
        color: #333;
        background-color: #f5f8ff;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    .card {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        transition: var(--transition);
    }

    .card:hover {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        background-color: #f8fafc;
    }

    .card-body {
        padding: 1.5rem;
    }

    .mb-4 {
        margin-bottom: 1rem;
    }

    .mb-5 {
        margin-bottom: 1.5rem;
    }

    /* Job Title and Status Styles */
    .job-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }

    .status-badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 50px;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .status-open {
        background-color: #ebf5ff;
        color: #3182ce;
    }

    .status-assigned {
        background-color: #e6fffa;
        color: #319795;
    }

    .status-in-progress {
        background-color: #ebf8ff;
        color: #4299e1;
    }

    .status-completed {
        background-color: #f0fff4;
        color: #38a169;
    }

    .status-cancelled {
        background-color: #fff5f5;
        color: #e53e3e;
    }

    /* Flex Utilities */
    .flex {
        display: flex;
    }

    .justify-between {
        justify-content: space-between;
    }

    .items-center {
        align-items: center;
    }

    /* Client Info Styles */
    .client-info {
        display: flex;
        align-items: center;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--border-color);
    }

    .client-avatar-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 1rem;
        border: 2px solid var(--primary);
    }

    .client-avatar {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .client-name {
        font-size: 1.125rem;
        font-weight: 600;
        margin: 0 0 0.25rem;
        color: #2d3748;
    }

    .text-secondary {
        color: var(--secondary);
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        margin: 0;
    }

    .text-secondary i {
        margin-right: 0.5rem;
    }

    /* Job Meta Styles */
    .job-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .meta-item {
        display: flex;
        align-items: flex-start;
    }

    .meta-icon {
        width: 2rem;
        height: 2rem;
        margin-right: 0.75rem;
        color: var(--primary);
    }

    .meta-icon svg {
        width: 100%;
        height: 100%;
    }

    .meta-content {
        display: flex;
        flex-direction: column;
    }

    .meta-label {
        font-size: 0.875rem;
        color: var(--secondary);
        margin-bottom: 0.25rem;
    }

    .meta-value {
        font-weight: 600;
        color: #2d3748;
    }

    /* Bid Stats Styles */
    .bid-stats {
        display: flex;
        justify-content: space-between;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border-color);
    }

    .bid-stat {
        text-align: center;
        flex: 1;
    }

    .bid-stat-label {
        display: block;
        font-size: 0.875rem;
        color: var(--secondary);
        margin-bottom: 0.25rem;
    }

    .bid-stat-value {
        font-weight: 700;
        color: var(--primary);
        font-size: 1.125rem;
    }

    /* Job Description Styles */
    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1a202c;
        margin: 0;
    }

    .job-description-content {
        line-height: 1.8;
        color: #4a5568;
        white-space: pre-line;
    }

    /* Attachments Styles */
    .attachments-section {
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border-color);
    }

    .attachments-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: #2d3748;
    }

    .attachments-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }

    .attachment-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        background-color: #f7fafc;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        transition: var(--transition);
        text-decoration: none;
        color: #4a5568;
    }

    .attachment-item:hover {
        background-color: #edf2f7;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .attachment-icon {
        width: 1.5rem;
        height: 1.5rem;
        margin-right: 0.75rem;
        color: var(--primary);
        flex-shrink: 0;
    }

    .attachment-icon svg {
        width: 100%;
        height: 100%;
    }

    .attachment-name {
        flex: 1;
        font-size: 0.875rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .attachment-download {
        width: 1.25rem;
        height: 1.25rem;
        color: var(--secondary);
        margin-left: 0.75rem;
        flex-shrink: 0;
    }

    .attachment-download svg {
        width: 100%;
        height: 100%;
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
        .job-meta {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .bid-stats {
            flex-direction: column;
        }
        
        .bid-stat {
            margin-bottom: 1rem;
        }
        
        .attachments-list {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        .job-meta {
            grid-template-columns: 1fr;
        }
        
        .flex.justify-between {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .job-title {
            margin-bottom: 0.5rem;
        }
        
        .client-info {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .client-avatar-wrapper {
            margin-bottom: 1rem;
        }
    }
    
    /* Form Styles */
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #2d3748;
    }
    
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        font-size: 1rem;
        transition: var(--transition);
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
    }
    
    .form-hint {
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: var(--secondary);
    }
    
    .input-with-icon {
        position: relative;
    }
    
    .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1.25rem;
        height: 1.25rem;
        color: var(--secondary);
    }
    
    .input-with-icon .form-control {
        padding-left: 3rem;
    }
    
    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }
    
    /* Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        border: none;
        font-size: 1rem;
    }
    
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    .btn-success {
        background-color: var(--success);
        color: white;
    }
    
    .btn-success:hover {
        background-color: #1a7f44;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background-color: var(--danger);
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #b83939;
        transform: translateY(-2px);
    }
    
    .btn-icon {
        width: 1.25rem;
        height: 1.25rem;
        margin-right: 0.5rem;
    }
    
    .bid-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }
    
    /* Alert Styles */
    .alert {
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
    }
    
    .alert-danger {
        background-color: #fef2f2;
        color: #b91c1c;
        border-left: 4px solid #ef4444;
    }
    
    .alert-success {
        background-color: #f0fdf4;
        color: #166534;
        border-left: 4px solid #22c55e;
    }
    
    .alert-info {
        background-color: #eff6ff;
        color: #1e40af;
        border-left: 4px solid #3b82f6;
    }
    
    .alert-icon {
        width: 1.5rem;
        height: 1.5rem;
        margin-right: 0.75rem;
        flex-shrink: 0;
    }
    
    .alert-title {
        font-weight: 600;
        margin: 0 0 0.25rem;
    }
    
    .alert-message {
        margin: 0;
    }
    
    /* Existing Bid Styles */
    .existing-bid {
        padding: 1rem 0;
    }
    
    .bid-details {
        margin-top: 1.5rem;
    }
    
    .bid-details-title {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: #2d3748;
    }
    
    .bid-proposal {
        line-height: 1.8;
        color: #4a5568;
        white-space: pre-line;
    }
    
    /* Bids List Styles */
    .bids-list {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .bid-item {
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        overflow: hidden;
        transition: var(--transition);
    }
    
    .bid-item:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transform: translateY(-2px);
    }
    
    .bid-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem;
        background-color: #f8fafc;
        border-bottom: 1px solid var(--border-color);
    }
    
    .bidder-info {
        display: flex;
        align-items: center;
    }
    
    .bidder-avatar-wrapper {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 1rem;
        border: 2px solid var(--primary);
    }
    
    .bidder-avatar {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .bidder-name {
        font-size: 1.125rem;
        font-weight: 600;
        margin: 0 0 0.25rem;
        color: #2d3748;
    }
    
    .bidder-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .bidder-rating, .bidder-jobs {
        display: flex;
        align-items: center;
        font-size: 0.875rem;
        color: var(--secondary);
    }
    
    .star-icon, .jobs-icon {
        width: 1rem;
        height: 1rem;
        margin-right: 0.25rem;
    }
    
    .star-icon {
        color: #f59e0b;
    }
    
    .bid-amount-wrapper {
        text-align: right;
    }
    
    .bid-amount {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary);
    }
    
    .delivery-time {
        font-size: 0.875rem;
        color: var(--secondary);
    }
    
    .bid-body {
        padding: 1.25rem;
    }
    
    .bid-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        background-color: #f8fafc;
        border-top: 1px solid var(--border-color);
    }
    
    .bid-date {
        font-size: 0.875rem;
        color: var(--secondary);
    }
    
    .accept-bid-form {
        margin-left: auto;
    }
    
    @media (max-width: 768px) {
        .bid-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .bid-amount-wrapper {
            margin-top: 1rem;
            text-align: left;
        }
        
        .bid-footer {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .accept-bid-form {
            margin-left: 0;
        }
    }
</style>
';

// Additional scripts
$additional_scripts = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize interactive elements
        initializeInteractiveElements();
    });
    
    function initializeInteractiveElements() {
        // Add any JavaScript initialization here
    }
</script>
';

// Start output buffering
ob_start();
?>

<div class="container">
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])): ?>
        <?php foreach ($_SESSION['flash_messages'] as $type => $message): ?>
            <div class="alert alert-<?php echo $type; ?> mb-5">
                <?php if ($type === 'success'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="alert-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                <?php elseif ($type === 'error'): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="alert-icon">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                <?php endif; ?>
                <div>
                    <p class="alert-message"><?php echo $message; ?></p>
                </div>
            </div>
            <?php unset($_SESSION['flash_messages'][$type]); ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Job Header Section -->
    <div class="card mb-5">
        <div class="card-header">
            <div class="flex justify-between items-center">
                <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>
                <span class="status-badge status-<?php echo $job['status']; ?>">
                    <?php echo ucfirst($job['status']); ?>
                </span>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Client Info -->
            <div class="client-info mb-4">
                <div class="client-avatar-wrapper">
                    <img src="<?php echo $job['profile_image'] ?? '/assets/images/default-avatar.png'; ?>" 
                         alt="<?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>"
                         class="client-avatar">
                </div>
                <div class="client-details">
                    <h3 class="client-name"><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></h3>
                    <p class="text-secondary">
                        <i class="icon-calendar"></i> Posted on <?php echo date('M j, Y', strtotime($job['created_at'])); ?>
                    </p>
                </div>
            </div>
            
            <!-- Job Meta Info -->
            <div class="job-meta">
                <div class="meta-item">
                    <div class="meta-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                    <div class="meta-content">
                        <span class="meta-label">Category</span>
                        <span class="meta-value"><?php echo isset($job['category_name']) ? htmlspecialchars($job['category_name']) : 'N/A'; ?></span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="meta-content">
                        <span class="meta-label">Budget</span>
                        <span class="meta-value"><?php echo format_money($job['salary']); ?></span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="meta-content">
                        <span class="meta-label">Deadline</span>
                        <span class="meta-value"><?php echo time_remaining($job['deadline']); ?> left</span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="meta-content">
                        <span class="meta-label">Bids</span>
                        <span class="meta-value"><?php echo $job['bid_count']; ?> bids</span>
                    </div>
                </div>
            </div>
            
            <?php if ($job['bid_count'] > 0): ?>
                <div class="bid-stats">
                    <div class="bid-stat">
                        <span class="bid-stat-label">Lowest bid:</span>
                        <span class="bid-stat-value"><?php echo format_money($job['lowest_bid']); ?></span>
                    </div>
                    <div class="bid-stat">
                        <span class="bid-stat-label">Average bid:</span>
                        <span class="bid-stat-value"><?php echo format_money($job['average_bid']); ?></span>
                    </div>
                    <div class="bid-stat">
                        <span class="bid-stat-label">Highest bid:</span>
                        <span class="bid-stat-value"><?php echo format_money($job['highest_bid']); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Job Description Section -->
    <div class="card mb-5">
        <div class="card-header">
            <h2 class="section-title">Job Description</h2>
        </div>
        <div class="card-body">
            <div class="job-description-content">
                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
            </div>
            
            <?php if (isset($job['attachments']) && $job['attachments']): ?>
                <div class="attachments-section">
                    <h3 class="attachments-title">Attachments</h3>
                    <div class="attachments-list">
                        <?php foreach (json_decode($job['attachments']) as $attachment): ?>
                            <a href="/uploads/<?php echo $attachment; ?>" class="attachment-item" target="_blank">
                                <div class="attachment-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                </div>
                                <span class="attachment-name"><?php echo htmlspecialchars($attachment); ?></span>
                                <div class="attachment-download">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bidding Section -->
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'freelancer' && $job['status'] == 'open'): ?>
        <div class="card mb-5">
            <div class="card-header">
                <h2 class="section-title">Place Your Bid</h2>
            </div>
            <div class="card-body">
                <?php if (isset($bid_error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $bid_error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($bid_success)): ?>
                    <div class="alert alert-success">
                        <?php echo $bid_success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$has_bid): ?>
                    <form action="" method="post" class="bid-form">
                        <input type="hidden" name="action" value="place_bid">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="form-group">
                            <label for="amount" class="form-label">Bid Amount ($)</label>
                            <div class="input-with-icon">
                                <span class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </span>
                                <input type="number" id="amount" name="amount" class="form-control" min="<?php echo $job['salary'] * 0.7; ?>" step="0.01" required>
                            </div>
                            <div class="form-hint">
                                Suggested bid range: <?php echo format_money($job['salary'] * 0.7); ?> - <?php echo format_money($job['salary'] * 1.3); ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="timeline" class="form-label">Delivery Time (Days)</label>
                            <div class="input-with-icon">
                                <span class="input-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </span>
                                <input type="number" id="timeline" name="timeline" class="form-control" min="1" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="proposal" class="form-label">Your Proposal</label>
                            <textarea id="proposal" name="proposal" class="form-control" rows="5" required placeholder="Explain why you're the best fit for this job..."></textarea>
                        </div>
                        
                        <div class="bid-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="btn-icon">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                                </svg>
                                Submit Bid
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="existing-bid">
                        <div class="alert alert-info">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="alert-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <h4 class="alert-title">You've already placed a bid on this job</h4>
                                <p class="alert-message">Your bid: <strong><?php echo format_money($user_bid['amount']); ?></strong> with delivery in <strong><?php echo $user_bid['delivery_time']; ?> days</strong></p>
                            </div>
                        </div>
                        
                        <div class="bid-details">
                            <h4 class="bid-details-title">Your Proposal</h4>
                            <div class="bid-proposal">
                                <?php echo nl2br(htmlspecialchars($user_bid['proposal'])); ?>
                            </div>
                        </div>
                        
                        <div class="bid-actions">
                            <form action="" method="post" class="delete-bid-form">
                                <input type="hidden" name="action" value="delete_bid">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to withdraw your bid?');">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="btn-icon">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Withdraw Bid
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Bids Section for Job Owner -->
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $job['user_id'] && $job['bid_count'] > 0): ?>
        <div class="card mb-5">
            <div class="card-header">
                <h2 class="section-title">Bids on Your Job</h2>
            </div>
            <div class="card-body">
                <div class="bids-list">
                    <?php foreach ($bids as $bid): ?>
                        <div class="bid-item">
                            <div class="bid-header">
                                <div class="bidder-info">
                                    <div class="bidder-avatar-wrapper">
                                        <img src="<?php echo $bid['profile_image'] ?? '/assets/images/default-avatar.png'; ?>" 
                                             alt="<?php echo htmlspecialchars($bid['first_name'] . ' ' . $bid['last_name']); ?>"
                                             class="bidder-avatar">
                                    </div>
                                    <div class="bidder-details">
                                        <h3 class="bidder-name"><?php echo htmlspecialchars($bid['first_name'] . ' ' . $bid['last_name']); ?></h3>
                                        <div class="bidder-meta">
                                            <span class="bidder-rating">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="star-icon">
                                                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                                </svg>
                                                <?php echo number_format($bid['rating'] ?? 0, 1); ?>
                                            </span>
                                            <span class="bidder-jobs">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="jobs-icon">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                                <?php echo $bid['completed_jobs'] ?? 0; ?> jobs completed
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bid-amount-wrapper">
                                    <div class="bid-amount"><?php echo format_money($bid['amount']); ?></div>
                                    <div class="delivery-time">in <?php echo $bid['delivery_time']; ?> days</div>
                                </div>
                            </div>
                            
                            <div class="bid-body">
                                <div class="bid-proposal">
                                    <?php echo nl2br(htmlspecialchars($bid['proposal'])); ?>
                                </div>
                            </div>
                            
                            <div class="bid-footer">
                                <div class="bid-date">Bid placed on <?php echo date('M j, Y', strtotime($bid['created_at'])); ?></div>
                                
                                <?php if ($job['status'] == 'open'): ?>
                                    <form action="" method="post" class="accept-bid-form">
                                        <input type="hidden" name="action" value="accept_bid">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                        <button type="submit" class="btn btn-success">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="btn-icon">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Accept Bid
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout template
require_once __DIR__ . '/../views/partials/layout.php'; 