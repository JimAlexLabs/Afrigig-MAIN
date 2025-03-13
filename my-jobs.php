<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check if user is a freelancer
if ($user['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

// Get active tab
$active_tab = isset($_GET['tab']) ? clean_input($_GET['tab']) : 'active';
if (!in_array($active_tab, ['active', 'completed', 'bids'])) {
    $active_tab = 'active';
}

// Get freelancer's active jobs (where bid was accepted)
$active_jobs = [];
$stmt = $conn->prepare("
    SELECT j.*, b.amount as bid_amount, b.delivery_time, b.status as bid_status, 
           u.first_name, u.last_name, u.profile_image
    FROM jobs j
    JOIN bids b ON j.id = b.job_id
    JOIN users u ON j.admin_id = u.id
    WHERE b.freelancer_id = ? AND b.status = 'accepted' AND j.status = 'in_progress'
    ORDER BY j.deadline ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get freelancer's completed jobs
$completed_jobs = [];
$stmt = $conn->prepare("
    SELECT j.*, b.amount as bid_amount, b.delivery_time, b.status as bid_status, 
           u.first_name, u.last_name, u.profile_image
    FROM jobs j
    JOIN bids b ON j.id = b.job_id
    JOIN users u ON j.admin_id = u.id
    WHERE b.freelancer_id = ? AND j.status = 'completed'
    ORDER BY j.updated_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get freelancer's active bids
$active_bids = [];
$stmt = $conn->prepare("
    SELECT j.*, b.amount as bid_amount, b.delivery_time, b.status as bid_status, 
           b.created_at as bid_date, u.first_name, u.last_name, u.profile_image
    FROM bids b
    JOIN jobs j ON b.job_id = j.id
    JOIN users u ON j.admin_id = u.id
    WHERE b.freelancer_id = ? AND b.status = 'pending' AND j.status = 'open'
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'My Jobs';

// Add custom CSS and JS
$custom_css = [
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://unpkg.com/aos@2.3.1/dist/aos.css',
    'assets/css/dashboard.css'
];
$custom_js = [
    'https://unpkg.com/aos@2.3.1/dist/aos.js'
];

ob_start();
?>

<div class="dashboard-layout">
    <!-- Include sidebar from dashboard -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container">
            <h1 class="text-2xl font-bold mb-6" data-aos="fade-up">My Jobs</h1>
            
            <!-- Tabs -->
            <div class="tabs mb-6" data-aos="fade-up">
                <a href="my-jobs.php?tab=active" class="tab <?php echo $active_tab === 'active' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase mr-2"></i> Active Jobs
                    <?php if (count($active_jobs) > 0): ?>
                        <span class="badge"><?php echo count($active_jobs); ?></span>
                    <?php endif; ?>
                </a>
                <a href="my-jobs.php?tab=completed" class="tab <?php echo $active_tab === 'completed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle mr-2"></i> Completed Jobs
                    <?php if (count($completed_jobs) > 0): ?>
                        <span class="badge"><?php echo count($completed_jobs); ?></span>
                    <?php endif; ?>
                </a>
                <a href="my-jobs.php?tab=bids" class="tab <?php echo $active_tab === 'bids' ? 'active' : ''; ?>">
                    <i class="fas fa-gavel mr-2"></i> My Bids
                    <?php if (count($active_bids) > 0): ?>
                        <span class="badge"><?php echo count($active_bids); ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Active Jobs Tab -->
            <?php if ($active_tab === 'active'): ?>
                <?php if (empty($active_jobs)): ?>
                    <div class="content-card" data-aos="fade-up">
                        <div class="card-body text-center py-12">
                            <i class="fas fa-briefcase text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-xl font-semibold mb-2">No active jobs</h3>
                            <p class="text-gray-500 mb-6">You don't have any active jobs at the moment.</p>
                            <a href="find-jobs.php" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i> Find Jobs
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6" data-aos="fade-up">
                        <?php foreach ($active_jobs as $job): ?>
                            <div class="content-card">
                                <div class="card-body">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                        <h3 class="text-xl font-semibold mb-2 md:mb-0">
                                            <a href="view-job.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </h3>
                                        <div class="flex items-center">
                                            <span class="text-lg font-bold text-primary mr-4">$<?php echo number_format($job['bid_amount'], 2); ?></span>
                                            <a href="submit-work.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-upload mr-2"></i> Submit Work
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-user text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Client</p>
                                                <p class="font-medium"><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Start Date</p>
                                                <p class="font-medium"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-clock text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Deadline</p>
                                                <p class="font-medium"><?php echo isset($job['deadline']) ? date('M j, Y', strtotime($job['deadline'])) : date('M j, Y', strtotime($job['created_at'] . ' +30 days')); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-hourglass-half text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Delivery Time</p>
                                                <p class="font-medium"><?php echo $job['delivery_time']; ?> days</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <a href="messages.php?recipient=<?php echo $job['admin_id']; ?>" class="btn btn-sm btn-outline mr-2">
                                                <i class="fas fa-envelope mr-1"></i> Message Client
                                            </a>
                                            <a href="view-job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye mr-1"></i> View Details
                                            </a>
                                        </div>
                                        
                                        <?php
                                        // Calculate days remaining
                                        $deadline = isset($job['deadline']) ? strtotime($job['deadline']) : strtotime($job['created_at'] . ' +30 days');
                                        $now = time();
                                        $days_remaining = ceil(($deadline - $now) / (60 * 60 * 24));
                                        
                                        $status_class = 'bg-green-100 text-green-800';
                                        $status_text = 'On Track';
                                        $status_icon = 'fa-check-circle';
                                        
                                        if ($days_remaining <= 2) {
                                            $status_class = 'bg-red-100 text-red-800';
                                            $status_text = 'Urgent';
                                            $status_icon = 'fa-exclamation-circle';
                                        } elseif ($days_remaining <= 5) {
                                            $status_class = 'bg-yellow-100 text-yellow-800';
                                            $status_text = 'Due Soon';
                                            $status_icon = 'fa-exclamation-triangle';
                                        }
                                        ?>
                                        
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                            <i class="fas <?php echo $status_icon; ?> mr-1"></i> <?php echo $status_text; ?> (<?php echo $days_remaining; ?> days left)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            
            <!-- Completed Jobs Tab -->
            <?php elseif ($active_tab === 'completed'): ?>
                <?php if (empty($completed_jobs)): ?>
                    <div class="content-card" data-aos="fade-up">
                        <div class="card-body text-center py-12">
                            <i class="fas fa-check-circle text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-xl font-semibold mb-2">No completed jobs</h3>
                            <p class="text-gray-500 mb-6">You haven't completed any jobs yet.</p>
                            <a href="find-jobs.php" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i> Find Jobs
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6" data-aos="fade-up">
                        <?php foreach ($completed_jobs as $job): ?>
                            <div class="content-card">
                                <div class="card-body">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                        <h3 class="text-xl font-semibold mb-2 md:mb-0">
                                            <a href="view-job.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </h3>
                                        <div class="flex items-center">
                                            <span class="text-lg font-bold text-primary mr-4">$<?php echo number_format($job['bid_amount'], 2); ?></span>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i> Completed
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-user text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Client</p>
                                                <p class="font-medium"><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Start Date</p>
                                                <p class="font-medium"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar-check text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Completion Date</p>
                                                <p class="font-medium"><?php echo date('M j, Y', strtotime($job['updated_at'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-star text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Rating</p>
                                                <p class="font-medium">
                                                    <?php if (isset($job['rating']) && $job['rating'] > 0): ?>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $job['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                                        <?php endfor; ?>
                                                    <?php else: ?>
                                                        Not rated yet
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <a href="messages.php?recipient=<?php echo $job['admin_id']; ?>" class="btn btn-sm btn-outline mr-2">
                                                <i class="fas fa-envelope mr-1"></i> Message Client
                                            </a>
                                            <a href="view-job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye mr-1"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            
            <!-- My Bids Tab -->
            <?php elseif ($active_tab === 'bids'): ?>
                <?php if (empty($active_bids)): ?>
                    <div class="content-card" data-aos="fade-up">
                        <div class="card-body text-center py-12">
                            <i class="fas fa-gavel text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-xl font-semibold mb-2">No active bids</h3>
                            <p class="text-gray-500 mb-6">You haven't placed any bids on jobs yet.</p>
                            <a href="find-jobs.php" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i> Find Jobs
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-6" data-aos="fade-up">
                        <?php foreach ($active_bids as $bid): ?>
                            <div class="content-card">
                                <div class="card-body">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                        <h3 class="text-xl font-semibold mb-2 md:mb-0">
                                            <a href="view-job.php?id=<?php echo $bid['id']; ?>" class="text-primary hover:text-secondary">
                                                <?php echo htmlspecialchars($bid['title']); ?>
                                            </a>
                                        </h3>
                                        <div class="flex items-center">
                                            <span class="text-lg font-bold text-primary mr-4">$<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                            <a href="place-bid.php?job_id=<?php echo $bid['id']; ?>" class="btn btn-outline">
                                                <i class="fas fa-edit mr-2"></i> Edit Bid
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-user text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Client</p>
                                                <p class="font-medium"><?php echo htmlspecialchars($bid['first_name'] . ' ' . $bid['last_name']); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Bid Date</p>
                                                <p class="font-medium"><?php echo date('M j, Y', strtotime($bid['bid_date'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-clock text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Delivery Time</p>
                                                <p class="font-medium"><?php echo $bid['delivery_time']; ?> days</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-money-bill-wave text-primary mr-2"></i>
                                            <div>
                                                <p class="text-sm text-gray-500">Job Budget</p>
                                                <p class="font-medium">$<?php echo number_format($bid['salary'], 2); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center">
                                            <a href="messages.php?recipient=<?php echo $bid['admin_id']; ?>" class="btn btn-sm btn-outline mr-2">
                                                <i class="fas fa-envelope mr-1"></i> Message Client
                                            </a>
                                            <a href="view-job.php?id=<?php echo $bid['id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-eye mr-1"></i> View Details
                                            </a>
                                        </div>
                                        
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-hourglass-half mr-1"></i> Awaiting Response
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
.tabs {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
    overflow-x: auto;
}

.tab {
    padding: 0.75rem 1.25rem;
    font-weight: 500;
    color: #4a5568;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}

.tab:hover {
    color: var(--primary);
}

.tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    background-color: var(--primary);
    border-radius: 9999px;
    margin-left: 0.5rem;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
    });
</script>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 