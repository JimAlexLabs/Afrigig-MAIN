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

// Custom CSS and JS
$custom_css = ['assets/css/home.css'];
$custom_js = ['https://unpkg.com/aos@2.3.1/dist/aos.js', 'assets/js/home.js'];

// Additional styles for AOS
$additional_styles = '
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
  .form-control.error {
    border-color: var(--error-color);
  }
  
  .testimonials-grid {
    transition: transform 0.5s ease;
  }
  
  .testimonials {
    position: relative;
  }
</style>
';

// Start output buffering
ob_start();
?>

<!-- Hero section -->
<section class="hero">
    <div class="hero-content">
        <h1>Empowering African Talent</h1>
        <p>Connecting skilled African professionals with global opportunities. Join our platform to showcase your expertise and find exciting projects.</p>
        <div class="hero-buttons">
            <a href="register.php" class="btn btn-secondary">Get Started</a>
            <a href="#about" class="btn btn-outline-light">Learn More</a>
        </div>
    </div>
    <div class="scroll-indicator">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
    </div>
</section>

<!-- About section -->
<section id="about" class="about-section">
    <h2 class="section-title text-3xl font-bold">About Afrigig</h2>
    <p class="text-lg max-w-3xl mx-auto mb-16" data-aos="fade-up">
        We're on a mission to showcase African talent to the world. Our platform connects skilled professionals with opportunities that match their expertise.
    </p>
</section>

<!-- Features section -->
<section class="features">
    <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
        <i class="fas fa-users feature-icon"></i>
        <h3 class="text-xl font-bold mb-3">Community</h3>
        <p>Join our growing community of African professionals and expand your network.</p>
    </div>
    
    <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
        <i class="fas fa-briefcase feature-icon"></i>
        <h3 class="text-xl font-bold mb-3">Opportunities</h3>
        <p>Access high-quality job opportunities from reputable organizations worldwide.</p>
    </div>
    
    <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
        <i class="fas fa-chart-line feature-icon"></i>
        <h3 class="text-xl font-bold mb-3">Growth</h3>
        <p>Enhance your skills through our training programs and skill assessments.</p>
    </div>
</section>

<!-- Stats section -->
<section class="stats">
    <div class="stats-content">
        <h2 class="section-title text-3xl font-bold">Our Impact</h2>
        <p class="text-center text-lg mb-12">Making a difference in the African tech ecosystem</p>
        
        <div class="stats-grid">
            <div class="stat-item" data-aos="zoom-in" data-aos-delay="100">
                <div class="stat-number" data-target="5000">5000+</div>
                <div class="stat-label">Registered Professionals</div>
            </div>
            
            <div class="stat-item" data-aos="zoom-in" data-aos-delay="200">
                <div class="stat-number" data-target="1000">1000+</div>
                <div class="stat-label">Completed Projects</div>
            </div>
            
            <div class="stat-item" data-aos="zoom-in" data-aos-delay="300">
                <div class="stat-number" data-target="50">50+</div>
                <div class="stat-label">Countries Reached</div>
            </div>
            
            <div class="stat-item" data-aos="zoom-in" data-aos-delay="400">
                <div class="stat-number" data-target="2000000">$2M+</div>
                <div class="stat-label">Paid to Freelancers</div>
            </div>
        </div>
    </div>
</section>

<!-- Team section -->
<section class="team-section">
    <h2 class="section-title text-3xl font-bold text-center mb-12">Meet Our Team</h2>
    <p class="text-center text-lg mb-12">The passionate individuals behind Afrigig</p>
    
    <div class="team-grid">
        <div class="team-member" data-aos="fade-up" data-aos-delay="100">
            <img src="https://thispersondoesnotexist.com/" alt="CEO" class="team-photo">
            <h3 class="team-name">John Adeyemi</h3>
            <p class="team-role">CEO & Founder</p>
            <p class="team-bio">With over 15 years of experience in tech and entrepreneurship, John is passionate about creating opportunities for African talent.</p>
            <div class="team-social">
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        
        <div class="team-member" data-aos="fade-up" data-aos-delay="200">
            <img src="https://thispersondoesnotexist.com/" alt="COO" class="team-photo">
            <h3 class="team-name">Sarah Okonkwo</h3>
            <p class="team-role">Chief Operations Officer</p>
            <p class="team-bio">Sarah brings her extensive experience in operations and strategy to ensure Afrigig runs smoothly and efficiently.</p>
            <div class="team-social">
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        
        <div class="team-member" data-aos="fade-up" data-aos-delay="300">
            <img src="https://thispersondoesnotexist.com/" alt="CTO" class="team-photo">
            <h3 class="team-name">David Mensah</h3>
            <p class="team-role">Chief Technology Officer</p>
            <p class="team-bio">David leads our technical team, bringing innovative solutions to connect talent with opportunities across the continent.</p>
            <div class="team-social">
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials section -->
<section class="testimonials">
    <h2 class="section-title text-3xl font-bold text-center mb-12">What Our Users Say</h2>
    <p class="text-center text-lg mb-12">Success stories from our community</p>
    
    <div class="testimonials-grid">
        <div class="testimonial-card" data-aos="fade-up">
            <div class="testimonial-header">
                <img src="https://thispersondoesnotexist.com/" alt="Testimonial 1" class="testimonial-avatar">
                <div>
                    <h3 class="testimonial-name">Chioma Eze</h3>
                    <p class="testimonial-role">Freelance Writer</p>
                </div>
            </div>
            <p class="testimonial-content">"Afrigig has transformed my career as a writer. I've connected with clients from around the world and have been able to showcase my skills on a global platform."</p>
        </div>
        
        <div class="testimonial-card" data-aos="fade-up" data-aos-delay="100">
            <div class="testimonial-header">
                <img src="https://thispersondoesnotexist.com/" alt="Testimonial 2" class="testimonial-avatar">
                <div>
                    <h3 class="testimonial-name">Kwame Asante</h3>
                    <p class="testimonial-role">Software Developer</p>
                </div>
            </div>
            <p class="testimonial-content">"As a developer from Ghana, finding quality clients was always a challenge. Afrigig has opened doors to opportunities I never thought possible. The platform is intuitive and the support team is amazing!"</p>
        </div>
        
        <div class="testimonial-card" data-aos="fade-up" data-aos-delay="200">
            <div class="testimonial-header">
                <img src="https://thispersondoesnotexist.com/" alt="Testimonial 3" class="testimonial-avatar">
                <div>
                    <h3 class="testimonial-name">Amina Diallo</h3>
                    <p class="testimonial-role">Graphic Designer</p>
                </div>
            </div>
            <p class="testimonial-content">"I've been using Afrigig for over a year now, and it has completely changed how I work. The quality of clients and projects is exceptional, and I've grown my portfolio significantly."</p>
        </div>
    </div>
</section>

<!-- Latest jobs section -->
<section class="latest-jobs">
    <h2 class="section-title text-3xl font-bold text-center mb-12">Latest Orders</h2>
    <p class="text-center text-lg mb-12">Available opportunities for our writers</p>
    
    <div class="overflow-x-auto mb-8">
        <table class="jobs-table">
            <thead>
                <tr>
                    <th>Topic/Title</th>
                    <th>Deadline</th>
                    <th>Pages</th>
                    <th>Salary</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($latest_jobs as $job): ?>
                <tr data-aos="fade-right">
                    <td class="job-title"><?php echo htmlspecialchars($job['title']); ?></td>
                    <td class="job-deadline"><?php echo time_remaining($job['deadline']); ?></td>
                    <td><?php echo $job['pages']; ?></td>
                    <td class="job-salary"><?php echo format_money($job['salary']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <p class="text-gray-600 mb-8 text-center">
        Current orders: <?php echo count($latest_jobs); ?> | 
        Total fees offered: <?php echo format_money($total_fees); ?>
    </p>
    
    <div class="text-center">
        <a href="jobs.php" class="btn btn-primary" data-aos="zoom-in">View All Orders</a>
    </div>
</section>

<!-- Counter section -->
<section class="counter-section">
    <div class="counter-grid">
        <div class="counter-item" data-aos="fade-up">
            <div class="counter-number" data-target="195427">0</div>
            <div class="counter-label">Professional Writers</div>
        </div>
        
        <div class="counter-item" data-aos="fade-up" data-aos-delay="100">
            <div class="counter-number" data-target="2844076">0</div>
            <div class="counter-label">Completed Orders</div>
        </div>
        
        <div class="counter-item" data-aos="fade-up" data-aos-delay="200">
            <div class="counter-number" data-target="1247">0</div>
            <div class="counter-label">Current Online Jobs</div>
        </div>
    </div>
</section>

<!-- Benefits section -->
<section class="benefits">
    <h2 class="section-title text-3xl font-bold text-center mb-12">Why Choose Us</h2>
    <p class="text-center text-lg mb-12">Benefits of working with Afrigig</p>
    
    <div class="benefits-grid">
        <div class="benefit-item" data-aos="fade-up">
            <i class="fas fa-money-bill-wave benefit-icon"></i>
            <h3 class="benefit-title">Competitive Salaries</h3>
            <p class="benefit-description">Earn what you deserve with our competitive pay rates</p>
        </div>
        
        <div class="benefit-item" data-aos="fade-up" data-aos-delay="100">
            <i class="fas fa-clock benefit-icon"></i>
            <h3 class="benefit-title">Flexible Schedule</h3>
            <p class="benefit-description">Work on your own terms, whenever and wherever you want</p>
        </div>
        
        <div class="benefit-item" data-aos="fade-up" data-aos-delay="200">
            <i class="fas fa-rocket benefit-icon"></i>
            <h3 class="benefit-title">Career Growth</h3>
            <p class="benefit-description">Opportunities for personal and professional development</p>
        </div>
        
        <div class="benefit-item" data-aos="fade-up" data-aos-delay="300">
            <i class="fas fa-balance-scale benefit-icon"></i>
            <h3 class="benefit-title">Fair Policy</h3>
            <p class="benefit-description">Transparent and fair policies for all our writers</p>
        </div>
        
        <div class="benefit-item" data-aos="fade-up" data-aos-delay="400">
            <i class="fas fa-headset benefit-icon"></i>
            <h3 class="benefit-title">24/7 Support</h3>
            <p class="benefit-description">Round-the-clock assistance whenever you need it</p>
        </div>
        
        <div class="benefit-item" data-aos="fade-up" data-aos-delay="500">
            <i class="fas fa-sync-alt benefit-icon"></i>
            <h3 class="benefit-title">Constant Flow</h3>
            <p class="benefit-description">Regular stream of orders throughout the year</p>
        </div>
    </div>
</section>

<!-- Contact form -->
<section class="contact-form" data-aos="fade-up">
    <h2 class="section-title text-3xl font-bold text-center mb-12">Need Assistance?</h2>
    <p class="text-center text-lg mb-12">Let us help you find the perfect solution</p>
    
    <form action="contact.php" method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" id="first_name" name="first_name" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" id="last_name" name="last_name" required class="form-control">
            </div>
        </div>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" required class="form-control">
            </div>
            
            <div class="form-group">
                <label for="phone" class="form-label">Phone</label>
                <input type="tel" id="phone" name="phone" class="form-control">
            </div>
        </div>
        
        <div class="form-group">
            <label for="message" class="form-label">Request Details</label>
            <textarea id="message" name="message" rows="4" required class="form-control"></textarea>
        </div>
        
        <div class="text-center">
            <button type="submit" class="form-submit">Send Request</button>
        </div>
    </form>
</section>

<?php
$content = ob_get_clean();
require_once 'views/layout.php'; 