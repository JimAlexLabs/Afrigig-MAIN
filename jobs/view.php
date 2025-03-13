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
    LEFT JOIN users u ON j.client_id = u.id
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
    JOIN users u ON b.freelancer_id = u.id
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
        WHERE job_id = ? AND freelancer_id = ?
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
            INSERT INTO bids (job_id, freelancer_id, amount, proposal, timeline)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $user_id = get_current_user_id();
        $proposal = clean_input($_POST['proposal']);
        $timeline = (int)$_POST['timeline'];
        
        $stmt->bind_param('iidsi', $job_id, $user_id, $amount, $proposal, $timeline);
        
        if ($stmt->execute()) {
            // Notify job owner
            create_notification(
                $job['client_id'],
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
}

// Page title
$page_title = $job['title'];

// Additional styles
$additional_styles = '
<style>
    .job-header {
        background: var(--surface-color);
        padding: 2rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
    }
    
    .client-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .client-avatar {
        width: 4rem;
        height: 4rem;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .job-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
    }
    
    .meta-icon {
        width: 1.5rem;
        height: 1.5rem;
    }
    
    .job-description {
        background: var(--surface-color);
        padding: 2rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
    }
    
    .attachments {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .attachment {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--background-color);
        border-radius: 0.5rem;
        color: var(--text-primary);
        text-decoration: none;
    }
    
    .bids-section {
        background: var(--surface-color);
        padding: 2rem;
        border-radius: 1rem;
    }
    
    .bid-card {
        display: flex;
        gap: 1.5rem;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .bid-card:last-child {
        border-bottom: none;
    }
    
    .freelancer-info {
        flex: 1;
    }
    
    .bid-details {
        text-align: right;
    }
    
    .bid-form {
        background: var(--surface-color);
        padding: 2rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
    }
</style>
';

// Start output buffering
ob_start();
?>

<div class="job-header">
    <div class="flex justify-between items-start mb-6">
        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($job['title']); ?></h1>
        <div class="badge badge-<?php echo get_status_color($job['status']); ?>">
            <?php echo ucfirst($job['status']); ?>
        </div>
    </div>
    
    <div class="client-info">
        <img src="<?php echo $job['profile_image'] ?? '/assets/images/default-avatar.png'; ?>" 
             alt="<?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>"
             class="client-avatar">
        <div>
            <h3 class="font-bold"><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></h3>
            <div class="text-secondary">
                <span class="text-sm">Posted on <?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
            </div>
        </div>
    </div>
    
    <div class="job-meta">
        <div class="meta-item">
            <svg class="meta-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            <div>
                <div class="text-sm">Category</div>
                <div class="font-bold"><?php echo isset($job['category_name']) ? htmlspecialchars($job['category_name']) : 'N/A'; ?></div>
            </div>
        </div>
        
        <div class="meta-item">
            <svg class="meta-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <div class="text-sm">Budget</div>
                <div class="font-bold"><?php echo format_money($job['salary']); ?></div>
            </div>
        </div>
        
        <div class="meta-item">
            <svg class="meta-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <div class="text-sm">Deadline</div>
                <div class="font-bold"><?php echo time_remaining($job['deadline']); ?> left</div>
            </div>
        </div>
        
        <div class="meta-item">
            <svg class="meta-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <div>
                <div class="text-sm">Bids</div>
                <div class="font-bold"><?php echo $job['bid_count']; ?> bids</div>
            </div>
        </div>
    </div>
    
    <?php if ($job['bid_count'] > 0): ?>
        <div class="flex gap-4 text-sm text-secondary">
            <div>Lowest bid: <?php echo format_money($job['lowest_bid']); ?></div>
            <div>Average bid: <?php echo format_money($job['average_bid']); ?></div>
            <div>Highest bid: <?php echo format_money($job['highest_bid']); ?></div>
        </div>
    <?php endif; ?>
</div>

<div class="job-description">
    <h2 class="text-xl font-bold mb-4">Job Description</h2>
    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
    
    <?php if ($job['attachments']): ?>
        <div class="attachments">
            <?php foreach (json_decode($job['attachments']) as $attachment): ?>
                <a href="/uploads/<?php echo $attachment; ?>" class="attachment" target="_blank">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                    <?php echo htmlspecialchars($attachment); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (is_logged_in() && !$has_bid && $job['status'] === 'open' && $job['client_id'] !== get_current_user_id()): ?>
    <div class="bid-form">
        <h2 class="text-xl font-bold mb-4">Place Your Bid</h2>
        <form method="POST" data-validate>
            <input type="hidden" name="action" value="place_bid">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="form-group">
                    <label for="amount" class="form-label">Bid Amount</label>
                    <input type="number" id="amount" name="amount" class="form-input" 
                           min="1" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="timeline" class="form-label">Timeline (days)</label>
                    <input type="number" id="timeline" name="timeline" class="form-input" 
                           min="1" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="proposal" class="form-label">Proposal</label>
                <textarea id="proposal" name="proposal" class="form-input" rows="4" required></textarea>
                <div class="text-sm text-secondary mt-1">
                    Explain why you're the best person for this job and how you plan to complete it.
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Submit Bid</button>
        </form>
    </div>
<?php endif; ?>

<?php if ($has_bid): ?>
    <div class="alert alert-info mb-4">
        You have already placed a bid on this job.
        <?php if ($user_bid['status'] === 'pending'): ?>
            Your bid is currently under review.
        <?php elseif ($user_bid['status'] === 'accepted'): ?>
            Congratulations! Your bid has been accepted.
        <?php else: ?>
            Your bid was <?php echo $user_bid['status']; ?>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="bids-section">
    <h2 class="text-xl font-bold mb-4">Bids (<?php echo count($bids); ?>)</h2>
    
    <?php if (empty($bids)): ?>
        <div class="text-center text-secondary py-8">
            No bids yet. Be the first to bid on this job!
        </div>
    <?php else: ?>
        <?php foreach ($bids as $bid): ?>
            <div class="bid-card">
                <img src="<?php echo $bid['profile_image'] ?? '/assets/images/default-avatar.png'; ?>" 
                     alt="<?php echo htmlspecialchars($bid['first_name'] . ' ' . $bid['last_name']); ?>"
                     class="w-12 h-12 rounded-full object-cover">
                
                <div class="freelancer-info">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold">
                            <?php echo htmlspecialchars($bid['first_name'] . ' ' . $bid['last_name']); ?>
                        </h3>
                        <?php if ($bid['status'] === 'accepted'): ?>
                            <span class="badge badge-success">Accepted</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-sm text-secondary mb-2">
                        <span>Bid placed <?php echo time_ago($bid['created_at']); ?></span>
                    </div>
                    
                    <p class="text-secondary">
                        <?php echo nl2br(htmlspecialchars($bid['proposal'])); ?>
                    </p>
                </div>
                
                <div class="bid-details">
                    <div class="text-xl font-bold mb-1">
                        <?php echo format_money($bid['amount']); ?>
                    </div>
                    <div class="text-sm text-secondary mb-2">
                        <?php echo $bid['timeline']; ?> days
                    </div>
                    
                    <?php if (is_admin() && $job['status'] === 'open' && $bid['status'] === 'pending'): ?>
                        <div class="flex gap-2 mt-4">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="accept_bid">
                                <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="btn btn-success btn-sm">Accept</button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="reject_bid">
                                <input type="hidden" name="bid_id" value="<?php echo $bid['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout template
require_once __DIR__ . '/../views/partials/layout.php'; 