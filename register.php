<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $first_name = clean_input($_POST['first_name'] ?? '');
    $last_name = clean_input($_POST['last_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $is_admin = isset($_POST['type']) && $_POST['type'] === 'admin';

    // Validate input
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";

    // Check if email already exists
    if (empty($errors)) {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already registered";
        }
    }

    // Register user if no errors
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, email, password, is_admin, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssssi", $first_name, $last_name, $email, $hashed_password, $is_admin);
        
        if ($stmt->execute()) {
            // Log the user in
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['is_admin'] = $is_admin;
            $_SESSION['first_name'] = $first_name;
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}

$page_title = 'Register';
$is_admin_registration = isset($_GET['type']) && $_GET['type'] === 'admin';

// Custom CSS and JS
$custom_css = ['assets/css/auth.css'];
$custom_js = ['https://unpkg.com/aos@2.3.1/dist/aos.js'];

// Additional styles for AOS
$additional_styles = '
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
';

ob_start();
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </div>
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">
                Register as <?php echo $is_admin_registration ? 'Admin' : 'Freelancer'; ?> or 
                <a href="login.php<?php echo $is_admin_registration ? '?type=admin' : ''; ?>">sign in</a>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-container">
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="POST">
            <input type="hidden" name="type" value="<?php echo $is_admin_registration ? 'admin' : 'user'; ?>">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name" class="form-label">First Name</label>
                    <input id="first_name" name="first_name" type="text" required class="form-control"
                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input id="last_name" name="last_name" type="text" required class="form-control"
                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email address</label>
                <input id="email" name="email" type="email" required class="form-control"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required class="form-control">
                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small class="text-xs text-gray-500">Password must be at least 8 characters</small>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="password-field">
                    <input id="confirm_password" name="confirm_password" type="password" required class="form-control">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="auth-button">
                Create Account
            </button>
        </form>

        <div class="social-login">
            <div class="social-login-title">Or register with</div>
            <div class="social-buttons">
                <a href="#" class="social-button">
                    <i class="fab fa-google"></i>
                </a>
                <a href="#" class="social-button">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" class="social-button">
                    <i class="fab fa-twitter"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePassword(id) {
        const passwordInput = document.getElementById(id);
        const icon = document.querySelector(`#${id} + .password-toggle i`);
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize AOS
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true
            });
        }
    });
</script>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 