<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Afrigig</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom CSS Variables -->
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #4f46e5;
            --surface-color: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
        }
        
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
            line-height: 1.5;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-align: center;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary-color);
        }
        
        .btn-secondary:hover {
            background: var(--surface-color);
        }
        
        .btn-outline-light {
            border: 2px solid white;
            color: white;
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
    
    <!-- Additional page-specific styles -->
    <?php echo isset($additional_styles) ? $additional_styles : ''; ?>
    
    <!-- Custom CSS files -->
    <?php if (isset($custom_css) && is_array($custom_css)): ?>
        <?php foreach ($custom_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_file); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <a href="index.php" class="text-2xl font-bold text-primary">Afrigig</a>
                
                <div class="flex gap-6 items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['is_admin']): ?>
                            <a href="dashboard.php" class="hover:text-primary">Dashboard</a>
                            <a href="profile.php" class="hover:text-primary">Profile</a>
                            <a href="post-job.php" class="hover:text-primary">Post New Job</a>
                            <a href="manage-jobs.php" class="hover:text-primary">Manage Jobs</a>
                            <a href="manage-users.php" class="hover:text-primary">Manage Users</a>
                            <a href="messages.php" class="hover:text-primary">Messages</a>
                            <a href="settings.php" class="hover:text-primary">Settings</a>
                        <?php else: ?>
                            <a href="dashboard.php" class="hover:text-primary">Dashboard</a>
                            <a href="jobs.php" class="hover:text-primary">Find Jobs</a>
                            <a href="my-jobs.php" class="hover:text-primary">My Jobs</a>
                            <a href="profile.php" class="hover:text-primary">Profile</a>
                            <a href="messages.php" class="hover:text-primary">Messages</a>
                        <?php endif; ?>
                        <div class="relative ml-3 group">
                            <button class="flex items-center gap-2 text-sm focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden group-hover:block">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="jobs.php" class="hover:text-primary">Jobs</a>
                        <a href="about.php" class="hover:text-primary">About</a>
                        <a href="login.php" class="hover:text-primary">Login</a>
                        <a href="register.php" class="btn btn-primary">Get Started</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main>
        <?php echo $content; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">About Us</h3>
                    <p class="text-gray-400">Empowering African talent through technology and connecting them with global opportunities.</p>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="dashboard.php" class="text-gray-400 hover:text-white">Dashboard</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white">About</a></li>
                        <li><a href="jobs.php" class="text-gray-400 hover:text-white">Jobs</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4">Follow Us</h3>
                    <div class="flex gap-4">
                        <a href="https://facebook.com/afrigig" target="_blank" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="https://twitter.com/afrigig" target="_blank" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                        </a>
                        <a href="https://linkedin.com/company/afrigig" target="_blank" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4">Contact</h3>
                    <p class="text-gray-400">Need help? Chat with us</p>
                    <a href="contact.php" class="btn btn-primary mt-4">Chat with us</a>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-12 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Afrigig. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Chat Widget Script -->
    <script>
        // Initialize chat widget when available
        document.addEventListener('DOMContentLoaded', function() {
            const chatButtons = document.querySelectorAll('[data-chat]');
            chatButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Implement chat functionality
                    window.location.href = 'contact.php';
                });
            });
        });
    </script>
    
    <!-- Custom JS files -->
    <?php if (isset($custom_js) && is_array($custom_js)): ?>
        <?php foreach ($custom_js as $js_file): ?>
            <script src="<?php echo htmlspecialchars($js_file); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html> 