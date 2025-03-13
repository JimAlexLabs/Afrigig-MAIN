// Theme toggling
document.addEventListener('DOMContentLoaded', () => {
    // Theme toggle
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            
            // Save theme preference
            fetch('/api/settings/theme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ theme: newTheme })
            });
        });
    }

    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    if (mobileMenuBtn && sidebar) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== mobileMenuBtn) {
            sidebar.classList.remove('open');
        }
    });

    // Flash message auto-dismiss
    const flashMessage = document.querySelector('.alert');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.opacity = '0';
            setTimeout(() => flashMessage.remove(), 300);
        }, 5000);
    }

    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Show error message
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = 'This field is required';
                    field.parentNode.appendChild(errorDiv);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });

    // Cart functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            const jobId = button.dataset.jobId;
            const bidAmount = document.querySelector(`#bid-amount-${jobId}`).value;
            const timeline = document.querySelector(`#timeline-${jobId}`).value;
            const proposal = document.querySelector(`#proposal-${jobId}`).value;
            const hideBid = document.querySelector(`#hide-bid-${jobId}`).checked;
            const featureBid = document.querySelector(`#feature-bid-${jobId}`).checked;
            
            try {
                const response = await fetch('/api/cart/add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        job_id: jobId,
                        bid_amount: bidAmount,
                        timeline: timeline,
                        proposal: proposal,
                        hide_bid: hideBid,
                        feature_bid: featureBid
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    // Update cart count
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount) {
                        cartCount.textContent = parseInt(cartCount.textContent) + 1;
                    }
                    
                    // Show success message
                    showNotification('Item added to cart successfully', 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                showNotification('Failed to add item to cart', 'error');
            }
        });
    });
});

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type} fade-in`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// M-Pesa payment handling
async function initiateMpesaPayment(phone, amount, reference, description) {
    try {
        const response = await fetch('/api/mpesa/initiate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                phone: phone,
                amount: amount,
                reference: reference,
                description: description
            })
        });
        
        const data = await response.json();
        if (data.success) {
            showNotification('Please check your phone for the M-Pesa prompt', 'info');
            
            // Start polling for payment status
            pollPaymentStatus(data.checkout_id);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        showNotification('Failed to initiate payment', 'error');
    }
}

// Poll payment status
function pollPaymentStatus(checkoutId) {
    const interval = setInterval(async () => {
        try {
            const response = await fetch(`/api/mpesa/status.php?checkout_id=${checkoutId}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.status === 'completed') {
                    clearInterval(interval);
                    showNotification('Payment successful', 'success');
                    window.location.reload();
                } else if (data.status === 'failed') {
                    clearInterval(interval);
                    showNotification('Payment failed', 'error');
                }
            }
        } catch (error) {
            clearInterval(interval);
            showNotification('Failed to check payment status', 'error');
        }
    }, 5000); // Check every 5 seconds
}

// Skill verification
function initiateSkillVerification(skillId) {
    const modal = document.getElementById('skill-verification-modal');
    const phoneInput = modal.querySelector('#phone');
    const confirmBtn = modal.querySelector('#confirm-verification');
    
    modal.style.display = 'block';
    
    confirmBtn.addEventListener('click', () => {
        const phone = phoneInput.value;
        if (phone) {
            initiateMpesaPayment(phone, 10, `skill_${skillId}`, 'Skill Verification Fee');
            modal.style.display = 'none';
        } else {
            showNotification('Please enter your phone number', 'error');
        }
    });
}

// Welcome tour
function startWelcomeTour() {
    const tour = new Shepherd.Tour({
        defaultStepOptions: {
            classes: 'shepherd-theme-default',
            scrollTo: true
        }
    });
    
    tour.addStep({
        id: 'welcome',
        text: 'Welcome to Afrigig! Let us show you around.',
        buttons: [
            {
                text: 'Next',
                action: tour.next
            }
        ]
    });
    
    tour.addStep({
        id: 'jobs',
        text: 'Here you can find all available jobs.',
        attachTo: {
            element: '.jobs-section',
            on: 'bottom'
        },
        buttons: [
            {
                text: 'Previous',
                action: tour.back
            },
            {
                text: 'Next',
                action: tour.next
            }
        ]
    });
    
    // Add more tour steps...
    
    tour.start();
} 