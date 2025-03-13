// Home page animations and interactivity
document.addEventListener('DOMContentLoaded', function() {
  // Initialize AOS (Animate On Scroll)
  if (typeof AOS !== 'undefined') {
    AOS.init({
      duration: 800,
      easing: 'ease-in-out',
      once: true
    });
  }

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const targetId = this.getAttribute('href');
      if (targetId === '#') return;
      
      const targetElement = document.querySelector(targetId);
      if (targetElement) {
        window.scrollTo({
          top: targetElement.offsetTop - 80,
          behavior: 'smooth'
        });
      }
    });
  });

  // Animated counters
  const counterElements = document.querySelectorAll('.counter-number');
  
  function animateCounter(el) {
    const target = parseInt(el.getAttribute('data-target'));
    const duration = 2000; // ms
    const step = target / (duration / 16); // 60fps
    let current = 0;
    
    const timer = setInterval(() => {
      current += step;
      if (current >= target) {
        el.textContent = target.toLocaleString();
        clearInterval(timer);
      } else {
        el.textContent = Math.floor(current).toLocaleString();
      }
    }, 16);
  }
  
  // Use Intersection Observer to trigger counter animation when visible
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    
    counterElements.forEach(counter => {
      observer.observe(counter);
    });
  } else {
    // Fallback for browsers that don't support Intersection Observer
    counterElements.forEach(counter => {
      animateCounter(counter);
    });
  }

  // Testimonial carousel
  const testimonialContainer = document.querySelector('.testimonials-grid');
  const testimonials = document.querySelectorAll('.testimonial-card');
  
  if (testimonialContainer && testimonials.length > 3) {
    let currentIndex = 0;
    const totalSlides = Math.ceil(testimonials.length / 3);
    
    // Create navigation dots
    const dotsContainer = document.createElement('div');
    dotsContainer.className = 'testimonial-dots';
    dotsContainer.style.display = 'flex';
    dotsContainer.style.justifyContent = 'center';
    dotsContainer.style.marginTop = '2rem';
    dotsContainer.style.gap = '0.5rem';
    
    for (let i = 0; i < totalSlides; i++) {
      const dot = document.createElement('button');
      dot.className = 'testimonial-dot';
      dot.style.width = '1rem';
      dot.style.height = '1rem';
      dot.style.borderRadius = '50%';
      dot.style.backgroundColor = i === 0 ? 'var(--primary-color)' : '#e2e8f0';
      dot.style.border = 'none';
      dot.style.cursor = 'pointer';
      dot.style.transition = 'background-color 0.3s ease';
      
      dot.addEventListener('click', () => {
        goToSlide(i);
      });
      
      dotsContainer.appendChild(dot);
    }
    
    testimonialContainer.parentNode.appendChild(dotsContainer);
    
    function goToSlide(index) {
      currentIndex = index;
      testimonialContainer.style.transform = `translateX(-${index * 100}%)`;
      
      // Update dots
      document.querySelectorAll('.testimonial-dot').forEach((dot, i) => {
        dot.style.backgroundColor = i === index ? 'var(--primary-color)' : '#e2e8f0';
      });
    }
    
    // Add navigation arrows if there are multiple slides
    if (totalSlides > 1) {
      const prevButton = document.createElement('button');
      prevButton.className = 'testimonial-nav prev';
      prevButton.innerHTML = '&larr;';
      prevButton.style.position = 'absolute';
      prevButton.style.left = '1rem';
      prevButton.style.top = '50%';
      prevButton.style.transform = 'translateY(-50%)';
      prevButton.style.backgroundColor = 'white';
      prevButton.style.color = 'var(--primary-color)';
      prevButton.style.width = '3rem';
      prevButton.style.height = '3rem';
      prevButton.style.borderRadius = '50%';
      prevButton.style.border = 'none';
      prevButton.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
      prevButton.style.cursor = 'pointer';
      prevButton.style.zIndex = '2';
      
      const nextButton = document.createElement('button');
      nextButton.className = 'testimonial-nav next';
      nextButton.innerHTML = '&rarr;';
      nextButton.style.position = 'absolute';
      nextButton.style.right = '1rem';
      nextButton.style.top = '50%';
      nextButton.style.transform = 'translateY(-50%)';
      nextButton.style.backgroundColor = 'white';
      nextButton.style.color = 'var(--primary-color)';
      nextButton.style.width = '3rem';
      nextButton.style.height = '3rem';
      nextButton.style.borderRadius = '50%';
      nextButton.style.border = 'none';
      nextButton.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
      nextButton.style.cursor = 'pointer';
      nextButton.style.zIndex = '2';
      
      prevButton.addEventListener('click', () => {
        goToSlide((currentIndex - 1 + totalSlides) % totalSlides);
      });
      
      nextButton.addEventListener('click', () => {
        goToSlide((currentIndex + 1) % totalSlides);
      });
      
      testimonialContainer.parentNode.appendChild(prevButton);
      testimonialContainer.parentNode.appendChild(nextButton);
      
      // Auto-advance slides every 5 seconds
      setInterval(() => {
        goToSlide((currentIndex + 1) % totalSlides);
      }, 5000);
    }
  }

  // Typing animation for hero section
  const heroTitle = document.querySelector('.hero h1');
  if (heroTitle) {
    const originalText = heroTitle.textContent;
    heroTitle.textContent = '';
    
    let i = 0;
    const typeInterval = setInterval(() => {
      if (i < originalText.length) {
        heroTitle.textContent += originalText.charAt(i);
        i++;
      } else {
        clearInterval(typeInterval);
      }
    }, 100);
  }

  // Parallax effect for hero section
  const hero = document.querySelector('.hero');
  if (hero) {
    window.addEventListener('scroll', () => {
      const scrollPosition = window.scrollY;
      if (scrollPosition < window.innerHeight) {
        hero.style.backgroundPositionY = `${scrollPosition * 0.5}px`;
      }
    });
  }

  // Form validation
  const contactForm = document.querySelector('.contact-form form');
  if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
      let isValid = true;
      const requiredFields = contactForm.querySelectorAll('[required]');
      
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          isValid = false;
          field.classList.add('error');
          
          // Add error message if it doesn't exist
          let errorMessage = field.parentNode.querySelector('.error-message');
          if (!errorMessage) {
            errorMessage = document.createElement('div');
            errorMessage.className = 'error-message';
            errorMessage.style.color = 'var(--error-color)';
            errorMessage.style.fontSize = '0.875rem';
            errorMessage.style.marginTop = '0.5rem';
            errorMessage.textContent = 'This field is required';
            field.parentNode.appendChild(errorMessage);
          }
        } else {
          field.classList.remove('error');
          const errorMessage = field.parentNode.querySelector('.error-message');
          if (errorMessage) {
            errorMessage.remove();
          }
        }
      });
      
      // Email validation
      const emailField = contactForm.querySelector('input[type="email"]');
      if (emailField && emailField.value.trim()) {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(emailField.value)) {
          isValid = false;
          emailField.classList.add('error');
          
          let errorMessage = emailField.parentNode.querySelector('.error-message');
          if (!errorMessage) {
            errorMessage = document.createElement('div');
            errorMessage.className = 'error-message';
            errorMessage.style.color = 'var(--error-color)';
            errorMessage.style.fontSize = '0.875rem';
            errorMessage.style.marginTop = '0.5rem';
            errorMessage.textContent = 'Please enter a valid email address';
            emailField.parentNode.appendChild(errorMessage);
          } else {
            errorMessage.textContent = 'Please enter a valid email address';
          }
        }
      }
      
      if (!isValid) {
        e.preventDefault();
      }
    });
    
    // Clear error styling on input
    contactForm.querySelectorAll('input, textarea').forEach(field => {
      field.addEventListener('input', function() {
        this.classList.remove('error');
        const errorMessage = this.parentNode.querySelector('.error-message');
        if (errorMessage) {
          errorMessage.remove();
        }
      });
    });
  }
}); 