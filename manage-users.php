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

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $status = clean_input($_POST['status'] ?? '');
    
    if ($user_id > 0 && in_array($status, ['active', 'suspended', 'banned'])) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $user_id);
            
            if ($stmt->execute()) {
                $success = true;
            } else {
                $errors[] = "Failed to update user status";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again.";
        }
    } else {
        $errors[] = "Invalid user ID or status";
    }
}

// Handle user deletion (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id > 0) {
        try {
            $conn = getDbConnection();
            // Instead of actually deleting, we'll set a deleted flag
            $stmt = $conn->prepare("UPDATE users SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success = true;
            } else {
                $errors[] = "Failed to delete user";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again.";
        }
    } else {
        $errors[] = "Invalid user ID";
    }
}

// Get all users except current admin
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM jobs WHERE freelancer_id = u.id) as assigned_jobs,
           (SELECT COUNT(*) FROM bids WHERE user_id = u.id) as bid_count
    FROM users u
    WHERE u.id != ?
    ORDER BY u.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Add default status if not set
foreach ($users as &$user) {
    if (!isset($user['status'])) {
        $user['status'] = 'active';
    }
}
unset($user); // Break the reference

$page_title = 'Manage Users';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Manage Users</h1>
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
        
        <?php if (empty($users)): ?>
            <div class="bg-white shadow rounded-lg p-6 text-center">
                <i class="fas fa-users text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600 mb-4">No users found</p>
            </div>
        <?php else: ?>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Role
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Jobs/Bids
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Joined
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <span class="text-gray-500 font-medium">
                                                        <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php if (isset($user['username']) && !empty($user['username'])): ?>
                                                        @<?php echo htmlspecialchars($user['username']); ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">No username</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo $user['is_admin'] ? 'Admin' : 'Freelancer'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            $status = $user['status'] ?? 'active';
                                            switch ($status) {
                                                case 'active': echo 'bg-green-100 text-green-800'; break;
                                                case 'suspended': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'banned': echo 'bg-red-100 text-red-800'; break;
                                                case 'deleted': echo 'bg-gray-100 text-gray-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst(htmlspecialchars($status)); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if (!$user['is_admin']): ?>
                                            <div>Jobs: <?php echo $user['assigned_jobs']; ?></div>
                                            <div>Bids: <?php echo $user['bid_count']; ?></div>
                                        <?php else: ?>
                                            <div>Admin User</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="view-profile.php?id=<?php echo $user['id']; ?>" class="text-primary hover:text-secondary">
                                                View
                                            </a>
                                            <?php if (($user['status'] ?? 'active') !== 'deleted'): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
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
                                                            $statuses = ['active', 'suspended', 'banned'];
                                                            $currentStatus = $user['status'] ?? 'active';
                                                            foreach ($statuses as $status): 
                                                                if ($status !== $currentStatus):
                                                            ?>
                                                                <form method="POST" class="block">
                                                                    <input type="hidden" name="action" value="update_status">
                                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
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
                                            <?php endif; ?>
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