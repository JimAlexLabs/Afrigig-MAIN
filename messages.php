<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

// Get conversations
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT DISTINCT 
        c.id as conversation_id,
        c.job_id,
        c.created_at,
        j.title as job_title,
        CASE 
            WHEN c.sender_id = ? THEN r.first_name
            ELSE s.first_name
        END as other_user_name,
        CASE 
            WHEN c.sender_id = ? THEN r.id
            ELSE s.id
        END as other_user_id,
        (
            SELECT message 
            FROM messages m2 
            WHERE m2.conversation_id = c.id 
            ORDER BY m2.created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT created_at 
            FROM messages m2 
            WHERE m2.conversation_id = c.id 
            ORDER BY m2.created_at DESC 
            LIMIT 1
        ) as last_message_date
    FROM conversations c
    JOIN jobs j ON c.job_id = j.id
    JOIN users s ON c.sender_id = s.id
    JOIN users r ON c.receiver_id = r.id
    WHERE c.sender_id = ? OR c.receiver_id = ?
    ORDER BY last_message_date DESC
");

$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Messages';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">Messages</h1>

        <?php if (empty($conversations)): ?>
            <div class="bg-white shadow rounded-lg p-6 text-center">
                <p class="text-gray-600">No messages yet</p>
                <a href="jobs.php" class="btn btn-primary mt-4">Browse Jobs</a>
            </div>
        <?php else: ?>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="divide-y divide-gray-200">
                    <?php foreach ($conversations as $conv): ?>
                        <a href="chat.php?conversation=<?php echo $conv['conversation_id']; ?>" 
                           class="block p-6 hover:bg-gray-50 transition-colors">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($conv['other_user_name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Re: <?php echo htmlspecialchars($conv['job_title']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500 mt-2">
                                        <?php echo htmlspecialchars($conv['last_message']); ?>
                                    </p>
                                </div>
                                <span class="text-xs text-gray-500">
                                    <?php echo date('M j, Y', strtotime($conv['last_message_date'])); ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 