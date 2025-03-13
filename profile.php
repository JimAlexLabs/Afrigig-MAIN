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
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, created_at, is_admin, 
           phone, profile_image, bio, location, balance, is_verified
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user statistics
if ($user['is_admin']) {
    // Admin statistics
    $jobs_posted = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE admin_id = {$user_id}")->fetch_assoc()['count'];
    $total_bids = $conn->query("SELECT COUNT(*) as count FROM bids b JOIN jobs j ON b.job_id = j.id WHERE j.admin_id = {$user_id}")->fetch_assoc()['count'];
    $active_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE admin_id = {$user_id} AND status = 'open'")->fetch_assoc()['count'];
} else {
    // Freelancer statistics
    $jobs_bid = $conn->query("SELECT COUNT(*) as count FROM bids WHERE freelancer_id = {$user_id}")->fetch_assoc()['count'];
    $jobs_won = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE freelancer_id = {$user_id} AND status = 'awarded'")->fetch_assoc()['count'];
    $active_jobs = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE freelancer_id = {$user_id} AND status = 'in_progress'")->fetch_assoc()['count'];
}

$page_title = 'Profile';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <!-- Profile Header -->
            <div class="bg-primary px-6 py-8 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="mr-4">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="w-16 h-16 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-full bg-primary-dark flex items-center justify-center text-2xl font-bold">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                            <p class="text-primary-100"><?php echo $user['is_admin'] ? 'Administrator' : 'Freelancer'; ?></p>
                        </div>
                    </div>
                    <a href="settings.php" class="btn btn-outline-light">Edit Profile</a>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="p-6">
                <!-- Basic Information -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4">Basic Information</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-600">Email</p>
                            <p class="font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Phone</p>
                            <p class="font-medium"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Location</p>
                            <p class="font-medium"><?php echo htmlspecialchars($user['location'] ?? 'Not set'); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Account Balance</p>
                            <p class="font-medium">$<?php echo number_format($user['balance'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Verification Status</p>
                            <p class="font-medium"><?php echo $user['is_verified'] ? 'Verified' : 'Not Verified'; ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600">Member Since</p>
                            <p class="font-medium"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($user['bio'])): ?>
                <!-- Bio Section -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4">About Me</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold mb-4">Statistics</h2>
                    <div class="grid grid-cols-3 gap-4">
                        <?php if ($user['is_admin']): ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600">Jobs Posted</p>
                                <p class="text-2xl font-bold"><?php echo $jobs_posted; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600">Total Bids</p>
                                <p class="text-2xl font-bold"><?php echo $total_bids; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600">Active Jobs</p>
                                <p class="text-2xl font-bold"><?php echo $active_jobs; ?></p>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600">Jobs Bid</p>
                                <p class="text-2xl font-bold"><?php echo $jobs_bid; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600">Jobs Won</p>
                                <p class="text-2xl font-bold"><?php echo $jobs_won; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600">Active Jobs</p>
                                <p class="text-2xl font-bold"><?php echo $active_jobs; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
                    <div class="flex gap-4">
                        <?php if ($user['is_admin']): ?>
                            <a href="post-job.php" class="btn btn-primary">Post New Job</a>
                            <a href="manage-jobs.php" class="btn btn-secondary">Manage Jobs</a>
                        <?php else: ?>
                            <a href="jobs.php" class="btn btn-primary">Find Jobs</a>
                            <a href="my-jobs.php" class="btn btn-secondary">My Jobs</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 