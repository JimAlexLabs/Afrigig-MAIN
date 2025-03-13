<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Debug information
error_log('direct_bid.php accessed');

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid job ID');
    header('Location: /jobs');
    exit;
}

$job_id = (int)$_GET['id'];

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

// Check if user is logged in
if (!is_logged_in()) {
    set_flash_message('error', 'You must be logged in to bid on jobs');
    header('Location: /login');
    exit;
}

// Check if user already has a bid
$stmt = $conn->prepare("SELECT * FROM bids WHERE job_id = ? AND user_id = ?");
$user_id = get_current_user_id();
$stmt->bind_param('ii', $job_id, $user_id);
$stmt->execute();
$user_bid = $stmt->get_result()->fetch_assoc();
$has_bid = (bool)$user_bid;

if ($has_bid) {
    set_flash_message('error', 'You have already placed a bid on this job');
    header("Location: /jobs/view.php?id={$job_id}");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST request received in direct_bid.php: ' . print_r($_POST, true));
    
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
    
    $proposal = clean_input($_POST['proposal']);
    $timeline = (int)$_POST['timeline'];
    
    error_log("Attempting to insert bid: job_id=$job_id, user_id=$user_id, amount=$amount, timeline=$timeline");
    
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
        error_log("Bid placed successfully");
    } else {
        set_flash_message('error', 'Failed to place bid: ' . $conn->error);
        error_log("Failed to place bid: " . $conn->error);
    }
    
    header("Location: /jobs/view.php?id={$job_id}");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Bid on <?php echo htmlspecialchars($job['title']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f8ff;
        }
        h1 {
            color: #1a202c;
            margin-bottom: 20px;
        }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2d3748;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 16px;
        }
        .hint {
            font-size: 14px;
            color: #6E6E6E;
            margin-top: 5px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4a6cf7;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover {
            background-color: #3d5ed4;
        }
        .btn-secondary {
            background-color: #6E6E6E;
        }
        .btn-secondary:hover {
            background-color: #5a5a5a;
        }
    </style>
</head>
<body>
    <h1>Place Bid on: <?php echo htmlspecialchars($job['title']); ?></h1>
    
    <div class="card">
        <form action="" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="form-group">
                <label for="amount">Bid Amount ($)</label>
                <input type="number" id="amount" name="amount" min="<?php echo $job['salary'] * 0.7; ?>" step="0.01" required>
                <div class="hint">
                    Suggested bid range: <?php echo format_money($job['salary'] * 0.7); ?> - <?php echo format_money($job['salary'] * 1.3); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label for="timeline">Delivery Time (Days)</label>
                <input type="number" id="timeline" name="timeline" min="1" required>
            </div>
            
            <div class="form-group">
                <label for="proposal">Your Proposal</label>
                <textarea id="proposal" name="proposal" rows="5" required placeholder="Explain why you're the best fit for this job..."></textarea>
            </div>
            
            <div>
                <button type="submit" class="btn">Submit Bid</button>
                <a href="/jobs/view.php?id=<?php echo $job_id; ?>" class="btn btn-secondary" style="margin-left: 10px;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html> 