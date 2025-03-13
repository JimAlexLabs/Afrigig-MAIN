// Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard JS loaded');
    
    // Initialize components
    initializeNotifications();
    initializeJobFilters();
    initializeJobFiltersForFindJobs();
    console.log('Initializing AI Skill Assessment...');
    initializeAISkillAssessment();
    console.log('AI Skill Assessment initialized');
    loadSampleJobs();
    
    // Show welcome notification
    showNotification('Welcome to Afrigig!', 'Find the best freelance jobs in Africa.', 'success');
    
    // Show job alert notification after 5 seconds
    setTimeout(() => {
        showNotification('New Job Alert!', '5 new jobs match your skills.', 'info');
    }, 5000);
    
    // Show deadline notification after 10 seconds
    setTimeout(() => {
        showNotification('Deadline Approaching!', 'You have a job due in 2 days.', 'warning');
    }, 10000);
    
    // Check if we need to open the assessment modal from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const openAssessment = urlParams.get('open_assessment');
    if (openAssessment) {
        console.log('Found open_assessment parameter:', openAssessment);
        openSkillAssessment(openAssessment);
    }
});

// Notification System
function initializeNotifications() {
    // Create notification center if it doesn't exist
    if (!document.querySelector('.notification-center')) {
        const notificationCenter = document.createElement('div');
        notificationCenter.className = 'notification-center';
        document.body.appendChild(notificationCenter);
    }
    
    // Add notification badge to messages link
    const messagesLink = document.querySelector('a[href="messages.php"]');
    if (messagesLink) {
        messagesLink.style.position = 'relative';
        const badge = document.createElement('span');
        badge.className = 'notification-badge';
        badge.textContent = '3';
        messagesLink.appendChild(badge);
    }
}

function showNotification(title, message, type = 'info') {
    const notificationCenter = document.querySelector('.notification-center');
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    let iconClass = 'fa-info-circle';
    if (type === 'success') iconClass = 'fa-check-circle';
    if (type === 'warning') iconClass = 'fa-exclamation-triangle';
    if (type === 'error') iconClass = 'fa-times-circle';
    
    notification.innerHTML = `
        <i class="notification-icon fas ${iconClass}"></i>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close">&times;</button>
    `;
    
    notificationCenter.appendChild(notification);
    
    // Add event listener to close button
    notification.querySelector('.notification-close').addEventListener('click', function() {
        notification.remove();
    });
    
    // Auto-remove notification after 5 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Job Filtering System
function initializeJobFilters() {
    const jobsContainer = document.querySelector('.bg-white.rounded-lg.shadow.p-6:last-child');
    if (!jobsContainer) return;
    
    // Create filter section
    const filterSection = document.createElement('div');
    filterSection.className = 'filter-section';
    filterSection.innerHTML = `
        <h3 class="text-lg font-semibold mb-4">Filter Jobs</h3>
        <form class="filter-form" id="job-filter-form">
            <div>
                <input type="text" id="keyword-filter" placeholder="Search by keyword">
            </div>
            <div>
                <select id="category-filter">
                    <option value="">All Categories</option>
                    <option value="writing">Academic Writing</option>
                    <option value="programming">Programming</option>
                    <option value="design">Design</option>
                    <option value="marketing">Marketing</option>
                    <option value="legal">Legal</option>
                </select>
            </div>
            <div>
                <select id="salary-filter">
                    <option value="">Any Salary</option>
                    <option value="0-50">$0 - $50</option>
                    <option value="50-100">$50 - $100</option>
                    <option value="100-200">$100 - $200</option>
                    <option value="200+">$200+</option>
                </select>
            </div>
            <div>
                <select id="deadline-filter">
                    <option value="">Any Deadline</option>
                    <option value="1">Within 24 hours</option>
                    <option value="3">Within 3 days</option>
                    <option value="7">Within 7 days</option>
                    <option value="14">Within 14 days</option>
                </select>
            </div>
            <div>
                <button type="submit" id="apply-filters">Apply Filters</button>
            </div>
        </form>
    `;
    
    // Insert filter section before the jobs table
    const jobsHeader = jobsContainer.querySelector('.flex.justify-between.items-center.mb-6');
    jobsContainer.insertBefore(filterSection, jobsHeader.nextSibling);
    
    // Add event listener to filter form
    document.getElementById('job-filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        filterJobs();
    });
}

function filterJobs() {
    const keyword = document.getElementById('keyword-filter').value.toLowerCase();
    const category = document.getElementById('category-filter').value;
    const salary = document.getElementById('salary-filter').value;
    const deadline = document.getElementById('deadline-filter').value;
    
    const jobRows = document.querySelectorAll('.jobs-table tbody tr');
    
    jobRows.forEach(row => {
        const title = row.querySelector('td:first-child a').textContent.toLowerCase();
        const salaryValue = parseFloat(row.querySelector('td:nth-child(3)').textContent.replace('$', '').replace(',', ''));
        const deadlineText = row.querySelector('td:nth-child(4)').getAttribute('data-deadline') || '';
        const categoryValue = row.getAttribute('data-category') || '';
        
        let showRow = true;
        
        // Filter by keyword
        if (keyword && !title.includes(keyword)) {
            showRow = false;
        }
        
        // Filter by category
        if (category && categoryValue !== category) {
            showRow = false;
        }
        
        // Filter by salary
        if (salary) {
            const [min, max] = salary.split('-');
            if (max === '+') {
                if (salaryValue < parseFloat(min)) showRow = false;
            } else if (salaryValue < parseFloat(min) || salaryValue > parseFloat(max)) {
                showRow = false;
            }
        }
        
        // Filter by deadline
        if (deadline) {
            const deadlineDays = parseInt(deadline);
            const deadlineDate = new Date(deadlineText);
            const daysUntilDeadline = Math.ceil((deadlineDate - new Date()) / (1000 * 60 * 60 * 24));
            
            if (daysUntilDeadline > deadlineDays) {
                showRow = false;
            }
        }
        
        row.style.display = showRow ? '' : 'none';
    });
    
    // Show notification about filter results
    const visibleRows = document.querySelectorAll('.jobs-table tbody tr:not([style*="display: none"])');
    showNotification('Filter Applied', `Found ${visibleRows.length} matching jobs.`, 'info');
}

// Job Filtering System for find-jobs.php
function initializeJobFiltersForFindJobs() {
    const jobFilterForm = document.getElementById('job-filter-form');
    if (!jobFilterForm) return;
    
    jobFilterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        filterJobsOnFindJobsPage();
    });
}

function filterJobsOnFindJobsPage() {
    const keyword = document.getElementById('keyword-filter').value.toLowerCase();
    const category = document.getElementById('category-filter').value;
    const salary = document.getElementById('salary-filter').value;
    const deadline = document.getElementById('deadline-filter').value;
    
    console.log('Filtering with:', { keyword, category, salary, deadline });
    
    const jobCards = document.querySelectorAll('.grid.grid-cols-1.gap-6 > div');
    let visibleCount = 0;
    
    jobCards.forEach(card => {
        const title = card.querySelector('h2').textContent.toLowerCase();
        const description = card.querySelector('p').textContent.toLowerCase();
        const salaryText = card.querySelector('.flex.gap-4.text-sm.text-gray-500').textContent;
        const salaryMatch = salaryText.match(/Budget: \$([0-9,.]+)/);
        const salaryValue = salaryMatch ? parseFloat(salaryMatch[1].replace(',', '')) : 0;
        const dateText = card.querySelector('.flex.gap-4.text-sm.text-gray-500').textContent;
        const categoryValue = card.getAttribute('data-category') || '';
        
        console.log('Card data:', { 
            title: title.substring(0, 30) + '...', 
            category: categoryValue,
            salary: salaryValue,
            dateText: dateText.substring(0, 30) + '...'
        });
        
        let showCard = true;
        
        // Filter by keyword
        if (keyword && !title.includes(keyword) && !description.includes(keyword)) {
            showCard = false;
            console.log('Hiding by keyword');
        }
        
        // Filter by category
        if (category && categoryValue !== category) {
            showCard = false;
            console.log('Hiding by category', categoryValue, 'vs', category);
        }
        
        // Filter by salary
        if (salary) {
            const [min, max] = salary.split('-');
            if (max === '+') {
                if (salaryValue < parseFloat(min)) {
                    showCard = false;
                    console.log('Hiding by salary (min)', salaryValue, '<', min);
                }
            } else if (salaryValue < parseFloat(min) || salaryValue > parseFloat(max)) {
                showCard = false;
                console.log('Hiding by salary (range)', min, '-', max, 'vs', salaryValue);
            }
        }
        
        // Filter by deadline
        if (deadline) {
            const monthMatch = dateText.match(/Posted: ([A-Za-z]+)/);
            if (monthMatch) {
                const month = monthMatch[1].toLowerCase();
                if (deadline === 'march' && month !== 'mar') {
                    showCard = false;
                    console.log('Hiding by deadline (march)');
                }
                if (deadline === 'april' && month !== 'apr') {
                    showCard = false;
                    console.log('Hiding by deadline (april)');
                }
                if (deadline === 'may' && month !== 'may') {
                    showCard = false;
                    console.log('Hiding by deadline (may)');
                }
                if (deadline === 'june' && month !== 'jun') {
                    showCard = false;
                    console.log('Hiding by deadline (june)');
                }
            }
        }
        
        card.style.display = showCard ? '' : 'none';
        if (showCard) visibleCount++;
    });
    
    // Show notification about filter results
    showNotification('Filter Applied', `Found ${visibleCount} matching jobs.`, 'info');
}

// AI Skill Assessment
function initializeAISkillAssessment() {
    console.log('Setting up AI Skill Assessment...');
    
    // Get the existing modal
    const modalOverlay = document.getElementById('skill-assessment-modal');
    if (!modalOverlay) {
        console.error('Skill assessment modal not found in the DOM!');
        return;
    }
    
    console.log('Found skill assessment modal');
    
    // Add event listeners to navigation buttons
    const prevBtn = document.getElementById('prev-question');
    const nextBtn = document.getElementById('next-question');
    
    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => navigateQuestion(-1));
        nextBtn.addEventListener('click', () => navigateQuestion(1));
        console.log('Added event listeners to navigation buttons');
    }
    
    // Add event listeners to place bid buttons
    document.querySelectorAll('.place-bid-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const jobId = this.getAttribute('data-job-id');
            openSkillAssessment(jobId);
        });
    });
    
    console.log('AI Skill Assessment setup complete');
}

// Close assessment modal function (global for onclick access)
window.closeAssessmentModal = function() {
    const modal = document.getElementById('skill-assessment-modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

// Make openSkillAssessment globally accessible
window.openSkillAssessment = function(jobId) {
    console.log('Opening skill assessment for job ID:', jobId);
    const modal = document.getElementById('skill-assessment-modal');
    if (!modal) {
        console.error('Skill assessment modal not found!');
        alert('Error: Assessment modal not found. Please refresh the page and try again.');
        return;
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
    
    // Reset assessment
    currentQuestion = 0;
    userAnswers = [];
    
    // Load questions
    loadAssessmentQuestions(jobId);
    
    // Update progress bar
    updateProgressBar();
    
    // Log that the modal should be visible now
    console.log('Modal should be visible now');
}

let currentQuestion = 0;
let assessmentQuestions = [];
let userAnswers = [];

function loadAssessmentQuestions(jobId) {
    // In a real app, these would be loaded from the server based on the job
    assessmentQuestions = [
        {
            question: "What is the primary purpose of a CSS preprocessor?",
            options: [
                "To compress CSS files for faster loading",
                "To add programming features like variables and functions to CSS",
                "To convert HTML to CSS automatically",
                "To validate CSS syntax"
            ],
            correctAnswer: 1
        },
        {
            question: "Which of the following is NOT a JavaScript framework or library?",
            options: [
                "React",
                "Angular",
                "Vue",
                "Cascade"
            ],
            correctAnswer: 3
        },
        {
            question: "What does API stand for?",
            options: [
                "Application Programming Interface",
                "Automated Program Integration",
                "Application Process Integration",
                "Advanced Programming Interface"
            ],
            correctAnswer: 0
        },
        {
            question: "Which database type uses collections of documents instead of tables with rows?",
            options: [
                "SQL database",
                "NoSQL database",
                "Graph database",
                "Hierarchical database"
            ],
            correctAnswer: 1
        },
        {
            question: "What is the purpose of version control systems like Git?",
            options: [
                "To optimize code execution speed",
                "To automatically fix bugs in code",
                "To track changes to files and coordinate work among multiple people",
                "To convert code between different programming languages"
            ],
            correctAnswer: 2
        }
    ];
    
    displayQuestion(currentQuestion);
}

function displayQuestion(index) {
    const questionContainer = document.getElementById('assessment-questions');
    const question = assessmentQuestions[index];
    
    questionContainer.innerHTML = `
        <div class="question">
            <div class="question-text">${index + 1}. ${question.question}</div>
            <div class="options">
                ${question.options.map((option, i) => `
                    <label class="option ${userAnswers[index] === i ? 'selected' : ''}">
                        <input type="radio" name="q${index}" value="${i}" ${userAnswers[index] === i ? 'checked' : ''}>
                        ${option}
                    </label>
                `).join('')}
            </div>
        </div>
    `;
    
    // Add event listeners to options
    questionContainer.querySelectorAll('.option').forEach((option, i) => {
        option.addEventListener('click', () => {
            userAnswers[index] = i;
            questionContainer.querySelectorAll('.option').forEach(opt => opt.classList.remove('selected'));
            option.classList.add('selected');
            option.querySelector('input').checked = true;
        });
    });
    
    // Update button states
    document.getElementById('prev-question').disabled = index === 0;
    
    const nextBtn = document.getElementById('next-question');
    if (index === assessmentQuestions.length - 1) {
        nextBtn.textContent = 'Submit';
    } else {
        nextBtn.textContent = 'Next';
    }
}

function navigateQuestion(direction) {
    const nextIndex = currentQuestion + direction;
    
    // Check if we're at the end of the assessment
    if (nextIndex >= assessmentQuestions.length) {
        submitAssessment();
        return;
    }
    
    // Navigate to the next/previous question
    if (nextIndex >= 0 && nextIndex < assessmentQuestions.length) {
        currentQuestion = nextIndex;
        displayQuestion(currentQuestion);
        updateProgressBar();
    }
}

function updateProgressBar() {
    const progressBar = document.querySelector('.progress-bar');
    const progress = ((currentQuestion + 1) / assessmentQuestions.length) * 100;
    progressBar.style.width = `${progress}%`;
}

function submitAssessment() {
    // Calculate score
    let correctAnswers = 0;
    userAnswers.forEach((answer, index) => {
        if (answer === assessmentQuestions[index].correctAnswer) {
            correctAnswers++;
        }
    });
    
    const score = Math.round((correctAnswers / assessmentQuestions.length) * 100);
    
    // Display results
    const questionContainer = document.getElementById('assessment-questions');
    questionContainer.innerHTML = `
        <div class="text-center">
            <h3 class="text-xl font-bold mb-4">Assessment Complete!</h3>
            <p class="text-lg mb-2">Your Score: <span class="font-bold text-primary">${score}%</span></p>
            <p class="mb-4">${score >= 70 ? 'Congratulations! You passed the assessment.' : 'You did not pass the assessment. Please try again later.'}</p>
            ${score >= 70 ? '<p class="text-success font-semibold">You can now proceed with your bid.</p>' : ''}
        </div>
    `;
    
    // Update footer buttons
    document.getElementById('prev-question').style.display = 'none';
    const nextBtn = document.getElementById('next-question');
    nextBtn.textContent = 'Continue';
    nextBtn.addEventListener('click', () => {
        closeAssessmentModal();
        
        if (score >= 70) {
            // In a real app, this would redirect to the actual bid page
            showNotification('Assessment Passed', 'You can now place your bid.', 'success');
        } else {
            showNotification('Assessment Failed', 'Please improve your skills and try again.', 'error');
        }
    }, { once: true });
}

// Sample Jobs Data
function loadSampleJobs() {
    const jobsTable = document.querySelector('.jobs-table tbody');
    if (!jobsTable) return;
    
    // Clear existing jobs
    jobsTable.innerHTML = '';
    
    // Sample jobs data
    const jobs = [
        {
            id: 1,
            title: 'Investment Management Research Paper',
            status: 'open',
            category: 'writing',
            salary: 150.00,
            deadline: '2025-03-21T18:00:00',
            pages: 3,
            description: 'Need a detailed research paper on modern investment management strategies.'
        },
        {
            id: 2,
            title: 'E-commerce Website Development',
            status: 'open',
            category: 'programming',
            salary: 1200.00,
            deadline: '2025-03-30T08:00:00',
            pages: 10,
            description: 'Looking for a skilled web developer to create a responsive e-commerce website with payment integration.'
        },
        {
            id: 3,
            title: 'Legal Issues in Psychology',
            status: 'open',
            category: 'legal',
            salary: 85.00,
            deadline: '2025-03-24T02:00:00',
            pages: 5,
            description: 'Research paper on legal and ethical issues in psychological practice.'
        },
        {
            id: 4,
            title: 'Mobile App UI/UX Design',
            status: 'open',
            category: 'design',
            salary: 750.00,
            deadline: '2025-03-28T12:00:00',
            pages: 8,
            description: 'Design a modern and intuitive user interface for a fitness tracking mobile application.'
        },
        {
            id: 5,
            title: 'Restaurant Review Analysis',
            status: 'open',
            category: 'writing',
            salary: 65.00,
            deadline: '2025-03-16T00:00:00',
            pages: 2,
            description: 'Analyze customer reviews for a chain of restaurants and provide actionable insights.'
        },
        {
            id: 6,
            title: 'Python Data Analysis Project',
            status: 'open',
            category: 'programming',
            salary: 350.00,
            deadline: '2025-03-22T00:00:00',
            pages: 5,
            description: 'Analyze a large dataset using Python and create visualizations with insights.'
        },
        {
            id: 7,
            title: 'Logo Design for Tech Startup',
            status: 'open',
            category: 'design',
            salary: 200.00,
            deadline: '2025-03-18T00:00:00',
            pages: 1,
            description: 'Create a modern, minimalist logo for a new AI-focused tech startup.'
        },
        {
            id: 8,
            title: 'Social Media Marketing Strategy',
            status: 'open',
            category: 'marketing',
            salary: 450.00,
            deadline: '2025-03-25T00:00:00',
            pages: 4,
            description: 'Develop a comprehensive social media marketing strategy for a small business.'
        },
        {
            id: 9,
            title: 'WordPress Website Customization',
            status: 'open',
            category: 'programming',
            salary: 300.00,
            deadline: '2025-03-19T00:00:00',
            pages: 3,
            description: 'Customize an existing WordPress website with new features and improved design.'
        },
        {
            id: 10,
            title: 'Content Writing for Blog',
            status: 'open',
            category: 'writing',
            salary: 120.00,
            deadline: '2025-03-17T00:00:00',
            pages: 3,
            description: 'Write 5 SEO-optimized blog posts for a health and wellness website.'
        },
        {
            id: 11,
            title: 'Financial Analysis Report',
            status: 'open',
            category: 'finance',
            salary: 180.00,
            deadline: '2025-03-23T00:00:00',
            pages: 4,
            description: 'Create a detailed financial analysis report for a small manufacturing company.'
        },
        {
            id: 12,
            title: 'React Native Mobile App',
            status: 'open',
            category: 'programming',
            salary: 1500.00,
            deadline: '2025-04-05T00:00:00',
            pages: 12,
            description: 'Develop a cross-platform mobile app using React Native for a delivery service.'
        },
        {
            id: 13,
            title: 'Academic Research on Climate Change',
            status: 'open',
            category: 'writing',
            salary: 95.00,
            deadline: '2025-03-20T00:00:00',
            pages: 6,
            description: 'Research paper on the economic impacts of climate change in Sub-Saharan Africa.'
        },
        {
            id: 14,
            title: 'Video Editing for YouTube Channel',
            status: 'open',
            category: 'multimedia',
            salary: 250.00,
            deadline: '2025-03-18T00:00:00',
            pages: 2,
            description: 'Edit 5 videos for a growing YouTube channel focused on technology reviews.'
        },
        {
            id: 15,
            title: 'Database Optimization Project',
            status: 'open',
            category: 'programming',
            salary: 600.00,
            deadline: '2025-03-26T00:00:00',
            pages: 7,
            description: 'Optimize MySQL database performance for a high-traffic e-commerce website.'
        },
        // New jobs with June deadlines
        {
            id: 16,
            title: 'AI-Powered Chatbot Development',
            status: 'open',
            category: 'programming',
            salary: 2200.00,
            deadline: '2025-06-15T00:00:00',
            pages: 15,
            description: 'Develop an AI-powered chatbot for customer service using natural language processing.'
        },
        {
            id: 17,
            title: 'Summer Marketing Campaign Strategy',
            status: 'open',
            category: 'marketing',
            salary: 850.00,
            deadline: '2025-06-01T00:00:00',
            pages: 8,
            description: 'Create a comprehensive summer marketing campaign for a beach resort.'
        },
        {
            id: 18,
            title: 'Mobile Game Development',
            status: 'open',
            category: 'programming',
            salary: 3500.00,
            deadline: '2025-06-30T00:00:00',
            pages: 20,
            description: 'Develop a casual mobile game with in-app purchases and social features.'
        },
        {
            id: 19,
            title: 'Corporate Brand Identity Redesign',
            status: 'open',
            category: 'design',
            salary: 1800.00,
            deadline: '2025-06-10T00:00:00',
            pages: 12,
            description: 'Redesign the complete brand identity for a financial services company.'
        },
        {
            id: 20,
            title: 'E-learning Platform Development',
            status: 'open',
            category: 'programming',
            salary: 4000.00,
            deadline: '2025-06-25T00:00:00',
            pages: 25,
            description: 'Build a complete e-learning platform with course management, user authentication, and payment processing.'
        },
        {
            id: 21,
            title: 'Cryptocurrency Market Analysis',
            status: 'open',
            category: 'finance',
            salary: 550.00,
            deadline: '2025-06-05T00:00:00',
            pages: 7,
            description: 'Analyze current cryptocurrency market trends and provide investment recommendations.'
        },
        {
            id: 22,
            title: 'Product Packaging Design',
            status: 'open',
            category: 'design',
            salary: 650.00,
            deadline: '2025-06-12T00:00:00',
            pages: 5,
            description: 'Design attractive and eco-friendly packaging for a new organic food product line.'
        },
        {
            id: 23,
            title: 'SEO Optimization Project',
            status: 'open',
            category: 'marketing',
            salary: 750.00,
            deadline: '2025-06-08T00:00:00',
            pages: 6,
            description: 'Improve search engine rankings for an e-commerce website through comprehensive SEO optimization.'
        },
        {
            id: 24,
            title: 'Virtual Reality Experience Design',
            status: 'open',
            category: 'multimedia',
            salary: 2800.00,
            deadline: '2025-06-20T00:00:00',
            pages: 18,
            description: 'Design an immersive virtual reality experience for a museum exhibition.'
        },
        {
            id: 25,
            title: 'Blockchain Smart Contract Development',
            status: 'open',
            category: 'programming',
            salary: 3200.00,
            deadline: '2025-06-18T00:00:00',
            pages: 15,
            description: 'Develop secure smart contracts for a decentralized finance application.'
        },
        {
            id: 26,
            title: 'Corporate Training Manual',
            status: 'open',
            category: 'writing',
            salary: 420.00,
            deadline: '2025-06-07T00:00:00',
            pages: 12,
            description: 'Create a comprehensive training manual for new employees in a retail environment.'
        },
        {
            id: 27,
            title: 'Podcast Production and Editing',
            status: 'open',
            category: 'multimedia',
            salary: 380.00,
            deadline: '2025-06-14T00:00:00',
            pages: 4,
            description: 'Produce and edit a 10-episode podcast series on entrepreneurship.'
        },
        {
            id: 28,
            title: 'Data Migration Project',
            status: 'open',
            category: 'programming',
            salary: 1700.00,
            deadline: '2025-06-22T00:00:00',
            pages: 10,
            description: 'Migrate data from legacy systems to a new cloud-based platform with data validation and cleaning.'
        },
        {
            id: 29,
            title: 'Legal Contract Template Creation',
            status: 'open',
            category: 'legal',
            salary: 950.00,
            deadline: '2025-06-03T00:00:00',
            pages: 8,
            description: 'Create standardized legal contract templates for a small business.'
        },
        {
            id: 30,
            title: 'Animated Explainer Video',
            status: 'open',
            category: 'multimedia',
            salary: 1200.00,
            deadline: '2025-06-28T00:00:00',
            pages: 3,
            description: 'Create a 2-minute animated explainer video for a new software product.'
        }
    ];
    
    // Add jobs to table
    jobs.forEach(job => {
        const deadlineDate = new Date(job.deadline);
        const now = new Date();
        const diffTime = Math.abs(deadlineDate - now);
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
        const diffHours = Math.floor((diffTime % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        
        const row = document.createElement('tr');
        row.className = 'border-b';
        row.setAttribute('data-category', job.category);
        
        row.innerHTML = `
            <td class="py-3">
                <a href="view-job.php?id=${job.id}" class="text-primary hover:text-secondary">
                    ${job.title}
                </a>
            </td>
            <td class="py-3">
                <span class="status-badge ${job.status}">
                    ${job.status.charAt(0).toUpperCase() + job.status.slice(1)}
                </span>
            </td>
            <td class="py-3">$${job.salary.toFixed(2)}</td>
            <td class="py-3" data-deadline="${job.deadline}">${diffDays}d ${diffHours}h</td>
            <td class="py-3">
                <a href="javascript:void(0)" data-job-id="${job.id}" class="text-primary hover:text-secondary place-bid-btn">Place Bid</a>
            </td>
        `;
        
        jobsTable.appendChild(row);
    });
    
    // Update stats
    const availableJobsCount = document.querySelector('.stats-card:nth-child(1) p');
    if (availableJobsCount) availableJobsCount.textContent = jobs.length;
    
    const recentJobsCount = document.querySelector('.stats-card:nth-child(2) p');
    if (recentJobsCount) {
        const recentJobs = jobs.filter(job => {
            const jobDate = new Date(job.deadline);
            const sevenDaysAgo = new Date();
            sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
            return jobDate > sevenDaysAgo;
        });
        recentJobsCount.textContent = recentJobs.length;
    }
    
    const highPayingJobsCount = document.querySelector('.stats-card:nth-child(3) p');
    if (highPayingJobsCount) {
        const highPayingJobs = jobs.filter(job => job.salary > 100);
        highPayingJobsCount.textContent = highPayingJobs.length;
    }
    
    // Add event listeners to place bid buttons
    document.querySelectorAll('.place-bid-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const jobId = this.getAttribute('data-job-id');
            openSkillAssessment(jobId);
        });
    });
} 