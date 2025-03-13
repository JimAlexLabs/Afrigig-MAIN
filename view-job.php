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

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: find-jobs.php');
    exit;
}

$job_id = intval($_GET['id']);

// Get job details
$stmt = $conn->prepare("
    SELECT j.*, u.first_name, u.last_name, u.profile_image, u.email
    FROM jobs j
    JOIN users u ON j.admin_id = u.id
    WHERE j.id = ?
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

// If job doesn't exist or is not open, redirect
if (!$job || $job['status'] !== 'open') {
    header('Location: find-jobs.php');
    exit;
}

// Check if user has already placed a bid
$has_bid = false;
$user_bid = null;

$stmt = $conn->prepare("SELECT * FROM bids WHERE job_id = ? AND freelancer_id = ?");
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $has_bid = true;
    $user_bid = $result->fetch_assoc();
}

// Get bid count
$stmt = $conn->prepare("SELECT COUNT(*) as bid_count FROM bids WHERE job_id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$bid_count = $stmt->get_result()->fetch_assoc()['bid_count'];

// Get similar jobs
$category = $job['category'] ?? '';
$similar_jobs = [];

if (!empty($category)) {
    $stmt = $conn->prepare("
        SELECT * FROM jobs 
        WHERE status = 'open' 
        AND category = ? 
        AND id != ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->bind_param("si", $category, $job_id);
    $stmt->execute();
    $similar_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$page_title = htmlspecialchars($job['title']);

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
            <div class="mb-4">
                <a href="find-jobs.php" class="text-primary hover:text-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Jobs
                </a>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Job Details Column -->
                <div class="lg:col-span-2">
                    <div class="content-card mb-6" data-aos="fade-up">
                        <div class="card-body">
                            <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
                                <h1 class="text-2xl font-bold mb-2 md:mb-0"><?php echo htmlspecialchars($job['title']); ?></h1>
                                <span class="text-2xl font-bold text-primary">$<?php echo number_format($job['salary'], 2); ?></span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-alt text-primary mr-2"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Posted</p>
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
                                    <i class="fas fa-user-friends text-primary mr-2"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Bids</p>
                                        <p class="font-medium"><?php echo $bid_count; ?> freelancer<?php echo $bid_count !== 1 ? 's' : ''; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($job['category'])): ?>
                                <div class="mb-6">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($job['category']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-6">
                                <h2 class="text-lg font-semibold mb-3">Description</h2>
                                <div class="prose max-w-none">
                                    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($job['requirements'])): ?>
                                <div class="mb-6">
                                    <h2 class="text-lg font-semibold mb-3">Requirements</h2>
                                    <div class="prose max-w-none">
                                        <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-center mt-6">
                                <?php if (!$user['is_admin']): ?>
                                    <?php if ($has_bid): ?>
                                        <a href="place-bid.php?job_id=<?php echo $job['id']; ?>" class="btn btn-outline">
                                            <i class="fas fa-edit mr-2"></i> Edit Your Bid
                                        </a>
                                    <?php else: ?>
                                        <a href="place-bid.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-gavel mr-2"></i> Place Bid
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($similar_jobs)): ?>
                        <div class="content-card" data-aos="fade-up" data-aos-delay="100">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i class="fas fa-briefcase"></i>
                                    Similar Jobs
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 gap-4">
                                    <?php foreach ($similar_jobs as $similar_job): ?>
                                        <div class="border-b border-gray-200 pb-4 mb-4 last:border-b-0 last:pb-0 last:mb-0">
                                            <h3 class="text-lg font-semibold mb-2">
                                                <a href="view-job.php?id=<?php echo $similar_job['id']; ?>" class="text-primary hover:text-secondary">
                                                    <?php echo htmlspecialchars($similar_job['title']); ?>
                                                </a>
                                            </h3>
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-lg font-bold text-primary">$<?php echo number_format($similar_job['salary'], 2); ?></span>
                                                <span class="text-sm text-gray-500">Posted <?php echo date('M j, Y', strtotime($similar_job['created_at'])); ?></span>
                                            </div>
                                            <p class="line-clamp-2 text-gray-600 mb-3">
                                                <?php echo htmlspecialchars(substr($similar_job['description'], 0, 150)) . (strlen($similar_job['description']) > 150 ? '...' : ''); ?>
                                            </p>
                                            <a href="view-job.php?id=<?php echo $similar_job['id']; ?>" class="text-primary hover:text-secondary text-sm flex items-center">
                                                View Details <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar Column -->
                <div class="lg:col-span-1">
                    <!-- Client Information -->
                    <div class="content-card mb-6" data-aos="fade-up" data-aos-delay="50">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-user"></i>
                                Client Information
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 rounded-full overflow-hidden mr-3 bg-gray-200 flex items-center justify-center">
                                    <?php if (!empty($job['profile_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($job['profile_image']); ?>" alt="Client" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-user text-gray-400 text-xl"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="font-semibold"><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></h3>
                                    <p class="text-sm text-gray-500">Client</p>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <a href="messages.php?recipient=<?php echo $job['admin_id']; ?>" class="btn btn-outline w-full">
                                    <i class="fas fa-envelope mr-2"></i> Contact Client
                                </a>
                            </div>
                            
                            <div class="text-sm">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-envelope text-primary mr-2"></i>
                                    <span><?php echo htmlspecialchars($job['email']); ?></span>
                                </div>
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-calendar-check text-primary mr-2"></i>
                                    <span>Member since <?php echo date('M Y', strtotime($job['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Job Details -->
                    <div class="content-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-info-circle"></i>
                                Job Details
                            </h2>
                        </div>
                        <div class="card-body">
                            <ul class="space-y-3">
                                <?php if (!empty($job['pages'])): ?>
                                <li class="flex items-center">
                                    <i class="fas fa-file-alt text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Pages</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($job['pages']); ?></p>
                                    </div>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['word_count'])): ?>
                                <li class="flex items-center">
                                    <i class="fas fa-font text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Word Count</p>
                                        <p class="font-medium"><?php echo number_format($job['word_count']); ?></p>
                                    </div>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['format'])): ?>
                                <li class="flex items-center">
                                    <i class="fas fa-file-pdf text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Format</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($job['format']); ?></p>
                                    </div>
                                </li>
                                <?php endif; ?>
                                
                                <?php if (!empty($job['language'])): ?>
                                <li class="flex items-center">
                                    <i class="fas fa-language text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Language</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($job['language']); ?></p>
                                    </div>
                                </li>
                                <?php endif; ?>
                                
                                <li class="flex items-center">
                                    <i class="fas fa-money-bill-wave text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Budget</p>
                                        <p class="font-medium">$<?php echo number_format($job['salary'], 2); ?></p>
                                    </div>
                                </li>
                                
                                <li class="flex items-center">
                                    <i class="fas fa-clock text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Deadline</p>
                                        <p class="font-medium"><?php echo isset($job['deadline']) ? date('M j, Y', strtotime($job['deadline'])) : date('M j, Y', strtotime($job['created_at'] . ' +30 days')); ?></p>
                                    </div>
                                </li>
                            </ul>
                            
                            <?php if (!$user['is_admin']): ?>
                                <div class="mt-6">
                                    <?php if ($has_bid): ?>
                                        <a href="place-bid.php?job_id=<?php echo $job['id']; ?>" class="btn btn-outline w-full">
                                            <i class="fas fa-edit mr-2"></i> Edit Your Bid
                                        </a>
                                    <?php else: ?>
                                        <a href="place-bid.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary w-full">
                                            <i class="fas fa-gavel mr-2"></i> Place Bid
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.prose {
    line-height: 1.6;
}

.prose p {
    margin-bottom: 1rem;
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