<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;

// Handle job status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $job_id = (int)($_POST['job_id'] ?? 0);
    $status = clean_input($_POST['status'] ?? '');
    
    if ($job_id > 0 && in_array($status, ['open', 'assigned', 'in_progress', 'completed', 'cancelled'])) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE id = ? AND admin_id = ?");
            $stmt->bind_param("sii", $status, $job_id, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = true;
            } else {
                $errors[] = "Failed to update job status";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again.";
        }
    } else {
        $errors[] = "Invalid job ID or status";
    }
}

// Handle job deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_job') {
    $job_id = (int)($_POST['job_id'] ?? 0);
    
    if ($job_id > 0) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ? AND admin_id = ?");
            $stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = true;
            } else {
                $errors[] = "Failed to delete job";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again.";
        }
    } else {
        $errors[] = "Invalid job ID";
    }
}

// Get all jobs posted by the admin
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT j.*, 
           IFNULL(u.first_name, '') as freelancer_name,
           IFNULL(u.last_name, '') as freelancer_last_name,
           (SELECT COUNT(*) FROM bids WHERE job_id = j.id) as bid_count
    FROM jobs j
    LEFT JOIN users u ON j.freelancer_id = u.id
    WHERE j.admin_id = ?
    ORDER BY j.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Manage Jobs';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Manage Jobs</h1>
            <a href="post-job.php" class="btn btn-primary">
                <i class="fas fa-plus-circle mr-2"></i> Post New Job
            </a>
        </div>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p>Operation completed successfully!</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (empty($jobs)): ?>
            <div class="bg-white shadow rounded-lg p-6 text-center">
                <i class="fas fa-briefcase text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600 mb-4">You haven't posted any jobs yet</p>
                <a href="post-job.php" class="btn btn-primary">Post Your First Job</a>
            </div>
        <?php else: ?>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Job Title
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Salary
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Deadline
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Bids
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Assigned To
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch ($job['status']) {
                                                case 'open': echo 'bg-green-100 text-green-800'; break;
                                                case 'assigned': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'in_progress': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'completed': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst(htmlspecialchars($job['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">$<?php echo number_format($job['salary'], 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M j, Y', strtotime($job['deadline'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $job['bid_count']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($job['freelancer_id']): ?>
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($job['freelancer_name'] . ' ' . $job['freelancer_last_name']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="view-job.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary">
                                                View
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this job?');">
                                                <input type="hidden" name="action" value="delete_job">
                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    Delete
                                                </button>
                                            </form>
                                            <div class="relative inline-block text-left" x-data="{ open: false }">
                                                <button type="button" @click="open = !open" class="text-primary hover:text-secondary">
                                                    Change Status
                                                </button>
                                                <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
                                                    <div class="py-1" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
                                                        <?php 
                                                        $statuses = ['open', 'assigned', 'in_progress', 'completed', 'cancelled'];
                                                        foreach ($statuses as $status): 
                                                            if ($status !== $job['status']):
                                                        ?>
                                                            <form method="POST" class="block">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $status; ?>">
                                                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem">
                                                                    <?php echo ucfirst($status); ?>
                                                                </button>
                                                            </form>
                                                        <?php 
                                                            endif;
                                                        endforeach; 
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 