// Enhanced Scrolling JavaScript
document.addEventListener('DOMContentLoaded', function() {
    
    // Enhanced smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                    inline: 'nearest'
                });
            }
        });
    });

    // Smooth scrolling for programmatic navigation
    window.smoothScrollTo = function(element, duration = 800) {
        const targetPosition = element.offsetTop - 100; // Offset for header
        const startPosition = window.pageYOffset;
        const distance = targetPosition - startPosition;
        let startTime = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const run = ease(timeElapsed, startPosition, distance, duration);
            window.scrollTo(0, run);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }

        // Easing function for smooth animation
        function ease(t, b, c, d) {
            t /= d / 2;
            if (t < 1) return c / 2 * t * t + b;
            t--;
            return -c / 2 * (t * (t - 2) - 1) + b;
        }

        requestAnimationFrame(animation);
    };

    // Enhanced scroll performance
    let ticking = false;
    function updateScroll() {
        // Add scroll-based animations here if needed
        ticking = false;
    }

    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(updateScroll);
            ticking = true;
        }
    }

    // Optimized scroll event listener
    window.addEventListener('scroll', requestTick, { passive: true });

    // Smooth scroll to top functionality
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.innerHTML = `
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    `;
    scrollToTopBtn.className = 'scroll-to-top-btn';
    scrollToTopBtn.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border: none;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 1000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
    `;

    scrollToTopBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });

    // Show/hide scroll to top button
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            scrollToTopBtn.style.opacity = '1';
            scrollToTopBtn.style.visibility = 'visible';
        } else {
            scrollToTopBtn.style.opacity = '0';
            scrollToTopBtn.style.visibility = 'hidden';
        }
    }, { passive: true });

    document.body.appendChild(scrollToTopBtn);

    // Enhanced touch scrolling for mobile
    if ('ontouchstart' in window) {
        document.addEventListener('touchstart', function() {}, { passive: true });
        document.addEventListener('touchmove', function() {}, { passive: true });
    }

    // Prevent scroll chaining on modals and overlays
    document.querySelectorAll('.modal, .overlay, .admin-login').forEach(element => {
        element.addEventListener('touchmove', function(e) {
            e.preventDefault();
        }, { passive: false });
    });

    // Smooth scrolling for admin panel tabs
    document.querySelectorAll('.admin-nav-item').forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            const targetElement = document.getElementById(targetTab + '-tab');
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Enhanced form scrolling
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            // Smooth scroll to any error messages
            setTimeout(() => {
                const errorElement = form.querySelector('.error-message, .alert-error');
                if (errorElement) {
                    errorElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }, 100);
        });
    });

    // Performance optimization: Use Intersection Observer for scroll animations
    if ('IntersectionObserver' in window) {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for scroll animations
        document.querySelectorAll('.card, .form-container, .analytics-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    }

    console.log('Enhanced scrolling initialized');
});
