<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Debug database connection
try {
    $test_conn = getDbConnection();
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $is_admin_login = isset($_POST['type']) && $_POST['type'] === 'admin';

    // Validate input
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    }

    // Attempt login if no validation errors
    if (empty($errors)) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("SELECT id, first_name, password, is_admin FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    // For admin login, check if user is admin
                    if ($is_admin_login && !$user['is_admin']) {
                        $errors[] = "This account is not authorized for admin access";
                    } elseif (!$is_admin_login && $user['is_admin']) {
                        $errors[] = "Please use the admin login for this account";
                    } else {
                        // Login successful
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['is_admin'] = $user['is_admin'];
                        $_SESSION['first_name'] = $user['first_name'];
                        
                        // Redirect to dashboard
                        header('Location: dashboard.php');
                        exit;
                    }
                } else {
                    $errors[] = "Invalid password";
                }
            } else {
                $errors[] = "No account found with this email";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again.";
        }
    }
}

$page_title = 'Login';
$is_admin_login = isset($_GET['type']) && $_GET['type'] === 'admin';

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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                </svg>
            </div>
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">
                Sign in as <?php echo $is_admin_login ? 'Admin' : 'Freelancer'; ?> or 
                <a href="register.php<?php echo $is_admin_login ? '?type=admin' : ''; ?>">create an account</a>
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

        <form class="auth-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($is_admin_login ? '?type=admin' : '')); ?>" method="POST">
            <input type="hidden" name="type" value="<?php echo $is_admin_login ? 'admin' : 'user'; ?>">
            
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
            </div>

            <div class="auth-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="#" class="forgot-password">Forgot password?</a>
            </div>

            <button type="submit" class="auth-button">
                Sign in
            </button>
        </form>

        <div class="social-login">
            <div class="social-login-title">Or continue with</div>
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
        const icon = document.querySelector('.password-toggle i');
        
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
?> 