<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'About Us';

// Custom CSS and JS
$custom_css = ['assets/css/about.css'];
$custom_js = ['https://unpkg.com/aos@2.3.1/dist/aos.js'];

// Additional styles for AOS
$additional_styles = '
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
';

ob_start();
?>

<!-- Hero Section -->
<section class="about-hero">
    <div class="about-hero-content">
        <h1>About Afrigig</h1>
        <p>Empowering African talent through technology and connecting them with global opportunities.</p>
    </div>
</section>

<!-- Mission & Vision -->
<div class="mission-vision">
    <div class="mission-card" data-aos="fade-up" data-aos-delay="100">
        <h2>Our Mission</h2>
        <p>
            To create a platform that showcases African talent to the world, providing opportunities for skilled professionals 
            to connect with clients globally while fostering economic growth and development across the continent.
        </p>
    </div>
    
    <div class="vision-card" data-aos="fade-up" data-aos-delay="200">
        <h2>Our Vision</h2>
        <p>
            To become the leading platform for African talent, breaking down geographical barriers and creating 
            sustainable economic opportunities for millions of skilled professionals across Africa.
        </p>
    </div>
</div>

<!-- Values -->
<section class="values-section">
    <div class="values-content">
        <h2 class="section-title text-3xl font-bold text-center">Our Values</h2>
        
        <div class="values-grid">
            <div class="value-item" data-aos="fade-up" data-aos-delay="100">
                <div class="value-icon-container">
                    <i class="fas fa-users value-icon"></i>
                </div>
                <h3 class="value-title">Community</h3>
                <p class="value-description">Building strong connections and fostering collaboration among professionals.</p>
            </div>
            
            <div class="value-item" data-aos="fade-up" data-aos-delay="200">
                <div class="value-icon-container">
                    <i class="fas fa-shield-alt value-icon"></i>
                </div>
                <h3 class="value-title">Trust</h3>
                <p class="value-description">Building reliable and secure relationships between clients and professionals.</p>
            </div>
            
            <div class="value-item" data-aos="fade-up" data-aos-delay="300">
                <div class="value-icon-container">
                    <i class="fas fa-lightbulb value-icon"></i>
                </div>
                <h3 class="value-title">Innovation</h3>
                <p class="value-description">Continuously improving our platform to better serve our community.</p>
            </div>
            
            <div class="value-item" data-aos="fade-up" data-aos-delay="400">
                <div class="value-icon-container">
                    <i class="fas fa-globe-africa value-icon"></i>
                </div>
                <h3 class="value-title">African Excellence</h3>
                <p class="value-description">Showcasing the best of African talent and expertise to the world.</p>
            </div>
        </div>
    </div>
</section>

<!-- Team -->
<section class="team-section">
    <h2 class="section-title text-3xl font-bold text-center">Our Team</h2>
    
    <div class="team-grid">
        <div class="team-member" data-aos="fade-up" data-aos-delay="100">
            <img src="https://thispersondoesnotexist.com/" alt="CEO" class="team-photo">
            <h3 class="team-name">John Adeyemi</h3>
            <p class="team-role">CEO & Founder</p>
            <p class="team-bio">Visionary leader with 15+ years of experience in tech and a passion for African innovation.</p>
            <div class="team-social">
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        
        <div class="team-member" data-aos="fade-up" data-aos-delay="200">
            <img src="https://thispersondoesnotexist.com/" alt="CTO" class="team-photo">
            <h3 class="team-name">David Mensah</h3>
            <p class="team-role">Chief Technology Officer</p>
            <p class="team-bio">Tech expert with a passion for creating innovative solutions for African businesses.</p>
            <div class="team-social">
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        
        <div class="team-member" data-aos="fade-up" data-aos-delay="300">
            <img src="https://thispersondoesnotexist.com/" alt="COO" class="team-photo">
            <h3 class="team-name">Sarah Okonkwo</h3>
            <p class="team-role">Chief Operations Officer</p>
            <p class="team-bio">Operations specialist focused on community growth and client satisfaction.</p>
            <div class="team-social">
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- Contact -->
<section class="contact-section">
    <div class="contact-content">
        <h2 class="contact-title">Get in Touch</h2>
        <p class="contact-description">Have questions? We'd love to hear from you.</p>
        <a href="contact.php" class="contact-button">Contact Us</a>
    </div>
</section>

<script>
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