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
                                        <div class="action-buttons">
                                            <a href="view-job.php?id=<?php echo $job['id']; ?>" class="action-button view-button">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirmDelete(event)">
                                                <input type="hidden" name="action" value="delete_job">
                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                <button type="submit" class="action-button delete-button">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </button>
                                            </form>
                                            <div class="relative inline-block text-left" x-data="{ open: false }">
                                                <button type="button" @click="open = !open" class="action-button status-button">
                                                    <i class="fas fa-exchange-alt mr-1"></i> Change Status
                                                </button>
                                                <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-56 dropdown-menu">
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
                                                                <button type="submit" class="dropdown-item block w-full text-left">
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
$custom_js = [
    'assets/js/main.js',
    'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js'
];

// Add custom CSS for button styling
$additional_styles = '
<style>
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        align-items: center;
    }
    
    .action-button {
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        transition: all 0.2s ease;
    }
    
    .view-button {
        background-color: rgba(59, 130, 246, 0.1);
        color: var(--primary-color);
    }
    
    .view-button:hover {
        background-color: rgba(59, 130, 246, 0.2);
    }
    
    .delete-button {
        background-color: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
    
    .delete-button:hover {
        background-color: rgba(239, 68, 68, 0.2);
    }
    
    .status-button {
        background-color: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .status-button:hover {
        background-color: rgba(16, 185, 129, 0.2);
    }
    
    .dropdown-menu {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border-radius: 0.375rem;
        z-index: 50;
    }
    
    .dropdown-item {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }
    
    .dropdown-item:hover {
        background-color: rgba(243, 244, 246, 1);
    }
    
    @media (max-width: 640px) {
        .action-buttons {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .action-button {
            width: 100%;
            justify-content: center;
        }
    }
</style>
';

require_once 'views/layout.php';
?>

<script>
function confirmDelete(event) {
    if (!confirm('Are you sure you want to delete this job?')) {
        event.preventDefault();
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    // Add loading state to forms when submitted
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
                button.disabled = true;
            }
        });
    });

    // Add hover effects to buttons
    const buttons = document.querySelectorAll('.action-button');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Add success message animation
    const successMessage = document.querySelector('.bg-green-100');
    if (successMessage) {
        successMessage.style.animation = 'fadeIn 0.5s ease-in-out';
        setTimeout(() => {
            successMessage.style.animation = 'fadeOut 0.5s ease-in-out forwards';
        }, 3000);
    }
});
</script>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeOut {
    from { opacity: 1; transform: translateY(0); }
    to { opacity: 0; transform: translateY(-10px); }
}
</style> 