<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure user is logged in and is an admin
if (!is_logged_in() || !is_admin()) {
    set_flash_message('error', 'You do not have permission to access this page');
    header('Location: /jobs');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('error', 'Invalid CSRF token');
        header("Location: {$_SERVER['REQUEST_URI']}");
        exit;
    }
    
    // Validate required fields
    $required_fields = ['title', 'category_id', 'description', 'salary', 'deadline'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (empty($errors)) {
        $conn = getDbConnection();
        
        // Handle file uploads
        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                $file_name = $_FILES['attachments']['name'][$key];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Validate file extension
                $allowed_exts = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png'];
                if (!in_array($file_ext, $allowed_exts)) {
                    $errors[] = "File type not allowed: $file_name";
                    continue;
                }
                
                // Generate unique filename
                $new_name = uniqid() . '_' . $file_name;
                $file_path = $upload_dir . $new_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $attachments[] = $new_name;
                } else {
                    $errors[] = "Failed to upload file: $file_name";
                }
            }
        }
        
        if (empty($errors)) {
            // Insert job
            $stmt = $conn->prepare("
                INSERT INTO jobs (
                    title, 
                    category_id, 
                    description, 
                    salary, 
                    deadline, 
                    attachments,
                    client_id,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            
            $title = clean_input($_POST['title']);
            $category_id = (int)$_POST['category_id'];
            $description = clean_input($_POST['description']);
            $salary = (float)$_POST['salary'];
            $deadline = clean_input($_POST['deadline']);
            $attachments_json = !empty($attachments) ? json_encode($attachments) : null;
            $client_id = get_current_user_id();
            
            $stmt->bind_param('sisdsis', 
                $title,
                $category_id,
                $description,
                $salary,
                $deadline,
                $attachments_json,
                $client_id
            );
            
            if ($stmt->execute()) {
                $job_id = $conn->insert_id;
                
                // Create notification for all freelancers
                $stmt = $conn->prepare("
                    INSERT INTO notifications (
                        user_id,
                        title,
                        message,
                        type,
                        reference_id,
                        created_at
                    )
                    SELECT 
                        id,
                        'New Job Posted',
                        ?,
                        'job',
                        ?,
                        NOW()
                    FROM users 
                    WHERE role = 'freelancer'
                ");
                
                $message = "A new job '{$title}' has been posted with a budget of " . format_money($salary);
                $stmt->bind_param('si', $message, $job_id);
                $stmt->execute();
                
                set_flash_message('success', 'Job posted successfully');
                header('Location: /jobs/view.php?id=' . $job_id);
                exit;
            } else {
                $errors[] = 'Failed to create job. Please try again.';
            }
        }
    }
}

// Get categories for dropdown
$conn = getDbConnection();
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Page title
$page_title = 'Post New Job';

// Additional styles
$additional_styles = '
<style>
    .file-upload {
        border: 2px dashed var(--border-color);
        padding: 2rem;
        text-align: center;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .file-upload:hover {
        border-color: var(--primary-color);
    }
    
    .file-upload input[type="file"] {
        display: none;
    }
    
    .file-list {
        margin-top: 1rem;
    }
    
    .file-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem;
        background: var(--background-color);
        border-radius: 0.25rem;
        margin-bottom: 0.5rem;
    }
    
    .remove-file {
        color: var(--danger-color);
        cursor: pointer;
    }
</style>
';

// Additional scripts
$additional_scripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const fileUpload = document.getElementById("attachments");
    const fileList = document.getElementById("file-list");
    const maxFiles = 5;
    
    fileUpload.addEventListener("change", function() {
        const files = Array.from(this.files);
        
        // Check file count
        if (files.length > maxFiles) {
            alert(`You can only upload up to ${maxFiles} files`);
            this.value = "";
            return;
        }
        
        // Clear file list
        fileList.innerHTML = "";
        
        // Add files to list
        files.forEach(file => {
            const item = document.createElement("div");
            item.className = "file-item";
            item.innerHTML = `
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                ${file.name}
                <span class="text-xs text-secondary">(${formatFileSize(file.size)})</span>
            `;
            fileList.appendChild(item);
        });
    });
    
    function formatFileSize(bytes) {
        if (bytes === 0) return "0 B";
        const k = 1024;
        const sizes = ["B", "KB", "MB", "GB"];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
    }
});
</script>
';

// Start output buffering
ob_start();
?>

<div class="card">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Post New Job</h1>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" data-validate>
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        
        <div class="form-group">
            <label for="title" class="form-label">Job Title</label>
            <input type="text" id="title" name="title" class="form-input" required
                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                   placeholder="e.g., Website Development Project">
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-group">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-input" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"
                                <?php echo isset($_POST['category_id']) && $_POST['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="salary" class="form-label">Budget</label>
                <input type="number" id="salary" name="salary" class="form-input" required
                       min="1" step="0.01"
                       value="<?php echo isset($_POST['salary']) ? htmlspecialchars($_POST['salary']) : ''; ?>"
                       placeholder="Enter amount">
            </div>
        </div>
        
        <div class="form-group">
            <label for="description" class="form-label">Job Description</label>
            <textarea id="description" name="description" class="form-input" rows="6" required
                      placeholder="Describe the job requirements, deliverables, and any specific instructions..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="deadline" class="form-label">Deadline</label>
            <input type="datetime-local" id="deadline" name="deadline" class="form-input" required
                   min="<?php echo date('Y-m-d\TH:i'); ?>"
                   value="<?php echo isset($_POST['deadline']) ? htmlspecialchars($_POST['deadline']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">Attachments</label>
            <label for="attachments" class="file-upload">
                <svg class="w-8 h-8 mx-auto mb-2 text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <div class="text-lg font-medium">Drop files here or click to upload</div>
                <div class="text-sm text-secondary mt-1">
                    Supported formats: PDF, DOC, DOCX, TXT, JPG, JPEG, PNG (max 5 files)
                </div>
                <input type="file" id="attachments" name="attachments[]" multiple
                       accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png">
            </label>
            <div id="file-list" class="file-list"></div>
        </div>
        
        <div class="flex justify-end gap-4">
            <a href="/jobs" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Post Job</button>
        </div>
    </form>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout template
require_once __DIR__ . '/../views/partials/layout.php'; 