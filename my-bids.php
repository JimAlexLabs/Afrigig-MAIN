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

// Get user's bids with job details
$stmt = $conn->prepare("
    SELECT b.*, j.title as job_title, j.status as job_status, 
           u.first_name, u.last_name
    FROM bids b
    JOIN jobs j ON b.job_id = j.id
    JOIN users u ON j.admin_id = u.id
    WHERE b.freelancer_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'My Bids';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">My Bids</h1>

        <?php if (empty($bids)): ?>
            <div class="bg-white shadow rounded-lg p-6 text-center">
                <p class="text-gray-600">You haven't placed any bids yet</p>
                <a href="find-jobs.php" class="btn btn-primary mt-4">Find Jobs</a>
            </div>
        <?php else: ?>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bid Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($bids as $bid): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <a href="view-job.php?id=<?php echo $bid['job_id']; ?>" class="text-primary hover:text-secondary">
                                        <?php echo htmlspecialchars($bid['job_title']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($bid['first_name'] . ' ' . $bid['last_name']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    $<?php echo number_format($bid['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded-full text-xs 
                                        <?php 
                                        $statusClass = match($bid['status']) {
                                            'accepted' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            default => 'bg-yellow-100 text-yellow-800'
                                        };
                                        echo $statusClass;
                                        ?>">
                                        <?php echo ucfirst(htmlspecialchars($bid['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo date('M j, Y', strtotime($bid['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($bid['status'] === 'pending' && $bid['job_status'] === 'open'): ?>
                                        <a href="edit-bid.php?id=<?php echo $bid['id']; ?>" 
                                           class="text-primary hover:text-secondary mr-4">Edit</a>
                                        <a href="withdraw-bid.php?id=<?php echo $bid['id']; ?>" 
                                           class="text-red-600 hover:text-red-800"
                                           onclick="return confirm('Are you sure you want to withdraw this bid?')">
                                            Withdraw
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

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 