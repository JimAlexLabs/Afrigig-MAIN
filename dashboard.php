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
    
    // Count total jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $total_jobs = $stmt->get_result()->fetch_assoc()['total'];
    
    // Count active jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE admin_id = ? AND status = 'open'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $active_jobs = $stmt->get_result()->fetch_assoc()['total'];
    
    // Count completed jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE admin_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $completed_jobs = $stmt->get_result()->fetch_assoc()['total'];
} else {
    // For freelancers, show all available jobs
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE status = 'open' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Count available jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE status = 'open'");
    $stmt->execute();
    $available_jobs = $stmt->get_result()->fetch_assoc()['total'];
    
    // Count recent jobs (last 7 days)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE status = 'open' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recent_jobs = $stmt->get_result()->fetch_assoc()['total'];
    
    // Count high paying jobs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM jobs WHERE status = 'open' AND salary > 1000");
    $stmt->execute();
    $high_paying_jobs = $stmt->get_result()->fetch_assoc()['total'];
}

// Get unread messages count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE recipient_id = ? AND is_read = 0");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$unread_messages = $stmt->get_result()->fetch_assoc()['total'];

$page_title = 'Dashboard';

// Add custom CSS and JS for dashboard
$custom_css = [
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://unpkg.com/aos@2.3.1/dist/aos.css',
    'assets/css/dashboard.css'
];
$custom_js = [
    'https://unpkg.com/aos@2.3.1/dist/aos.js',
    'assets/js/dashboard.js'
];

ob_start();
?>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-profile">
            <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'assets/images/default-avatar.png'); ?>" 
                 alt="Profile" 
                 class="sidebar-avatar">
            <h2 class="sidebar-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
            <p class="sidebar-email"><?php echo htmlspecialchars($user['email']); ?></p>
            <span class="sidebar-role"><?php echo $user['is_admin'] ? 'Administrator' : 'Freelancer'; ?></span>
        </div>
        
        <nav class="sidebar-nav">
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
            
            <div class="sidebar-divider"></div>
            
            <a href="messages.php" class="sidebar-link">
                <i class="fas fa-envelope"></i>
                Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="notification-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="settings.php" class="sidebar-link">
                <i class="fas fa-cog"></i>
                Settings
            </a>
            <a href="logout.php" class="sidebar-link logout">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="navbar">
            <div class="navbar-brand">
                <img src="assets/images/logo.png" alt="Afrigig Logo">
                <h1>Afrigig</h1>
            </div>
            
            <div class="navbar-menu">
                <div class="navbar-item">
                    <a href="messages.php" class="navbar-link">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_messages > 0): ?>
                            <span class="notification-badge"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="navbar-item">
                    <a href="notifications.php" class="navbar-link">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
                
                <div class="profile-dropdown">
                    <div class="profile-toggle">
                        <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'assets/images/default-avatar.png'); ?>" 
                             alt="Profile" 
                             class="profile-avatar">
                        <div class="profile-info">
                            <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                            <p><?php echo $user['is_admin'] ? 'Administrator' : 'Freelancer'; ?></p>
                        </div>
                    </div>
                    
                    <div class="profile-dropdown-menu">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            My Profile
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="container">
            <h1 class="text-2xl font-bold mb-6" data-aos="fade-up">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
            
            <!-- Quick Stats -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
                <?php if ($user['is_admin']): ?>
                    <div class="stats-card">
                        <h3>
                            <i class="fas fa-briefcase"></i>
                            Posted Jobs
                        </h3>
                        <p><?php echo $total_jobs; ?></p>
                    </div>
                    
                    <div class="stats-card">
                        <h3>
                            <i class="fas fa-check-circle"></i>
                            Active Jobs
                        </h3>
                        <p><?php echo $active_jobs; ?></p>
                    </div>
                    
                    <div class="stats-card">
                        <h3>
                            <i class="fas fa-flag-checkered"></i>
                            Completed Jobs
                        </h3>
                        <p><?php echo $completed_jobs; ?></p>
                    </div>
                <?php else: ?>
                    <div class="stats-card">
                        <h3>
                            <i class="fas fa-briefcase"></i>
                            Available Jobs
                        </h3>
                        <p><?php echo $available_jobs; ?></p>
                    </div>
                    
                    <div class="stats-card">
                        <h3>
                            <i class="fas fa-clock"></i>
                            Recent Jobs
                        </h3>
                        <p><?php echo $recent_jobs; ?></p>
                    </div>
                    
                    <div class="stats-card">
                        <h3>
                            <i class="fas fa-dollar-sign"></i>
                            High Paying Jobs
                        </h3>
                        <p><?php echo $high_paying_jobs; ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Jobs -->
            <div class="content-card" data-aos="fade-up" data-aos-delay="200">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-list"></i>
                        <?php echo $user['is_admin'] ? 'Recently Posted Jobs' : 'Available Jobs'; ?>
                    </h2>
                    <a href="<?php echo $user['is_admin'] ? 'manage-jobs.php' : 'find-jobs.php'; ?>" class="card-action">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="card-body">
                    <?php if (empty($jobs)): ?>
                        <div class="card-empty">
                            <i class="fas fa-search"></i>
                            <p>No jobs found</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="jobs-table">
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
                                            <a href="view-job.php?id=<?php echo $job['id']; ?>" class="job-title">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $job['status']; ?>">
                                                <?php echo ucfirst(htmlspecialchars($job['status'])); ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo number_format($job['salary'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($job['deadline'])); ?></td>
                                        <td>
                                            <?php if (!$user['is_admin'] && $job['status'] === 'open'): ?>
                                                <a href="javascript:void(0)" 
                                                   data-job-id="<?php echo $job['id']; ?>"
                                                   onclick="openSkillAssessment(<?php echo $job['id']; ?>)"
                                                   class="btn btn-primary">
                                                    <i class="fas fa-gavel"></i> Place Bid
                                                </a>
                                            <?php elseif ($user['is_admin']): ?>
                                                <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline">
                                                    <i class="fas fa-edit"></i> Edit
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
            
            <!-- Quick Actions -->
            <div class="content-card" data-aos="fade-up" data-aos-delay="300">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </h2>
                </div>
                
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if ($user['is_admin']): ?>
                            <a href="post-job.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Post New Job
                            </a>
                            <a href="manage-jobs.php" class="btn btn-outline">
                                <i class="fas fa-briefcase"></i> Manage Jobs
                            </a>
                            <a href="manage-users.php" class="btn btn-outline">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        <?php else: ?>
                            <a href="find-jobs.php" class="btn btn-primary">
                                <i class="fas fa-search"></i> Find Jobs
                            </a>
                            <a href="my-jobs.php" class="btn btn-outline">
                                <i class="fas fa-briefcase"></i> My Jobs
                            </a>
                            <a href="my-bids.php" class="btn btn-outline">
                                <i class="fas fa-gavel"></i> My Bids
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
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
            <button class="btn btn-outline" id="prev-question" disabled>Previous</button>
            <button class="btn btn-primary" id="next-question">Next</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // Mobile sidebar toggle
        const sidebarToggle = document.querySelector('.navbar-brand');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
    });
</script>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 