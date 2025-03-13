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

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid job ID');
    header('Location: /jobs');
    exit;
}

$job_id = (int)$_GET['id'];

// Get job details
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT j.*, c.name as category_name
    FROM jobs j
    LEFT JOIN categories c ON j.category_id = c.id
    WHERE j.id = ?
");

$stmt->bind_param('i', $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    set_flash_message('error', 'Job not found');
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
    $required_fields = ['title', 'category_id', 'description', 'salary', 'deadline', 'status'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    if (empty($errors)) {
        // Handle file uploads
        $attachments = json_decode($job['attachments'] ?? '[]');
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
        
        // Remove deleted attachments
        if (!empty($_POST['delete_attachments'])) {
            $delete_attachments = json_decode($_POST['delete_attachments']);
            foreach ($delete_attachments as $file) {
                $key = array_search($file, $attachments);
                if ($key !== false) {
                    unset($attachments[$key]);
                    $file_path = __DIR__ . '/../uploads/' . $file;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
            $attachments = array_values($attachments);
        }
        
        if (empty($errors)) {
            // Update job
            $stmt = $conn->prepare("
                UPDATE jobs SET
                    title = ?,
                    category_id = ?,
                    description = ?,
                    salary = ?,
                    deadline = ?,
                    attachments = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $title = clean_input($_POST['title']);
            $category_id = (int)$_POST['category_id'];
            $description = clean_input($_POST['description']);
            $salary = (float)$_POST['salary'];
            $deadline = clean_input($_POST['deadline']);
            $attachments_json = !empty($attachments) ? json_encode(array_values($attachments)) : null;
            $status = clean_input($_POST['status']);
            
            $stmt->bind_param('sisdsssi', 
                $title,
                $category_id,
                $description,
                $salary,
                $deadline,
                $attachments_json,
                $status,
                $job_id
            );
            
            if ($stmt->execute()) {
                // Notify relevant users about status change
                if ($status !== $job['status']) {
                    // Notify job owner
                    create_notification(
                        $job['client_id'],
                        'Job Status Updated',
                        "Your job '{$title}' status has been updated to " . ucfirst($status),
                        'job_status',
                        $job_id
                    );
                    
                    // If job is completed, notify the accepted freelancer
                    if ($status === 'completed') {
                        $stmt = $conn->prepare("
                            SELECT freelancer_id 
                            FROM bids 
                            WHERE job_id = ? AND status = 'accepted'
                        ");
                        $stmt->bind_param('i', $job_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($freelancer = $result->fetch_assoc()) {
                            create_notification(
                                $freelancer['freelancer_id'],
                                'Job Completed',
                                "The job '{$title}' has been marked as completed",
                                'job_completed',
                                $job_id
                            );
                        }
                    }
                }
                
                set_flash_message('success', 'Job updated successfully');
                header('Location: /jobs/view.php?id=' . $job_id);
                exit;
            } else {
                $errors[] = 'Failed to update job. Please try again.';
            }
        }
    }
}

// Get categories for dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Page title
$page_title = 'Edit Job: ' . $job['title'];

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
        justify-content: space-between;
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
    const deleteAttachments = new Set();
    const maxFiles = 5;
    
    // Handle new file uploads
    fileUpload.addEventListener("change", function() {
        const files = Array.from(this.files);
        const currentFiles = document.querySelectorAll(".file-item").length;
        
        // Check total file count
        if (files.length + currentFiles - deleteAttachments.size > maxFiles) {
            alert(`You can only have up to ${maxFiles} files in total`);
            this.value = "";
            return;
        }
        
        // Add new files to list
        files.forEach(file => {
            const item = document.createElement("div");
            item.className = "file-item";
            item.innerHTML = `
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                    ${file.name}
                    <span class="text-xs text-secondary">(${formatFileSize(file.size)})</span>
                </div>
            `;
            fileList.appendChild(item);
        });
    });
    
    // Handle existing file deletion
    document.querySelectorAll(".remove-file").forEach(button => {
        button.addEventListener("click", function() {
            const fileName = this.dataset.file;
            const fileItem = this.closest(".file-item");
            
            deleteAttachments.add(fileName);
            document.getElementById("delete_attachments").value = JSON.stringify(Array.from(deleteAttachments));
            fileItem.remove();
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
        <h1 class="text-2xl font-bold">Edit Job: <?php echo htmlspecialchars($job['title']); ?></h1>
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
        <input type="hidden" name="delete_attachments" id="delete_attachments" value="[]">
        
        <div class="form-group">
            <label for="title" class="form-label">Job Title</label>
            <input type="text" id="title" name="title" class="form-input" required
                   value="<?php echo htmlspecialchars($job['title']); ?>">
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="form-group">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-input" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"
                                <?php echo $job['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="salary" class="form-label">Budget</label>
                <input type="number" id="salary" name="salary" class="form-input" required
                       min="1" step="0.01"
                       value="<?php echo htmlspecialchars($job['salary']); ?>">
            </div>
            
            <div class="form-group">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input" required>
                    <option value="open" <?php echo $job['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $job['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo $job['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $job['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="description" class="form-label">Job Description</label>
            <textarea id="description" name="description" class="form-input" rows="6" required><?php echo htmlspecialchars($job['description']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="deadline" class="form-label">Deadline</label>
            <input type="datetime-local" id="deadline" name="deadline" class="form-input" required
                   value="<?php echo date('Y-m-d\TH:i', strtotime($job['deadline'])); ?>">
        </div>
        
        <div class="form-group">
            <label class="form-label">Attachments</label>
            
            <?php if ($job['attachments']): ?>
                <div id="file-list" class="file-list">
                    <?php foreach (json_decode($job['attachments']) as $file): ?>
                        <div class="file-item">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                <?php echo htmlspecialchars($file); ?>
                            </div>
                            <button type="button" class="remove-file" data-file="<?php echo htmlspecialchars($file); ?>">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <label for="attachments" class="file-upload mt-4">
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
        </div>
        
        <div class="flex justify-end gap-4">
            <a href="/jobs/view.php?id=<?php echo $job_id; ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Job</button>
        </div>
    </form>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout template
require_once __DIR__ . '/../views/partials/layout.php'; 