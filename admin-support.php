<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$support_user_id = 4; // ID of the support user
$errors = [];
$success = false;

$conn = getDbConnection();

// Handle responding as support
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond') {
    $user_id = clean_input($_POST['user_id'] ?? 0);
    $message = clean_input($_POST['message'] ?? '');
    
    if (empty($message)) {
        $errors[] = "Message cannot be empty";
    } else {
        // Send message as support user
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $support_user_id, $user_id, $message);
        
        if ($stmt->execute()) {
            $success = true;
            // Redirect to avoid form resubmission
            header("Location: admin-support.php?user=" . $user_id . "&success=1");
            exit;
        } else {
            $errors[] = "Failed to send message";
        }
    }
}

// Get all users who have contacted support
$stmt = $conn->prepare("
    SELECT DISTINCT 
        u.id, 
        u.first_name, 
        u.last_name, 
        u.email,
        (SELECT COUNT(*) FROM messages WHERE 
            ((sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id))
            AND is_read = 0 AND sender_id = u.id
        ) as unread_count,
        (SELECT created_at FROM messages WHERE 
            (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)
            ORDER BY created_at DESC LIMIT 1
        ) as last_message_date
    FROM users u
    JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.id)
    WHERE u.id != ? AND u.id != ?
    ORDER BY last_message_date DESC
");
$stmt->bind_param("iiiiiiii", $support_user_id, $support_user_id, $support_user_id, $support_user_id, $support_user_id, $support_user_id, $support_user_id, $admin_id);
$stmt->execute();
$support_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// If a specific user is selected, get their conversation with support
$selected_user = null;
$messages = [];
if (isset($_GET['user']) && is_numeric($_GET['user'])) {
    $user_id = (int)$_GET['user'];
    
    // Get user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $selected_user = $stmt->get_result()->fetch_assoc();
    
    if ($selected_user) {
        // Get messages between this user and support
        $stmt = $conn->prepare("
            SELECT 
                m.*,
                u.first_name,
                u.last_name,
                u.id as user_id
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->bind_param("iiii", $user_id, $support_user_id, $support_user_id, $user_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Mark messages as read
        $stmt = $conn->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->bind_param("ii", $user_id, $support_user_id);
        $stmt->execute();
    }
}

$page_title = 'Admin Support Dashboard';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Customer Support Dashboard</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <p>Message sent successfully!</p>
        </div>
    <?php endif; ?>
    
    <div class="flex flex-col md:flex-row gap-8">
        <!-- User List -->
        <div class="w-full md:w-1/3">
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b">
                    <h2 class="font-semibold text-gray-800">Support Conversations</h2>
                </div>
                <div class="divide-y divide-gray-200 max-h-[600px] overflow-y-auto">
                    <?php if (empty($support_users)): ?>
                        <div class="p-4 text-center text-gray-500">
                            No support conversations yet
                        </div>
                    <?php else: ?>
                        <?php foreach ($support_users as $user): ?>
                            <a href="?user=<?php echo $user['id']; ?>" 
                               class="block p-4 hover:bg-gray-50 transition-colors <?php echo (isset($_GET['user']) && $_GET['user'] == $user['id']) ? 'bg-blue-50' : ''; ?>">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            <?php if ($user['unread_count'] > 0): ?>
                                                <span class="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full">
                                                    <?php echo $user['unread_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        <?php echo date('M j, Y', strtotime($user['last_message_date'])); ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="w-full md:w-2/3">
            <?php if ($selected_user): ?>
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <!-- Chat Header -->
                    <div class="bg-primary text-white px-6 py-4 border-b">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-xl font-semibold">
                                    <?php echo htmlspecialchars($selected_user['first_name'] . ' ' . $selected_user['last_name']); ?>
                                </h2>
                                <p class="text-sm text-white opacity-80">
                                    <?php echo htmlspecialchars($selected_user['email']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div class="p-6 h-[400px] overflow-y-auto flex flex-col space-y-4" id="messages">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-gray-500 my-8">
                                <p>No messages yet with this user.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="flex <?php echo $msg['sender_id'] === $support_user_id ? 'justify-end' : 'justify-start'; ?>">
                                    <div class="<?php echo $msg['sender_id'] === $support_user_id ? 'bg-primary text-white' : 'bg-gray-100 text-gray-900'; ?> rounded-lg px-4 py-2 max-w-[70%]">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="font-medium text-xs">
                                                <?php echo $msg['sender_id'] === $support_user_id ? 'Support' : htmlspecialchars($msg['first_name']); ?>
                                            </span>
                                            <span class="text-xs <?php echo $msg['sender_id'] === $support_user_id ? 'text-primary-100' : 'text-gray-500'; ?>">
                                                <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Message Input -->
                    <div class="border-t p-4">
                        <form method="POST" class="flex gap-4">
                            <input type="hidden" name="action" value="respond">
                            <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
                            <textarea name="message" 
                                      class="flex-1 rounded-lg border-gray-300 focus:border-primary focus:ring-primary resize-none"
                                      rows="2" 
                                      placeholder="Type your response as support..."
                                      required></textarea>
                            <button type="submit" class="btn btn-primary">Send as Support</button>
                        </form>
                    </div>
                </div>
                
                <!-- User Info -->
                <div class="mt-6 bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">User Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Name</p>
                            <p class="font-medium"><?php echo htmlspecialchars($selected_user['first_name'] . ' ' . $selected_user['last_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Email</p>
                            <p class="font-medium"><?php echo htmlspecialchars($selected_user['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Phone</p>
                            <p class="font-medium"><?php echo !empty($selected_user['phone']) ? htmlspecialchars($selected_user['phone']) : 'Not provided'; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Location</p>
                            <p class="font-medium"><?php echo !empty($selected_user['location']) ? htmlspecialchars($selected_user['location']) : 'Not provided'; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Account Created</p>
                            <p class="font-medium"><?php echo date('F j, Y', strtotime($selected_user['created_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Account Status</p>
                            <p class="font-medium">
                                <?php if ($selected_user['is_verified']): ?>
                                    <span class="text-green-600">Verified</span>
                                <?php else: ?>
                                    <span class="text-yellow-600">Not Verified</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Responses -->
                <div class="mt-6 bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Responses</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <button onclick="insertQuickResponse('Thank you for contacting Afrigig Support. I will be happy to help you with your inquiry.')" class="text-left p-3 border rounded hover:bg-gray-50">
                            <p class="font-medium">Greeting</p>
                            <p class="text-sm text-gray-600 truncate">Thank you for contacting Afrigig Support...</p>
                        </button>
                        <button onclick="insertQuickResponse('I understand your concern. Let me look into this for you and get back to you as soon as possible.')" class="text-left p-3 border rounded hover:bg-gray-50">
                            <p class="font-medium">Acknowledgment</p>
                            <p class="text-sm text-gray-600 truncate">I understand your concern. Let me look into this...</p>
                        </button>
                        <button onclick="insertQuickResponse('To place a bid, go to the job listing page and click on the Place Bid button. You will need to complete a skill assessment before your bid is submitted.')" class="text-left p-3 border rounded hover:bg-gray-50">
                            <p class="font-medium">How to Place a Bid</p>
                            <p class="text-sm text-gray-600 truncate">To place a bid, go to the job listing page...</p>
                        </button>
                        <button onclick="insertQuickResponse('Once your work is approved, the payment will be released to your account balance. You can withdraw funds from your profile page.')" class="text-left p-3 border rounded hover:bg-gray-50">
                            <p class="font-medium">Payment Process</p>
                            <p class="text-sm text-gray-600 truncate">Once your work is approved, the payment will be released...</p>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white shadow rounded-lg p-8 text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    <h2 class="text-xl font-semibold text-gray-800 mb-2">Select a conversation</h2>
                    <p class="text-gray-600">Choose a user from the list to view and respond to their support messages.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Scroll to bottom of messages
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.getElementById('messages');
        if (messages) {
            messages.scrollTop = messages.scrollHeight;
        }
    });
    
    // Insert quick response into textarea
    function insertQuickResponse(text) {
        const textarea = document.querySelector('textarea[name="message"]');
        textarea.value = text;
        textarea.focus();
    }
</script>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 