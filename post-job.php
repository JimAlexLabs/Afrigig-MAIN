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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = clean_input($_POST['title'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $requirements = clean_input($_POST['requirements'] ?? '');
    $pages = (int)($_POST['pages'] ?? 0);
    $deadline = clean_input($_POST['deadline'] ?? '');
    $salary = (float)($_POST['salary'] ?? 0);
    
    // Validate input
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    if ($pages <= 0) {
        $errors[] = "Pages must be greater than 0";
    }
    if (empty($deadline)) {
        $errors[] = "Deadline is required";
    }
    if ($salary <= 0) {
        $errors[] = "Salary must be greater than 0";
    }
    
    // Insert job if no validation errors
    if (empty($errors)) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("
                INSERT INTO jobs (title, description, requirements, pages, deadline, salary, status, admin_id) 
                VALUES (?, ?, ?, ?, ?, ?, 'open', ?)
            ");
            $stmt->bind_param("sssissi", $title, $description, $requirements, $pages, $deadline, $salary, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = true;
                // Clear form data
                $title = $description = $requirements = $deadline = '';
                $pages = $salary = 0;
            } else {
                $errors[] = "Failed to post job";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again.";
        }
    }
}

$page_title = 'Post New Job';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">Post New Job</h1>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p>Job posted successfully!</p>
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
        
        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" class="space-y-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Job Title</label>
                    <input type="text" name="title" id="title" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                           value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
                </div>
                
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="description" rows="5" 
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                              required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>
                
                <div>
                    <label for="requirements" class="block text-sm font-medium text-gray-700">Requirements (Optional)</label>
                    <textarea name="requirements" id="requirements" rows="3" 
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                              ><?php echo htmlspecialchars($requirements ?? ''); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="pages" class="block text-sm font-medium text-gray-700">Number of Pages</label>
                        <input type="number" name="pages" id="pages" min="1" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                               value="<?php echo htmlspecialchars($pages ?? 1); ?>" required>
                    </div>
                    
                    <div>
                        <label for="salary" class="block text-sm font-medium text-gray-700">Budget/Salary ($)</label>
                        <input type="number" name="salary" id="salary" min="1" step="0.01" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                               value="<?php echo htmlspecialchars($salary ?? 100); ?>" required>
                    </div>
                </div>
                
                <div>
                    <label for="deadline" class="block text-sm font-medium text-gray-700">Deadline</label>
                    <input type="datetime-local" name="deadline" id="deadline" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                           value="<?php echo htmlspecialchars($deadline ?? ''); ?>" required>
                </div>
                
                <div class="flex justify-end">
                    <a href="dashboard.php" class="btn btn-secondary mr-4">Cancel</a>
                    <button type="submit" class="btn btn-primary">Post Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 