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

// Support user ID (Customer Support admin)
$support_user_id = 4; // ID of the support user we just created

// Get conversations (grouped messages)
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT 
        m.id as message_id,
        m.sender_id,
        m.receiver_id,
        m.message,
        m.created_at as last_message_date,
        s.first_name as sender_name,
        s.id as sender_id,
        r.first_name as receiver_name,
        r.id as receiver_id,
        j.id as job_id,
        j.title as job_title
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    JOIN users r ON m.receiver_id = r.id
    LEFT JOIN jobs j ON (
        (m.sender_id = j.admin_id AND m.receiver_id = j.freelancer_id) OR
        (m.receiver_id = j.admin_id AND m.sender_id = j.freelancer_id)
    )
    WHERE 
        m.id IN (
            SELECT MAX(id) 
            FROM messages 
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY 
                CASE 
                    WHEN sender_id < receiver_id THEN CONCAT(sender_id, '-', receiver_id)
                    ELSE CONCAT(receiver_id, '-', sender_id)
                END
        )
    ORDER BY m.created_at DESC
");

$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Messages';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Messages</h1>
            <a href="chat.php?user=<?php echo $support_user_id; ?>" class="btn btn-primary">
                <i class="fas fa-headset mr-2"></i> Contact Support
            </a>
        </div>

        <?php if (empty($conversations)): ?>
            <div class="bg-white shadow rounded-lg p-6 text-center">
                <p class="text-gray-600">No messages yet</p>
                <div class="mt-4 flex justify-center gap-4">
                    <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
                    <a href="chat.php?user=<?php echo $support_user_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-headset mr-2"></i> Contact Support
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="divide-y divide-gray-200">
                    <?php foreach ($conversations as $conv): ?>
                        <?php 
                        // Determine the other user in the conversation
                        $other_user_id = ($conv['sender_id'] == $user_id) ? $conv['receiver_id'] : $conv['sender_id'];
                        $other_user_name = ($conv['sender_id'] == $user_id) ? $conv['receiver_name'] : $conv['sender_name'];
                        
                        // Create a unique conversation identifier
                        $conversation_id = ($conv['sender_id'] < $conv['receiver_id']) 
                            ? $conv['sender_id'] . '-' . $conv['receiver_id'] 
                            : $conv['receiver_id'] . '-' . $conv['sender_id'];
                            
                        // Check if this is a support conversation
                        $is_support = ($other_user_id == $support_user_id);
                        ?>
                        <a href="chat.php?user=<?php echo $other_user_id; ?>" 
                           class="block p-6 hover:bg-gray-50 transition-colors">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <?php echo htmlspecialchars($other_user_name); ?>
                                        <?php if ($is_support): ?>
                                            <span class="ml-2 bg-primary text-white text-xs px-2 py-1 rounded-full">Support</span>
                                        <?php endif; ?>
                                    </h3>
                                    <?php if (!empty($conv['job_title']) && !$is_support): ?>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Re: <?php echo htmlspecialchars($conv['job_title']); ?>
                                    </p>
                                    <?php endif; ?>
                                    <p class="text-sm text-gray-500 mt-2">
                                        <?php echo htmlspecialchars($conv['message']); ?>
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