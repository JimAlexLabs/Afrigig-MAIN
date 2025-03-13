<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = $_GET['conversation'] ?? 0;
$errors = [];
$success = false;

// Validate conversation access
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT 
        c.*,
        j.title as job_title,
        CASE 
            WHEN c.sender_id = ? THEN r.first_name
            ELSE s.first_name
        END as other_user_name,
        CASE 
            WHEN c.sender_id = ? THEN r.id
            ELSE s.id
        END as other_user_id
    FROM conversations c
    JOIN jobs j ON c.job_id = j.id
    JOIN users s ON c.sender_id = s.id
    JOIN users r ON c.receiver_id = r.id
    WHERE c.id = ? AND (c.sender_id = ? OR c.receiver_id = ?)
");

$stmt->bind_param("iiiii", $user_id, $user_id, $conversation_id, $user_id, $user_id);
$stmt->execute();
$conversation = $stmt->get_result()->fetch_assoc();

if (!$conversation) {
    header('Location: messages.php');
    exit;
}

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = clean_input($_POST['message'] ?? '');
    
    if (empty($message)) {
        $errors[] = "Message cannot be empty";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO messages (conversation_id, sender_id, message) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $conversation_id, $user_id, $message);
        
        if ($stmt->execute()) {
            header("Location: chat.php?conversation=" . $conversation_id);
            exit;
        } else {
            $errors[] = "Failed to send message";
        }
    }
}

// Get messages
$stmt = $conn->prepare("
    SELECT 
        m.*,
        u.first_name,
        u.id as user_id
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Chat with ' . $conversation['other_user_name'];
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
                            <?php echo htmlspecialchars($conversation['other_user_name']); ?>
                        </h1>
                        <p class="text-sm text-gray-600">
                            Re: <?php echo htmlspecialchars($conversation['job_title']); ?>
                        </p>
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