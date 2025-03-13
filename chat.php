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

// Support user ID
$support_user_id = 4; // ID of the support user we created
$is_support_chat = ($other_user_id == $support_user_id);

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

// Try to find a job that connects these users (not needed for support chat)
$job = null;
if (!$is_support_chat) {
    $stmt = $conn->prepare("
        SELECT j.* FROM jobs j
        WHERE (j.admin_id = ? AND j.freelancer_id = ?) 
           OR (j.admin_id = ? AND j.freelancer_id = ?)
        LIMIT 1
    ");
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
}

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

$page_title = $is_support_chat ? 'Customer Support' : 'Chat with ' . $other_user['first_name'];
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <!-- Chat Header -->
            <div class="<?php echo $is_support_chat ? 'bg-primary text-white' : 'bg-gray-50'; ?> px-6 py-4 border-b">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-xl font-semibold flex items-center">
                            <?php echo htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']); ?>
                            <?php if ($is_support_chat): ?>
                                <span class="ml-2 bg-white text-primary text-xs px-2 py-1 rounded-full">Support</span>
                            <?php endif; ?>
                        </h1>
                        <?php if ($job): ?>
                        <p class="text-sm <?php echo $is_support_chat ? 'text-white opacity-80' : 'text-gray-600'; ?>">
                            Re: <?php echo htmlspecialchars($job['title']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <a href="messages.php" class="<?php echo $is_support_chat ? 'text-white' : 'text-gray-600 hover:text-gray-900'; ?>">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                </div>
            </div>

            <?php if ($is_support_chat): ?>
            <!-- Support Info Banner -->
            <div class="bg-blue-50 p-4 border-b border-blue-100">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    You are chatting with Afrigig Customer Support. We typically respond within 24 hours.
                </p>
            </div>
            <?php endif; ?>

            <!-- Chat Messages -->
            <div class="p-6 h-[500px] overflow-y-auto flex flex-col space-y-4" id="messages">
                <?php if (empty($messages)): ?>
                    <div class="text-center text-gray-500 my-8">
                        <?php if ($is_support_chat): ?>
                            <p>Welcome to Afrigig Customer Support! How can we help you today?</p>
                        <?php else: ?>
                            <p>No messages yet. Start the conversation!</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="flex <?php echo $msg['user_id'] === $user_id ? 'justify-end' : 'justify-start'; ?>">
                            <div class="<?php echo $msg['user_id'] === $user_id ? 'bg-primary text-white' : ($is_support_chat && $msg['user_id'] === $support_user_id ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-900'); ?> rounded-lg px-4 py-2 max-w-[70%]">
                                <p class="text-sm"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                <span class="text-xs <?php echo $msg['user_id'] === $user_id ? 'text-primary-100' : ($is_support_chat && $msg['user_id'] === $support_user_id ? 'text-blue-100' : 'text-gray-500'); ?> block mt-1">
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

        <?php if ($is_support_chat): ?>
        <!-- Support FAQ -->
        <div class="mt-8 bg-white shadow rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Frequently Asked Questions</h2>
            <div class="space-y-4">
                <div>
                    <h3 class="font-medium text-gray-900">How do I place a bid on a job?</h3>
                    <p class="text-gray-600 mt-1">Click on a job listing, then click the "Place Bid" button. You'll need to complete the skill assessment before your bid is submitted.</p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">How do I get paid for completed work?</h3>
                    <p class="text-gray-600 mt-1">Once your work is approved, the payment will be released to your account balance. You can withdraw funds from your profile page.</p>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">What if I have a dispute with a client?</h3>
                    <p class="text-gray-600 mt-1">Contact our support team through this chat, and we'll help mediate the situation to find a fair resolution.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
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