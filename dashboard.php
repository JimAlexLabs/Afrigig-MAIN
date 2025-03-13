<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user's jobs based on role
if ($user['is_admin']) {
    // For admins, get jobs they posted
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE admin_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // For freelancers, show all available jobs
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE status = 'open' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$page_title = 'Dashboard';

// Add custom CSS and JS for dashboard
$custom_css = [
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
    'assets/css/dashboard.css'
];
$custom_js = [
    'assets/js/dashboard.js'
];

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Sidebar -->
        <div class="md:col-span-1">
            <div class="sidebar bg-white rounded-lg shadow p-6">
                <div class="text-center mb-6">
                    <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'assets/images/default-avatar.png'); ?>" 
                         alt="Profile" 
                         class="w-32 h-32 rounded-full mx-auto mb-4 object-cover">
                    <h2 class="text-xl font-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-sm text-primary mt-2"><?php echo $user['is_admin'] ? 'Administrator' : 'Freelancer'; ?></p>
                </div>
                
                <nav class="space-y-2">
                    <a href="dashboard.php" class="sidebar-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="profile.php" class="sidebar-link">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                    <?php if ($user['is_admin']): ?>
                        <a href="post-job.php" class="sidebar-link">
                            <i class="fas fa-plus-circle"></i>
                            Post New Job
                        </a>
                        <a href="manage-jobs.php" class="sidebar-link">
                            <i class="fas fa-briefcase"></i>
                            Manage Jobs
                        </a>
                        <a href="manage-users.php" class="sidebar-link">
                            <i class="fas fa-users"></i>
                            Manage Users
                        </a>
                        <a href="admin-support.php" class="sidebar-link">
                            <i class="fas fa-headset"></i>
                            Support Dashboard
                        </a>
                    <?php else: ?>
                        <a href="find-jobs.php" class="sidebar-link">
                            <i class="fas fa-search"></i>
                            Find Jobs
                        </a>
                        <a href="my-jobs.php" class="sidebar-link">
                            <i class="fas fa-briefcase"></i>
                            My Jobs
                        </a>
                        <a href="my-bids.php" class="sidebar-link">
                            <i class="fas fa-gavel"></i>
                            My Bids
                        </a>
                    <?php endif; ?>
                    <a href="messages.php" class="sidebar-link">
                        <i class="fas fa-envelope"></i>
                        Messages
                    </a>
                    <a href="settings.php" class="sidebar-link">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                    <a href="logout.php" class="sidebar-link text-red-600">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="md:col-span-3">
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <?php if ($user['is_admin']): ?>
                    <div class="stats-card">
                        <h3 class="text-lg font-semibold mb-2">
                            <i class="fas fa-briefcase mr-2"></i>
                            Posted Jobs
                        </h3>
                        <p class="text-3xl font-bold text-primary">
                            <?php echo count($jobs); ?>
                        </p>
                    </div>
                    
                    <div class="stats-card">
                        <h3 class="text-lg font-semibold mb-2">
                            <i class="fas fa-check-circle mr-2"></i>
                            Active Jobs
                        </h3>
                        <p class="text-3xl font-bold text-primary">
                            <?php echo count(array_filter($jobs, function($job) { return $job['status'] === 'open'; })); ?>
                        </p>
                    </div>
                    
                    <div class="stats-card">
                        <h3 class="text-lg font-semibold mb-2">
                            <i class="fas fa-flag-checkered mr-2"></i>
                            Completed Jobs
                        </h3>
                        <p class="text-3xl font-bold text-primary">
                            <?php echo count(array_filter($jobs, function($job) { return $job['status'] === 'completed'; })); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="stats-card">
                        <h3 class="text-lg font-semibold mb-2">
                            <i class="fas fa-briefcase mr-2"></i>
                            Available Jobs
                        </h3>
                        <p class="text-3xl font-bold text-primary">
                            <?php echo count($jobs); ?>
                        </p>
                    </div>
                    
                    <div class="stats-card">
                        <h3 class="text-lg font-semibold mb-2">
                            <i class="fas fa-clock mr-2"></i>
                            Recent Jobs
                        </h3>
                        <p class="text-3xl font-bold text-primary">
                            <?php echo count(array_filter($jobs, function($job) { 
                                return strtotime($job['created_at']) > strtotime('-7 days'); 
                            })); ?>
                        </p>
                    </div>
                    
                    <div class="stats-card">
                        <h3 class="text-lg font-semibold mb-2">
                            <i class="fas fa-dollar-sign mr-2"></i>
                            High Paying Jobs
                        </h3>
                        <p class="text-3xl font-bold text-primary">
                            <?php echo count(array_filter($jobs, function($job) { 
                                return $job['salary'] > 1000; 
                            })); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Jobs -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold">
                        <i class="fas fa-list mr-2"></i>
                        <?php echo $user['is_admin'] ? 'Recently Posted Jobs' : 'Available Jobs'; ?>
                    </h3>
                    <a href="<?php echo $user['is_admin'] ? 'manage-jobs.php' : 'find-jobs.php'; ?>" 
                       class="text-primary hover:text-secondary flex items-center">
                        View All
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php if (empty($jobs)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-search text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-600">No jobs found</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="jobs-table w-full">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Salary</th>
                                    <th>Deadline</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td>
                                        <a href="view-job.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary font-medium">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $job['status']; ?>">
                                            <?php echo ucfirst(htmlspecialchars($job['status'])); ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($job['salary'], 2); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                    <td>
                                        <?php if (!$user['is_admin'] && $job['status'] === 'open'): ?>
                                            <a href="javascript:void(0)" 
                                               data-job-id="<?php echo $job['id']; ?>"
                                               onclick="openSkillAssessment(<?php echo $job['id']; ?>)"
                                               class="btn btn-primary py-1 px-3 text-sm place-bid-btn">
                                                <i class="fas fa-gavel mr-1"></i> Place Bid
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- AI Skill Assessment Modal -->
<div class="modal-overlay" id="skill-assessment-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">AI Skill Assessment</h2>
            <button class="modal-close" onclick="closeAssessmentModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="progress-container">
                <div class="progress-bar" style="width: 0%"></div>
            </div>
            <p class="mb-4">Complete this assessment to verify your skills. This costs $20 (50% refundable if selected, 90% refundable if not selected).</p>
            <div id="assessment-questions">
                <!-- Questions will be loaded here -->
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="prev-question" disabled>Previous</button>
            <button class="btn btn-primary" id="next-question">Next</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 