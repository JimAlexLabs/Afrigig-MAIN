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

// Get user's jobs
if ($user['is_admin']) {
    $stmt = $conn->prepare("
        SELECT j.*, COUNT(b.id) as bid_count
        FROM jobs j
        LEFT JOIN bids b ON j.id = b.job_id
        WHERE j.admin_id = ?
        GROUP BY j.id
        ORDER BY j.created_at DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
} else {
    $stmt = $conn->prepare("
        SELECT j.*, u.first_name, u.last_name
        FROM jobs j
        JOIN users u ON j.admin_id = u.id
        WHERE j.freelancer_id = ? OR j.id IN (
            SELECT job_id FROM bids WHERE freelancer_id = ?
        )
        ORDER BY j.created_at DESC
    ");
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
}
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = $user['is_admin'] ? 'My Posted Jobs' : 'My Jobs';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold"><?php echo $page_title; ?></h1>
            <?php if ($user['is_admin']): ?>
                <a href="post-job.php" class="btn btn-primary">Post New Job</a>
            <?php endif; ?>
        </div>

        <?php if (empty($jobs)): ?>
            <div class="bg-white shadow rounded-lg p-6 text-center">
                <p class="text-gray-600">
                    <?php echo $user['is_admin'] ? 'You haven\'t posted any jobs yet' : 'You haven\'t applied to any jobs yet'; ?>
                </p>
                <?php if ($user['is_admin']): ?>
                    <a href="post-job.php" class="btn btn-primary mt-4">Post Your First Job</a>
                <?php else: ?>
                    <a href="find-jobs.php" class="btn btn-primary mt-4">Find Jobs</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <?php if ($user['is_admin']): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bids</th>
                            <?php else: ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posted By</th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posted</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <a href="view-job.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded-full text-xs 
                                        <?php echo $job['status'] === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($job['status'])); ?>
                                    </span>
                                </td>
                                <?php if ($user['is_admin']): ?>
                                    <td class="px-6 py-4"><?php echo $job['bid_count']; ?></td>
                                <?php else: ?>
                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($user['is_admin']): ?>
                                        <a href="edit-job.php?id=<?php echo $job['id']; ?>" 
                                           class="text-primary hover:text-secondary mr-4">Edit</a>
                                        <?php if ($job['status'] === 'open'): ?>
                                            <a href="view-bids.php?job_id=<?php echo $job['id']; ?>" 
                                               class="text-primary hover:text-secondary">View Bids</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($job['status'] === 'open'): ?>
                                            <a href="place-bid.php?job_id=<?php echo $job['id']; ?>" 
                                               class="text-primary hover:text-secondary">Place Bid</a>
                                        <?php endif; ?>
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

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 