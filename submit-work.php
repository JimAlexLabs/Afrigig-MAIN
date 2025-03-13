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
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check if user is a freelancer
if ($user['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

// Check if job ID is provided
if (!isset($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
    header('Location: my-jobs.php');
    exit;
}

$job_id = intval($_GET['job_id']);

// Get job details and check if the freelancer has an accepted bid
$stmt = $conn->prepare("
    SELECT j.*, b.amount as bid_amount, b.delivery_time, b.status as bid_status, 
           u.first_name, u.last_name, u.profile_image, u.email
    FROM jobs j
    JOIN bids b ON j.id = b.job_id
    JOIN users u ON j.admin_id = u.id
    WHERE j.id = ? AND b.freelancer_id = ? AND b.status = 'accepted' AND j.status = 'in_progress'
");
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

// If job doesn't exist or freelancer doesn't have an accepted bid, redirect
if (!$job) {
    header('Location: my-jobs.php');
    exit;
}

// Check if the submissions table exists
$result = $conn->query("SHOW TABLES LIKE 'submissions'");
if ($result->num_rows == 0) {
    // Create submissions table
    $conn->query("
        CREATE TABLE submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            freelancer_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            file_path VARCHAR(255),
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            feedback TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES jobs(id),
            FOREIGN KEY (freelancer_id) REFERENCES users(id)
        )
    ");
}

// Check if the freelancer has already submitted work for this job
$stmt = $conn->prepare("SELECT * FROM submissions WHERE job_id = ? AND freelancer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$has_submissions = !empty($submissions);

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $title = clean_input($_POST['title'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    // Handle file upload
    $file_path = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/submissions/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = $_FILES['submission_file']['name'];
        $file_tmp = $_FILES['submission_file']['tmp_name'];
        $file_size = $_FILES['submission_file']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $new_file_name = uniqid('submission_') . '_' . $job_id . '_' . $user_id . '.' . $file_ext;
        $file_path = $upload_dir . $new_file_name;
        
        // Check file size (limit to 10MB)
        if ($file_size > 10 * 1024 * 1024) {
            $errors[] = "File size must be less than 10MB";
        }
        
        // Check file extension
        $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "Only PDF, DOC, DOCX, TXT, ZIP, RAR, JPG, JPEG, PNG, and GIF files are allowed";
        }
        
        // Upload file if no errors
        if (empty($errors)) {
            if (!move_uploaded_file($file_tmp, $file_path)) {
                $errors[] = "Failed to upload file";
            }
        }
    }
    
    // Insert submission if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO submissions (job_id, freelancer_id, title, description, file_path)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $job_id, $user_id, $title, $description, $file_path);
        
        if ($stmt->execute()) {
            $success = true;
            
            // Send notification to client
            $notification_message = "New submission for job: " . $job['title'];
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, message, link, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $link = "review-submission.php?job_id=" . $job_id;
            $stmt->bind_param("iss", $job['admin_id'], $notification_message, $link);
            $stmt->execute();
            
            // Redirect to prevent form resubmission
            header("Location: submit-work.php?job_id=" . $job_id . "&success=1");
            exit;
        } else {
            $errors[] = "Failed to submit work. Please try again.";
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
}

$page_title = 'Submit Work';

// Add custom CSS and JS
$custom_css = [
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://unpkg.com/aos@2.3.1/dist/aos.css',
    'assets/css/dashboard.css'
];
$custom_js = [
    'https://unpkg.com/aos@2.3.1/dist/aos.js'
];

ob_start();
?>

<div class="dashboard-layout">
    <!-- Include sidebar from dashboard -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container">
            <div class="mb-4">
                <a href="my-jobs.php" class="text-primary hover:text-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to My Jobs
                </a>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Submit Work Column -->
                <div class="lg:col-span-2">
                    <div class="content-card mb-6" data-aos="fade-up">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-upload"></i>
                                Submit Work
                            </h2>
                        </div>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success mb-6">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Your work has been submitted successfully! The client will review it and provide feedback.
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger mb-6">
                                    <ul class="list-disc pl-5">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Submission Title *</label>
                                    <input type="text" id="title" name="title" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                           placeholder="e.g., Final Draft, Version 1.0, etc."
                                           required>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                                    <textarea id="description" name="description" rows="5"
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                                              placeholder="Describe what you're submitting and any notes for the client..."
                                              required></textarea>
                                </div>
                                
                                <div class="mb-6">
                                    <label for="submission_file" class="block text-sm font-medium text-gray-700 mb-2">Upload File</label>
                                    <div class="border border-dashed border-gray-300 rounded-lg p-6 text-center">
                                        <input type="file" id="submission_file" name="submission_file" class="hidden">
                                        <label for="submission_file" class="cursor-pointer">
                                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                            <p class="text-gray-500">Click to select a file or drag and drop</p>
                                            <p class="text-xs text-gray-400 mt-1">Max file size: 10MB. Allowed formats: PDF, DOC, DOCX, TXT, ZIP, RAR, JPG, JPEG, PNG, GIF</p>
                                        </label>
                                        <div id="file-name" class="mt-2 text-sm text-primary hidden"></div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane mr-2"></i> Submit Work
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php if ($has_submissions): ?>
                        <div class="content-card" data-aos="fade-up" data-aos-delay="100">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i class="fas fa-history"></i>
                                    Previous Submissions
                                </h2>
                            </div>
                            <div class="card-body">
                                <div class="space-y-6">
                                    <?php foreach ($submissions as $index => $submission): ?>
                                        <div class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($submission['title']); ?></h3>
                                                    <p class="text-sm text-gray-500">Submitted on <?php echo date('M j, Y \a\t g:i a', strtotime($submission['created_at'])); ?></p>
                                                </div>
                                                
                                                <?php
                                                $status_class = 'bg-blue-100 text-blue-800';
                                                $status_text = 'Pending Review';
                                                $status_icon = 'fa-clock';
                                                
                                                if ($submission['status'] === 'approved') {
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    $status_text = 'Approved';
                                                    $status_icon = 'fa-check-circle';
                                                } elseif ($submission['status'] === 'rejected') {
                                                    $status_class = 'bg-red-100 text-red-800';
                                                    $status_text = 'Needs Revision';
                                                    $status_icon = 'fa-times-circle';
                                                }
                                                ?>
                                                
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                                    <i class="fas <?php echo $status_icon; ?> mr-1"></i> <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <p><?php echo nl2br(htmlspecialchars($submission['description'])); ?></p>
                                            </div>
                                            
                                            <?php if (!empty($submission['file_path'])): ?>
                                                <div class="mb-4">
                                                    <a href="<?php echo htmlspecialchars($submission['file_path']); ?>" class="btn btn-sm btn-outline" target="_blank">
                                                        <i class="fas fa-download mr-1"></i> Download Attachment
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($submission['feedback'])): ?>
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <h4 class="font-semibold mb-2">Client Feedback:</h4>
                                                    <p><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar Column -->
                <div class="lg:col-span-1">
                    <!-- Job Details -->
                    <div class="content-card mb-6" data-aos="fade-up" data-aos-delay="50">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-briefcase"></i>
                                Job Details
                            </h2>
                        </div>
                        <div class="card-body">
                            <h3 class="text-lg font-semibold mb-4">
                                <a href="view-job.php?id=<?php echo $job['id']; ?>" class="text-primary hover:text-secondary">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </a>
                            </h3>
                            
                            <ul class="space-y-3 mb-6">
                                <li class="flex items-center">
                                    <i class="fas fa-user text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Client</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($job['first_name'] . ' ' . $job['last_name']); ?></p>
                                    </div>
                                </li>
                                
                                <li class="flex items-center">
                                    <i class="fas fa-money-bill-wave text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Your Bid</p>
                                        <p class="font-medium">$<?php echo number_format($job['bid_amount'], 2); ?></p>
                                    </div>
                                </li>
                                
                                <li class="flex items-center">
                                    <i class="fas fa-clock text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Deadline</p>
                                        <p class="font-medium"><?php echo isset($job['deadline']) ? date('M j, Y', strtotime($job['deadline'])) : date('M j, Y', strtotime($job['created_at'] . ' +30 days')); ?></p>
                                    </div>
                                </li>
                                
                                <?php
                                // Calculate days remaining
                                $deadline = isset($job['deadline']) ? strtotime($job['deadline']) : strtotime($job['created_at'] . ' +30 days');
                                $now = time();
                                $days_remaining = ceil(($deadline - $now) / (60 * 60 * 24));
                                
                                $status_class = 'text-green-600';
                                if ($days_remaining <= 2) {
                                    $status_class = 'text-red-600';
                                } elseif ($days_remaining <= 5) {
                                    $status_class = 'text-yellow-600';
                                }
                                ?>
                                
                                <li class="flex items-center">
                                    <i class="fas fa-hourglass-half text-primary mr-3"></i>
                                    <div>
                                        <p class="text-sm text-gray-500">Time Remaining</p>
                                        <p class="font-medium <?php echo $status_class; ?>"><?php echo $days_remaining; ?> days left</p>
                                    </div>
                                </li>
                            </ul>
                            
                            <div class="mb-4">
                                <a href="messages.php?recipient=<?php echo $job['admin_id']; ?>" class="btn btn-outline w-full">
                                    <i class="fas fa-envelope mr-2"></i> Message Client
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submission Tips -->
                    <div class="content-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-lightbulb"></i>
                                Submission Tips
                            </h2>
                        </div>
                        <div class="card-body">
                            <ul class="space-y-3">
                                <li class="flex">
                                    <i class="fas fa-check-circle text-primary mt-1 mr-3"></i>
                                    <p>Make sure your work meets all the requirements specified in the job description.</p>
                                </li>
                                <li class="flex">
                                    <i class="fas fa-check-circle text-primary mt-1 mr-3"></i>
                                    <p>Double-check your work for errors before submitting.</p>
                                </li>
                                <li class="flex">
                                    <i class="fas fa-check-circle text-primary mt-1 mr-3"></i>
                                    <p>Provide a clear description of what you're submitting and any notes the client should know.</p>
                                </li>
                                <li class="flex">
                                    <i class="fas fa-check-circle text-primary mt-1 mr-3"></i>
                                    <p>If your file is too large, consider using a file compression tool or a file sharing service.</p>
                                </li>
                                <li class="flex">
                                    <i class="fas fa-check-circle text-primary mt-1 mr-3"></i>
                                    <p>Be responsive to client feedback and be prepared to make revisions if needed.</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.alert-success {
    background-color: #d1fae5;
    color: #065f46;
}

.alert-danger {
    background-color: #fee2e2;
    color: #b91c1c;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // File upload preview
        const fileInput = document.getElementById('submission_file');
        const fileNameDisplay = document.getElementById('file-name');
        
        if (fileInput && fileNameDisplay) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    fileNameDisplay.textContent = this.files[0].name;
                    fileNameDisplay.classList.remove('hidden');
                } else {
                    fileNameDisplay.textContent = '';
                    fileNameDisplay.classList.add('hidden');
                }
            });
        }
        
        // Drag and drop functionality
        const dropZone = document.querySelector('.border-dashed');
        
        if (dropZone && fileInput) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropZone.classList.add('border-primary', 'bg-blue-50');
            }
            
            function unhighlight() {
                dropZone.classList.remove('border-primary', 'bg-blue-50');
            }
            
            dropZone.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files && files.length) {
                    fileInput.files = files;
                    fileNameDisplay.textContent = files[0].name;
                    fileNameDisplay.classList.remove('hidden');
                }
            }
        }
    });
</script>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 