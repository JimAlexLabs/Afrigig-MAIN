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

// Get filter parameters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$min_salary = isset($_GET['min_salary']) ? floatval($_GET['min_salary']) : 0;
$max_salary = isset($_GET['max_salary']) ? floatval($_GET['max_salary']) : 0;
$sort_by = isset($_GET['sort_by']) ? clean_input($_GET['sort_by']) : 'created_at';
$sort_order = isset($_GET['sort_order']) ? clean_input($_GET['sort_order']) : 'DESC';

// Validate sort parameters
$allowed_sort_fields = ['created_at', 'salary', 'deadline', 'title'];
$allowed_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'created_at';
}

if (!in_array(strtoupper($sort_order), $allowed_sort_orders)) {
    $sort_order = 'DESC';
}

// Build query
$query = "SELECT * FROM jobs WHERE status = 'open'";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($min_salary > 0) {
    $query .= " AND salary >= ?";
    $params[] = $min_salary;
    $types .= "d";
}

if ($max_salary > 0) {
    $query .= " AND salary <= ?";
    $params[] = $max_salary;
    $types .= "d";
}

$query .= " ORDER BY {$sort_by} {$sort_order}";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get job categories for filter
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM jobs WHERE category IS NOT NULL AND category != '' ORDER BY category");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

$page_title = 'Find Jobs';

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
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold" data-aos="fade-up">Find Jobs</h1>
                <div class="flex items-center">
                    <span class="text-gray-500 mr-2"><?php echo count($jobs); ?> jobs found</span>
                    <button id="toggle-filters" class="btn btn-outline">
                        <i class="fas fa-filter mr-2"></i> Filters
                    </button>
                </div>
            </div>

            <!-- Modern Filter Section -->
            <div id="filter-section" class="content-card mb-6" data-aos="fade-up" style="display: none;">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-sliders-h"></i>
                        Filter Jobs
                    </h2>
                </div>
                <div class="card-body">
                    <form action="" method="GET" class="filter-form">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                    <input type="text" id="search" name="search" 
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="Job title or keywords"
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            
                            <div>
                                <label for="min_salary" class="block text-sm font-medium text-gray-700 mb-2">Min Salary ($)</label>
                                <input type="number" id="min_salary" name="min_salary" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="Minimum"
                                       value="<?php echo $min_salary > 0 ? $min_salary : ''; ?>">
                            </div>
                            
                            <div>
                                <label for="max_salary" class="block text-sm font-medium text-gray-700 mb-2">Max Salary ($)</label>
                                <input type="number" id="max_salary" name="max_salary" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                       placeholder="Maximum"
                                       value="<?php echo $max_salary > 0 ? $max_salary : ''; ?>">
                            </div>
                            
                            <div>
                                <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                                <div class="flex">
                                    <select id="sort_by" name="sort_by" 
                                            class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-primary focus:border-primary">
                                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Posted</option>
                                        <option value="salary" <?php echo $sort_by === 'salary' ? 'selected' : ''; ?>>Salary</option>
                                        <option value="deadline" <?php echo $sort_by === 'deadline' ? 'selected' : ''; ?>>Deadline</option>
                                        <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title</option>
                                    </select>
                                    <select id="sort_order" name="sort_order" 
                                            class="px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-primary focus:border-primary border-l-0">
                                        <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>↓</option>
                                        <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>↑</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <a href="find-jobs.php" class="btn btn-outline mr-2">
                                <i class="fas fa-undo mr-2"></i> Reset
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search mr-2"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Jobs List -->
            <?php if (empty($jobs)): ?>
                <div class="content-card" data-aos="fade-up">
                    <div class="card-body text-center py-12">
                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-xl font-semibold mb-2">No jobs found</h3>
                        <p class="text-gray-500 mb-6">Try adjusting your search filters or check back later for new opportunities.</p>
                        <a href="find-jobs.php" class="btn btn-primary">
                            <i class="fas fa-redo mr-2"></i> Clear Filters
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-6" data-aos="fade-up">
                    <?php foreach ($jobs as $job): ?>
                        <div class="content-card job-card">
                            <div class="card-body">
                                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                    <h3 class="text-xl font-semibold mb-2 md:mb-0">
                                        <a href="view-job.php?id=<?php echo $job['id']; ?>" class="job-title">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </a>
                                    </h3>
                                    <div class="flex items-center">
                                        <span class="text-2xl font-bold text-primary mr-4">$<?php echo number_format($job['salary'], 2); ?></span>
                                        <a href="place-bid.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-gavel mr-2"></i> Bid Now
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
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
                                        <i class="fas fa-file-alt text-primary mr-2"></i>
                                        <div>
                                            <p class="text-sm text-gray-500">Pages</p>
                                            <p class="font-medium"><?php echo htmlspecialchars($job['pages'] ?? 'Not specified'); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="line-clamp-3"><?php echo htmlspecialchars(substr($job['description'], 0, 200)) . (strlen($job['description']) > 200 ? '...' : ''); ?></p>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <?php if (!empty($job['category'])): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($job['category']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>
                                    
                                    <a href="view-job.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary flex items-center">
                                        View Details <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.job-card {
    transition: all 0.3s ease;
}

.job-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
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
        
        // Toggle filter section
        const toggleFilters = document.getElementById('toggle-filters');
        const filterSection = document.getElementById('filter-section');
        
        if (toggleFilters && filterSection) {
            // Show filter section if there are active filters
            <?php if (!empty($search) || $min_salary > 0 || $max_salary > 0 || $sort_by !== 'created_at' || $sort_order !== 'DESC'): ?>
                filterSection.style.display = 'block';
            <?php endif; ?>
            
            toggleFilters.addEventListener('click', function() {
                if (filterSection.style.display === 'none') {
                    filterSection.style.display = 'block';
                    filterSection.scrollIntoView({ behavior: 'smooth' });
                } else {
                    filterSection.style.display = 'none';
                }
            });
        }
    });
</script>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 