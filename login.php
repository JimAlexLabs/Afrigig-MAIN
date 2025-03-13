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

ob_start();
?>

<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Sign in as <?php echo $is_admin_login ? 'Admin' : 'Freelancer'; ?>
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Or
                <a href="register.php<?php echo $is_admin_login ? '?type=admin' : ''; ?>" class="font-medium text-primary hover:text-secondary">
                    create a new account
                </a>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4">
                <div class="flex">
                    <div class="ml-3">
                        <ul class="list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . ($is_admin_login ? '?type=admin' : '')); ?>" method="POST">
            <input type="hidden" name="type" value="<?php echo $is_admin_login ? 'admin' : 'user'; ?>">
            
            <div class="rounded-md shadow-sm space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <input id="email" name="email" type="email" required
                           class="mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" required
                           class="mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
                </div>
            </div>

            <div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-secondary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    Sign in
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 