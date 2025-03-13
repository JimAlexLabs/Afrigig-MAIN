<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Get all jobs with their bid counts
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT j.*, COUNT(DISTINCT b.id) as bid_count, u.first_name, u.last_name 
    FROM jobs j 
    LEFT JOIN bids b ON j.id = b.job_id 
    LEFT JOIN users u ON j.client_id = u.id
    WHERE j.status = 'open' 
    GROUP BY j.id 
    ORDER BY j.created_at DESC
");
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Available Jobs';

ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Available Jobs</h1>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="jobs/create.php" class="btn btn-primary">Post a Job</a>
        <?php endif; ?>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <form action="jobs.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" 
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                       placeholder="Search jobs...">
            </div>
            
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="category" name="category" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">All Categories</option>
                    <option value="writing">Writing</option>
                    <option value="design">Design</option>
                    <option value="development">Development</option>
                </select>
            </div>
            
            <div>
                <label for="budget" class="block text-sm font-medium text-gray-700 mb-1">Budget</label>
                <select id="budget" name="budget" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    <option value="">Any Budget</option>
                    <option value="0-100">$0 - $100</option>
                    <option value="100-500">$100 - $500</option>
                    <option value="500+">$500+</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="w-full btn btn-primary">
                    Filter Jobs
                </button>
            </div>
        </form>
    </div>
    
    <!-- Jobs List -->
    <?php if (empty($jobs)): ?>
        <div class="text-center py-8">
            <h2 class="text-2xl font-semibold text-gray-600">No jobs found</h2>
            <p class="text-gray-500 mt-2">Check back later for new opportunities</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-6">
            <?php foreach ($jobs as $job): ?>
                <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-xl font-semibold mb-2">
                                <a href="jobs/view.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </a>
                            </h2>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars(substr($job['description'], 0, 200)) . '...'; ?></p>
                            
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="px-3 py-1 bg-gray-100 rounded-full text-sm">
                                    <?php echo format_money($job['salary']); ?>
                                </span>
                                <span class="px-3 py-1 bg-gray-100 rounded-full text-sm">
                                    <?php echo $job['bid_count']; ?> bids
                                </span>
                                <span class="px-3 py-1 bg-gray-100 rounded-full text-sm">
                                    Posted <?php echo time_ago($job['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <p class="text-sm text-gray-600">
                                by <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                Deadline: <?php echo time_remaining($job['deadline']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="mt-4 flex justify-end">
                            <a href="jobs/bid.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                Place Bid
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 