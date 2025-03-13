<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Page title
$page_title = 'Jobs';

// Get filters from query parameters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$category = isset($_GET['category']) ? clean_input($_GET['category']) : '';
$min_salary = isset($_GET['min_salary']) ? (float)$_GET['min_salary'] : 0;
$max_salary = isset($_GET['max_salary']) ? (float)$_GET['max_salary'] : PHP_FLOAT_MAX;
$status = isset($_GET['status']) ? clean_input($_GET['status']) : 'open';
$sort = isset($_GET['sort']) ? clean_input($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

// Build the query
$conn = getDbConnection();
$where_clauses = ['1=1']; // Always true condition to start
$params = [];
$types = '';

if ($search) {
    $where_clauses[] = '(j.title LIKE ? OR j.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

if ($category) {
    $where_clauses[] = 'j.category = ?';
    $params[] = $category;
    $types .= 's';
}

if ($min_salary > 0) {
    $where_clauses[] = 'j.salary >= ?';
    $params[] = $min_salary;
    $types .= 'd';
}

if ($max_salary < PHP_FLOAT_MAX) {
    $where_clauses[] = 'j.salary <= ?';
    $params[] = $max_salary;
    $types .= 'd';
}

if ($status) {
    $where_clauses[] = 'j.status = ?';
    $params[] = $status;
    $types .= 's';
}

// Calculate offset for pagination
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count_sql = "
    SELECT COUNT(DISTINCT j.id) as total
    FROM jobs j
    WHERE " . implode(' AND ', $where_clauses);

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_jobs = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_jobs / $per_page);

// Get jobs with sorting
$order_by = match($sort) {
    'salary_high' => 'j.salary DESC',
    'salary_low' => 'j.salary ASC',
    'deadline' => 'j.deadline ASC',
    'oldest' => 'j.created_at ASC',
    default => 'j.created_at DESC'
};

$sql = "
    SELECT j.*, 
           COUNT(DISTINCT b.id) as bid_count,
           c.name as category_name,
           u.first_name,
           u.last_name,
           u.profile_image
    FROM jobs j
    LEFT JOIN bids b ON j.id = b.job_id
    LEFT JOIN categories c ON j.category_id = c.id
    LEFT JOIN users u ON j.client_id = u.id
    WHERE " . implode(' AND ', $where_clauses) . "
    GROUP BY j.id
    ORDER BY $order_by
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types . 'ii', ...[...$params, $per_page, $offset]);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Additional styles
$additional_styles = '
<style>
    .filters {
        background: var(--surface-color);
        padding: 1.5rem;
        border-radius: 0.5rem;
        margin-bottom: 2rem;
    }
    
    .job-grid {
        display: grid;
        gap: 1.5rem;
    }
    
    .job-card {
        background: var(--surface-color);
        border-radius: 0.5rem;
        padding: 1.5rem;
        transition: transform 0.2s ease;
    }
    
    .job-card:hover {
        transform: translateY(-2px);
    }
    
    .job-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    
    .client-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .client-avatar {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .job-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1rem;
        color: var(--text-secondary);
        font-size: 0.875rem;
    }
    
    .job-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }
    
    .page-link {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        background: var(--surface-color);
        color: var(--text-primary);
        text-decoration: none;
    }
    
    .page-link.active {
        background: var(--primary-color);
        color: white;
    }
</style>
';

// Start output buffering
ob_start();
?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">Jobs</h1>
    <?php if (is_admin()): ?>
        <a href="/jobs/create.php" class="btn btn-primary">Post New Job</a>
    <?php endif; ?>
</div>

<!-- Filters -->
<form class="filters" method="GET">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="form-group">
            <label for="search" class="form-label">Search</label>
            <input type="text" id="search" name="search" class="form-input" 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search jobs...">
        </div>
        
        <div class="form-group">
            <label for="category" class="form-label">Category</label>
            <select id="category" name="category" class="form-input">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" 
                            <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="sort" class="form-label">Sort By</label>
            <select id="sort" name="sort" class="form-input">
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                <option value="salary_high" <?php echo $sort === 'salary_high' ? 'selected' : ''; ?>>Highest Salary</option>
                <option value="salary_low" <?php echo $sort === 'salary_low' ? 'selected' : ''; ?>>Lowest Salary</option>
                <option value="deadline" <?php echo $sort === 'deadline' ? 'selected' : ''; ?>>Deadline</option>
            </select>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div class="form-group">
            <label for="min_salary" class="form-label">Min Salary</label>
            <input type="number" id="min_salary" name="min_salary" class="form-input" 
                   value="<?php echo $min_salary ?: ''; ?>" 
                   min="0" step="100">
        </div>
        
        <div class="form-group">
            <label for="max_salary" class="form-label">Max Salary</label>
            <input type="number" id="max_salary" name="max_salary" class="form-input" 
                   value="<?php echo $max_salary < PHP_FLOAT_MAX ? $max_salary : ''; ?>" 
                   min="0" step="100">
        </div>
    </div>
    
    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">Apply Filters</button>
    </div>
</form>

<!-- Jobs list -->
<div class="job-grid">
    <?php if (empty($jobs)): ?>
        <div class="text-center text-secondary py-8">
            No jobs found matching your criteria.
        </div>
    <?php else: ?>
        <?php foreach ($jobs as $job): ?>
            <div class="job-card">
                <div class="job-header">
                    <div class="client-info">
                        <img src="<?php echo $job['profile_image'] ?? '/assets/images/default-avatar.png'; ?>" 
                             alt="<?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>"
                             class="client-avatar">
                        <div>
                            <h3 class="font-bold"><?php echo htmlspecialchars($job['title']); ?></h3>
                            <div class="text-secondary text-sm">
                                Posted by <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="badge badge-<?php echo get_status_color($job['status']); ?>">
                        <?php echo ucfirst($job['status']); ?>
                    </div>
                </div>
                
                <div class="job-meta">
                    <span>
                        <svg class="inline-block w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <?php echo htmlspecialchars($job['category_name']); ?>
                    </span>
                    
                    <span>
                        <svg class="inline-block w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?php echo format_money($job['salary']); ?>
                    </span>
                    
                    <span>
                        <svg class="inline-block w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <?php echo time_remaining($job['deadline']); ?> left
                    </span>
                    
                    <span>
                        <svg class="inline-block w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <?php echo $job['bid_count']; ?> bids
                    </span>
                </div>
                
                <p class="text-secondary mb-4">
                    <?php echo nl2br(htmlspecialchars(truncate_text($job['description'], 200))); ?>
                </p>
                
                <div class="job-actions">
                    <div class="flex gap-2">
                        <?php if ($job['attachments']): ?>
                            <span class="text-secondary text-sm">
                                <svg class="inline-block w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                <?php echo count(json_decode($job['attachments'])); ?> attachments
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex gap-2">
                        <a href="/jobs/view.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">View Details</a>
                        <?php if (is_admin()): ?>
                            <a href="/jobs/edit.php?id=<?php echo $job['id']; ?>" class="btn btn-secondary">Edit</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
               class="page-link">Previous</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
               class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
               class="page-link">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout template
require_once __DIR__ . '/../views/partials/layout.php'; 