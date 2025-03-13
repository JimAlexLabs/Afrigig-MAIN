<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Get latest jobs
$conn = getDbConnection();
$stmt = $conn->prepare("
    SELECT j.*, COUNT(DISTINCT b.id) as bid_count 
    FROM jobs j 
    LEFT JOIN bids b ON j.id = b.job_id 
    WHERE j.status = 'open' 
    GROUP BY j.id 
    ORDER BY j.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$latest_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total fees
$total_fees = array_sum(array_column($latest_jobs, 'salary'));

// Get testimonials
$stmt = $conn->prepare("
    SELECT t.*, u.first_name, u.last_name, u.profile_image, 'Member' as user_title
    FROM testimonials t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.is_featured = 1 
    LIMIT 3
");
$stmt->execute();
$testimonials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Page title
$page_title = 'Home';

// Additional styles
$additional_styles = '
<style>
    .hero {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        padding: 6rem 2rem;
        text-align: center;
        color: white;
        border-radius: 0;
        margin-bottom: 4rem;
    }
    
    .hero h1 {
        font-size: 3.5rem;
        margin-bottom: 1.5rem;
        font-weight: 700;
    }
    
    .hero p {
        font-size: 1.5rem;
        margin-bottom: 2.5rem;
        opacity: 0.9;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .features {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2.5rem;
        margin-bottom: 5rem;
        padding: 0 2rem;
    }
    
    .feature-card {
        background: var(--surface-color);
        padding: 2.5rem;
        border-radius: 1rem;
        text-align: center;
        transition: transform 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .feature-card:hover {
        transform: translateY(-10px);
    }
    
    .feature-icon {
        width: 5rem;
        height: 5rem;
        margin-bottom: 1.5rem;
        color: var(--primary-color);
    }
    
    .stats {
        background: var(--surface-color);
        padding: 4rem 2rem;
        margin-bottom: 5rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 3rem;
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }
    
    .stat-number {
        font-size: 3rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
    }
    
    .contact-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 3rem;
        margin-bottom: 5rem;
        padding: 0 2rem;
    }
    
    .contact-card {
        background: var(--surface-color);
        padding: 2.5rem;
        border-radius: 1rem;
        text-align: center;
    }
    
    .testimonials {
        padding: 4rem 2rem;
        background: var(--surface-color);
        margin-bottom: 5rem;
    }
    
    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2.5rem;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .testimonial-card {
        background: white;
        padding: 2rem;
        border-radius: 1rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .testimonial-header {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .testimonial-avatar {
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 50%;
        margin-right: 1.5rem;
        object-fit: cover;
    }
    
    .latest-jobs {
        padding: 4rem 2rem;
        max-width: 1200px;
        margin: 0 auto 5rem;
    }
    
    .job-card {
        background: var(--surface-color);
        padding: 1.5rem;
        border-radius: 1rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: transform 0.2s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .job-card:hover {
        transform: translateX(5px);
    }
    
    .contact-form {
        max-width: 600px;
        margin: 0 auto 5rem;
        padding: 0 2rem;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    @media (max-width: 768px) {
        .hero h1 {
            font-size: 2.5rem;
        }
        
        .hero p {
            font-size: 1.25rem;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
';

// Start output buffering
ob_start();
?>

<!-- Hero section -->
<section class="hero">
    <h1>Empowering African Talent</h1>
    <p>Connecting skilled African professionals with global opportunities. Join our platform to showcase your expertise and find exciting projects.</p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
        <div class="space-x-4">
            <a href="register.php" class="btn btn-secondary">Sign Up as Freelancer</a>
            <a href="login.php" class="btn btn-outline-light">Login as Freelancer</a>
        </div>
        <div class="space-x-4">
            <a href="register.php?type=admin" class="btn btn-primary">Sign Up as Admin</a>
            <a href="login.php?type=admin" class="btn btn-outline-light">Login as Admin</a>
        </div>
    </div>
    <div class="mt-8">
        <a href="#about" class="btn btn-outline-light">Learn More</a>
    </div>
</section>

<!-- About section -->
<section id="about" class="container mx-auto px-4 mb-20">
    <h2 class="text-3xl font-bold text-center mb-8">About Afrigig</h2>
    <p class="text-center text-lg max-w-3xl mx-auto mb-16">
        We're on a mission to showcase African talent to the world. Our platform connects skilled professionals with opportunities that match their expertise.
    </p>
</section>

<!-- Features section -->
<section class="features">
    <div class="feature-card">
        <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <h3 class="text-xl font-bold mb-3">Community</h3>
        <p>Join our growing community of African professionals and expand your network.</p>
    </div>
    
    <div class="feature-card">
        <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        <h3 class="text-xl font-bold mb-3">Opportunities</h3>
        <p>Access high-quality job opportunities from reputable organizations worldwide.</p>
    </div>
    
    <div class="feature-card">
        <svg class="feature-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
        </svg>
        <h3 class="text-xl font-bold mb-3">Growth</h3>
        <p>Enhance your skills through our training programs and skill assessments.</p>
    </div>
</section>

<!-- Stats section -->
<section class="stats">
    <h2 class="text-3xl font-bold text-center mb-12">Our Impact</h2>
    <p class="text-center text-lg mb-12">Making a difference in the African tech ecosystem</p>
    
    <div class="stats-grid">
        <div>
            <div class="stat-number">5000+</div>
            <div class="text-lg">Registered Professionals</div>
        </div>
        
        <div>
            <div class="stat-number">1000+</div>
            <div class="text-lg">Completed Projects</div>
        </div>
        
        <div>
            <div class="stat-number">50+</div>
            <div class="text-lg">Countries Reached</div>
        </div>
        
        <div>
            <div class="stat-number">$2M+</div>
            <div class="text-lg">Paid to Freelancers</div>
        </div>
    </div>
</section>

<!-- Contact section -->
<section class="container mx-auto mb-20">
    <h2 class="text-3xl font-bold text-center mb-12">Get in Touch</h2>
    <p class="text-center text-lg mb-12">We're here to help you succeed</p>
    
    <div class="contact-section">
        <div class="contact-card">
            <h3 class="text-xl font-bold mb-4">Email Us</h3>
            <p class="mb-4">Get in touch with our support team</p>
            <a href="mailto:support@afrigig.org" class="text-primary hover:underline">support@afrigig.org</a>
        </div>
        
        <div class="contact-card">
            <h3 class="text-xl font-bold mb-4">Live Chat</h3>
            <p class="mb-4">Chat with our support team</p>
            <span class="text-green-500">Online</span>
        </div>
        
        <div class="contact-card">
            <h3 class="text-xl font-bold mb-4">Visit Us</h3>
            <p class="mb-4">Manchester Office</p>
            <address class="not-italic">
                125 Deansgate<br>
                Manchester M3 2BY<br>
                United Kingdom
            </address>
        </div>
    </div>
</section>

<!-- Testimonials section -->
<section class="testimonials">
    <h2 class="text-3xl font-bold text-center mb-12">What Our Users Say</h2>
    <p class="text-center text-lg mb-12">Success stories from our community</p>
    
    <div class="testimonials-grid">
        <?php foreach ($testimonials as $testimonial): ?>
        <div class="testimonial-card">
            <div class="testimonial-header">
                <img src="<?php echo htmlspecialchars($testimonial['profile_image']); ?>" 
                     alt="<?php echo htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']); ?>" 
                     class="testimonial-avatar">
                <div>
                    <h3 class="font-bold"><?php echo htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']); ?></h3>
                    <p class="text-gray-600"><?php echo htmlspecialchars($testimonial['user_title']); ?></p>
                </div>
            </div>
            <p class="text-gray-700"><?php echo htmlspecialchars($testimonial['content']); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Latest jobs section -->
<section class="latest-jobs">
    <h2 class="text-3xl font-bold mb-8">Latest Orders</h2>
    <p class="text-lg mb-8">Available opportunities for our writers</p>
    
    <div class="overflow-x-auto mb-8">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="text-left py-3">Topic/Title</th>
                    <th class="text-left py-3">Deadline</th>
                    <th class="text-left py-3">Pages</th>
                    <th class="text-left py-3">Salary</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($latest_jobs as $job): ?>
                <tr class="border-t">
                    <td class="py-3"><?php echo htmlspecialchars($job['title']); ?></td>
                    <td class="py-3"><?php echo time_remaining($job['deadline']); ?></td>
                    <td class="py-3"><?php echo $job['pages']; ?></td>
                    <td class="py-3"><?php echo format_money($job['salary']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <p class="text-gray-600 mb-8">
        Current orders: <?php echo count($latest_jobs); ?> | 
        Total fees offered: <?php echo format_money($total_fees); ?>
    </p>
    
    <div class="text-center">
        <a href="jobs.php" class="btn btn-primary">View All Orders</a>
    </div>
</section>

<!-- Benefits section -->
<section class="container mx-auto px-4 mb-20">
    <h2 class="text-3xl font-bold text-center mb-12">Why Choose Us</h2>
    <p class="text-center text-lg mb-12">Benefits of working with Afrigig</p>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div class="text-center">
            <h3 class="text-xl font-bold mb-3">Competitive Salaries</h3>
            <p>Earn what you deserve with our competitive pay rates</p>
        </div>
        
        <div class="text-center">
            <h3 class="text-xl font-bold mb-3">Flexible Schedule</h3>
            <p>Work on your own terms, whenever and wherever you want</p>
        </div>
        
        <div class="text-center">
            <h3 class="text-xl font-bold mb-3">Career Growth</h3>
            <p>Opportunities for personal and professional development</p>
        </div>
        
        <div class="text-center">
            <h3 class="text-xl font-bold mb-3">Fair Policy</h3>
            <p>Transparent and fair policies for all our writers</p>
        </div>
        
        <div class="text-center">
            <h3 class="text-xl font-bold mb-3">24/7 Support</h3>
            <p>Round-the-clock assistance whenever you need it</p>
        </div>
        
        <div class="text-center">
            <h3 class="text-xl font-bold mb-3">Constant Flow</h3>
            <p>Regular stream of orders throughout the year</p>
        </div>
    </div>
</section>

<!-- Stats counter -->
<section class="bg-primary text-white py-16 mb-20">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div>
                <div class="text-4xl font-bold mb-2">195,427</div>
                <div>Professional Writers</div>
            </div>
            
            <div>
                <div class="text-4xl font-bold mb-2">2,844,076</div>
                <div>Completed Orders</div>
            </div>
            
            <div>
                <div class="text-4xl font-bold mb-2">1,247</div>
                <div>Current Online Jobs</div>
            </div>
        </div>
    </div>
</section>

<!-- Contact form -->
<section class="contact-form">
    <h2 class="text-3xl font-bold text-center mb-12">Need Assistance?</h2>
    <p class="text-center text-lg mb-12">Let us help you find the perfect solution</p>
    
    <form action="contact.php" method="POST" class="space-y-6">
        <div class="form-grid">
            <div>
                <label for="first_name" class="block mb-2">First Name</label>
                <input type="text" id="first_name" name="first_name" required class="w-full p-2 border rounded">
            </div>
            
            <div>
                <label for="last_name" class="block mb-2">Last Name</label>
                <input type="text" id="last_name" name="last_name" required class="w-full p-2 border rounded">
            </div>
        </div>
        
        <div class="form-grid">
            <div>
                <label for="email" class="block mb-2">Email</label>
                <input type="email" id="email" name="email" required class="w-full p-2 border rounded">
            </div>
            
            <div>
                <label for="phone" class="block mb-2">Phone</label>
                <input type="tel" id="phone" name="phone" class="w-full p-2 border rounded">
            </div>
        </div>
        
        <div>
            <label for="message" class="block mb-2">Request Details</label>
            <textarea id="message" name="message" rows="4" required class="w-full p-2 border rounded"></textarea>
        </div>
        
        <div class="text-center">
            <button type="submit" class="btn btn-primary">Send Request</button>
        </div>
    </form>
</section>

<?php
$content = ob_get_clean();
require_once 'views/layout.php'; 