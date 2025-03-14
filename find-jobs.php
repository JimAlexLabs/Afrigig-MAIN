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

// Get available jobs
$stmt = $conn->prepare("
    SELECT j.*, u.first_name, u.last_name, COUNT(b.id) as bid_count
    FROM jobs j
    JOIN users u ON j.admin_id = u.id
    LEFT JOIN bids b ON j.id = b.job_id
    WHERE j.status = 'open'
    GROUP BY j.id
    ORDER BY j.created_at DESC
");
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Find Jobs';

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
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">Available Jobs</h1>

        <!-- Filter Section -->
        <div class="filter-section mb-8">
            <h3 class="text-lg font-semibold mb-4">Filter Jobs</h3>
            <form class="filter-form" id="job-filter-form">
                <div>
                    <input type="text" id="keyword-filter" placeholder="Search by keyword">
                </div>
                <div>
                    <select id="category-filter">
                        <option value="">All Categories</option>
                        <option value="writing">Academic Writing</option>
                        <option value="programming">Programming</option>
                        <option value="design">Design</option>
                        <option value="marketing">Marketing</option>
                        <option value="legal">Legal</option>
                        <option value="finance">Finance</option>
                        <option value="multimedia">Multimedia</option>
                    </select>
                </div>
                <div>
                    <select id="salary-filter">
                        <option value="">Any Salary</option>
                        <option value="0-100">$0 - $100</option>
                        <option value="100-500">$100 - $500</option>
                        <option value="500-1000">$500 - $1000</option>
                        <option value="1000-2000">$1000 - $2000</option>
                        <option value="2000+">$2000+</option>
                    </select>
                </div>
                <div>
                    <select id="deadline-filter">
                        <option value="">Any Deadline</option>
                        <option value="march">March 2025</option>
                        <option value="april">April 2025</option>
                        <option value="may">May 2025</option>
                        <option value="june">June 2025</option>
                    </select>
                </div>
                <div>
                    <button type="submit" id="apply-filters">Apply Filters</button>
                </div>
            </form>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="bg-white shadow rounded-lg p-6 text-center">
                <p class="text-gray-600">No jobs available at the moment</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($jobs as $job): ?>
                    <?php 
                    // Determine category based on job title or description
                    $category = 'other';
                    $title = strtolower($job['title']);
                    $desc = strtolower($job['description']);
                    
                    if (strpos($title, 'research') !== false || strpos($title, 'writing') !== false || 
                        strpos($desc, 'research') !== false || strpos($desc, 'writing') !== false || 
                        strpos($title, 'content') !== false || strpos($desc, 'content') !== false) {
                        $category = 'writing';
                    } elseif (strpos($title, 'development') !== false || strpos($title, 'programming') !== false || 
                             strpos($desc, 'develop') !== false || strpos($title, 'app') !== false || 
                             strpos($title, 'website') !== false || strpos($title, 'blockchain') !== false) {
                        $category = 'programming';
                    } elseif (strpos($title, 'design') !== false || strpos($desc, 'design') !== false || 
                             strpos($title, 'logo') !== false || strpos($title, 'ui') !== false || 
                             strpos($title, 'ux') !== false) {
                        $category = 'design';
                    } elseif (strpos($title, 'marketing') !== false || strpos($desc, 'marketing') !== false || 
                             strpos($title, 'seo') !== false || strpos($desc, 'seo') !== false) {
                        $category = 'marketing';
                    } elseif (strpos($title, 'legal') !== false || strpos($desc, 'legal') !== false || 
                             strpos($title, 'contract') !== false) {
                        $category = 'legal';
                    } elseif (strpos($title, 'finance') !== false || strpos($desc, 'finance') !== false || 
                             strpos($title, 'financial') !== false || strpos($title, 'cryptocurrency') !== false) {
                        $category = 'finance';
                    } elseif (strpos($title, 'video') !== false || strpos($desc, 'video') !== false || 
                             strpos($title, 'podcast') !== false || strpos($title, 'animation') !== false || 
                             strpos($title, 'virtual reality') !== false || strpos($title, 'vr') !== false) {
                        $category = 'multimedia';
                    }
                    ?>
                    <div class="bg-white shadow rounded-lg p-6" data-category="<?php echo $category; ?>">
                        <div class="flex justify-between items-start">
                            <div>
                                <h2 class="text-xl font-semibold mb-2">
                                    <a href="view-job.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </a>
                                </h2>
                                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($job['description']); ?></p>
                                <div class="flex gap-4 text-sm text-gray-500">
                                    <span>Posted by: <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></span>
                                    <span>Budget: $<?php echo number_format($job['salary'], 2); ?></span>
                                    <span>Bids: <?php echo $job['bid_count']; ?></span>
                                    <span>Posted: <?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                                </div>
                            </div>
                            <a href="javascript:void(0)" 
                               onclick="openSkillAssessment(<?php echo $job['id']; ?>)" 
                               class="btn btn-primary place-bid-btn">Place Bid</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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