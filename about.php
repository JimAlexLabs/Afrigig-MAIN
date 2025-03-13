<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'About Us';

ob_start();
?>

<div class="container mx-auto px-4 py-12">
    <!-- Hero Section -->
    <div class="text-center mb-16">
        <h1 class="text-4xl font-bold mb-4">About Afrigig</h1>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Empowering African talent through technology and connecting them with global opportunities.
        </p>
    </div>
    
    <!-- Mission & Vision -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mb-16">
        <div class="bg-white rounded-lg shadow p-8">
            <h2 class="text-2xl font-bold mb-4">Our Mission</h2>
            <p class="text-gray-600">
                To create a platform that showcases African talent to the world, providing opportunities for skilled professionals 
                to connect with clients globally while fostering economic growth and development across the continent.
            </p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-8">
            <h2 class="text-2xl font-bold mb-4">Our Vision</h2>
            <p class="text-gray-600">
                To become the leading platform for African talent, breaking down geographical barriers and creating 
                sustainable economic opportunities for millions of skilled professionals across Africa.
            </p>
        </div>
    </div>
    
    <!-- Values -->
    <div class="mb-16">
        <h2 class="text-3xl font-bold text-center mb-12">Our Values</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-2">Community</h3>
                <p class="text-gray-600">Building strong connections and fostering collaboration among professionals.</p>
            </div>
            
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-2">Trust</h3>
                <p class="text-gray-600">Building reliable and secure relationships between clients and professionals.</p>
            </div>
            
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-2">Innovation</h3>
                <p class="text-gray-600">Continuously improving our platform to better serve our community.</p>
            </div>
        </div>
    </div>
    
    <!-- Team -->
    <div class="mb-16">
        <h2 class="text-3xl font-bold text-center mb-12">Our Team</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <img src="assets/images/team/ceo.jpg" alt="CEO" class="w-32 h-32 rounded-full mx-auto mb-4 object-cover">
                <h3 class="text-xl font-bold mb-1">John Doe</h3>
                <p class="text-gray-600 mb-2">CEO & Founder</p>
                <p class="text-gray-500">Visionary leader with 15+ years of experience in tech.</p>
            </div>
            
            <div class="text-center">
                <img src="assets/images/team/cto.jpg" alt="CTO" class="w-32 h-32 rounded-full mx-auto mb-4 object-cover">
                <h3 class="text-xl font-bold mb-1">Jane Smith</h3>
                <p class="text-gray-600 mb-2">CTO</p>
                <p class="text-gray-500">Tech expert with a passion for African innovation.</p>
            </div>
            
            <div class="text-center">
                <img src="assets/images/team/coo.jpg" alt="COO" class="w-32 h-32 rounded-full mx-auto mb-4 object-cover">
                <h3 class="text-xl font-bold mb-1">Mike Johnson</h3>
                <p class="text-gray-600 mb-2">COO</p>
                <p class="text-gray-500">Operations specialist focused on community growth.</p>
            </div>
        </div>
    </div>
    
    <!-- Contact -->
    <div class="text-center">
        <h2 class="text-3xl font-bold mb-4">Get in Touch</h2>
        <p class="text-xl text-gray-600 mb-8">Have questions? We'd love to hear from you.</p>
        <a href="contact.php" class="btn btn-primary">Contact Us</a>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?> 