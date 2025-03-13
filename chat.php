<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$other_user_id = $_GET['user'] ?? 0;
$errors = [];
$success = false;

// Validate user access
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $other_user_id);
$stmt->execute();
$other_user = $stmt->get_result()->fetch_assoc();

if (!$other_user) {
    header('Location: messages.php');
    exit;
}

// Try to find a job that connects these users
$stmt = $conn->prepare("
    SELECT j.* FROM jobs j
    WHERE (j.admin_id = ? AND j.freelancer_id = ?) 
       OR (j.admin_id = ? AND j.freelancer_id = ?)
    LIMIT 1
");
$stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = clean_input($_POST['message'] ?? '');
    
    if (empty($message)) {
        $errors[] = "Message cannot be empty";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $user_id, $other_user_id, $message);
        
        if ($stmt->execute()) {
            header("Location: chat.php?user=" . $other_user_id);
            exit;
        } else {
            $errors[] = "Failed to send message";
        }
    }
}

// Get messages between these users
$stmt = $conn->prepare("
    SELECT 
        m.*,
        u.first_name,
        u.id as user_id
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = ? AND m.receiver_id = ?) 
       OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Mark messages as read
$stmt = $conn->prepare("
    UPDATE messages 
    SET is_read = 1 
    WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
");
$stmt->bind_param("ii", $other_user_id, $user_id);
$stmt->execute();

$page_title = 'Chat with ' . $other_user['first_name'];
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <!-- Chat Header -->
            <div class="bg-gray-50 px-6 py-4 border-b">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-xl font-semibold">
                            <?php echo htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']); ?>
                        </h1>
                        <?php if ($job): ?>
                        <p class="text-sm text-gray-600">
                            Re: <?php echo htmlspecialchars($job['title']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <a href="messages.php" class="text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="p-6 h-[500px] overflow-y-auto flex flex-col space-y-4" id="messages">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-gray-500 my-8">
                        <p>No messages yet. Start the conversation!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="flex <?php echo $msg['user_id'] === $user_id ? 'justify-end' : 'justify-start'; ?>">
                            <div class="<?php echo $msg['user_id'] === $user_id ? 'bg-primary text-white' : 'bg-gray-100 text-gray-900'; ?> rounded-lg px-4 py-2 max-w-[70%]">
                                <p class="text-sm"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                <span class="text-xs <?php echo $msg['user_id'] === $user_id ? 'text-primary-100' : 'text-gray-500'; ?> block mt-1">
                                    <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input -->
            <div class="border-t p-4">
                <form method="POST" class="flex gap-4">
                    <textarea name="message" 
                              class="flex-1 rounded-lg border-gray-300 focus:border-primary focus:ring-primary resize-none"
                              rows="1" 
                              placeholder="Type your message..."
                              required></textarea>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Scroll to bottom of messages
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.getElementById('messages');
        messages.scrollTop = messages.scrollHeight;
    });
</script>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 