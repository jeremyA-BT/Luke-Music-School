// Mobile Navigation Handler
class MobileNavigation {
    constructor() {
        this.initMobileNav();
        this.initViewportFix();
    }

    initMobileNav() {
        // Mobile nav toggle for landing page
        const landingToggle = document.querySelector('.landing-page .mobile-nav-toggle');
        const landingNav = document.querySelector('.landing-page .main-nav');

        if (landingToggle && landingNav) {
            landingToggle.addEventListener('click', () => {
                const isActive = landingNav.classList.contains('active');
                landingNav.classList.toggle('active');
                this.updateToggleIcon(landingToggle, !isActive);
                this.toggleBodyScroll(!isActive);
            });

            // Close nav when clicking on links
            const landingLinks = landingNav.querySelectorAll('a');
            landingLinks.forEach(link => {
                link.addEventListener('click', () => {
                    landingNav.classList.remove('active');
                    this.updateToggleIcon(landingToggle, false);
                    this.toggleBodyScroll(false);
                });
            });
        }

        // Mobile nav toggle for other pages
        const pageToggle = document.querySelector('.page-header .mobile-nav-toggle');
        const pageNavLinks = document.querySelector('.page-header .nav-links');

        if (pageToggle && pageNavLinks) {
            pageToggle.addEventListener('click', () => {
                const isActive = pageNavLinks.classList.contains('active');
                pageNavLinks.classList.toggle('active');
                this.updateToggleIcon(pageToggle, !isActive);
                this.toggleBodyScroll(!isActive);
            });

            // Close nav when clicking on links
            const pageLinks = pageNavLinks.querySelectorAll('a');
            pageLinks.forEach(link => {
                link.addEventListener('click', () => {
                    pageNavLinks.classList.remove('active');
                    this.updateToggleIcon(pageToggle, false);
                    this.toggleBodyScroll(false);
                });
            });
        }

        // Close nav when clicking outside
        document.addEventListener('click', (e) => {
            if (landingNav && !landingNav.contains(e.target) && !landingToggle?.contains(e.target)) {
                landingNav.classList.remove('active');
                if (landingToggle) this.updateToggleIcon(landingToggle, false);
                this.toggleBodyScroll(false);
            }

            if (pageNavLinks && !pageNavLinks.contains(e.target) && !pageToggle?.contains(e.target)) {
                pageNavLinks.classList.remove('active');
                if (pageToggle) this.updateToggleIcon(pageToggle, false);
                this.toggleBodyScroll(false);
            }
        });

        // Close nav on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (landingNav) {
                    landingNav.classList.remove('active');
                    if (landingToggle) this.updateToggleIcon(landingToggle, false);
                }
                if (pageNavLinks) {
                    pageNavLinks.classList.remove('active');
                    if (pageToggle) this.updateToggleIcon(pageToggle, false);
                }
                this.toggleBodyScroll(false);
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                if (landingNav) {
                    landingNav.classList.remove('active');
                    if (landingToggle) this.updateToggleIcon(landingToggle, false);
                }
                if (pageNavLinks) {
                    pageNavLinks.classList.remove('active');
                    if (pageToggle) this.updateToggleIcon(pageToggle, false);
                }
                this.toggleBodyScroll(false);
            }
        });
    }

    updateToggleIcon(toggle, isOpen) {
        if (toggle) {
            toggle.innerHTML = isOpen ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
            toggle.setAttribute('aria-expanded', isOpen.toString());
        }
    }

    toggleBodyScroll(disable) {
        // Don't lock scroll on tablet landscape where we want scrolling
        if (this.isTabletLandscape()) {
            return;
        }
        
        if (disable) {
            // Store current scroll position
            this.scrollY = window.pageYOffset;
            
            // Add class to disable scrolling
            document.body.classList.add('mobile-menu-open');
            
            // Set body position to maintain scroll position
            document.body.style.top = `-${this.scrollY}px`;
        } else {
            // Remove class to enable scrolling
            document.body.classList.remove('mobile-menu-open');
            
            // Remove inline styles
            document.body.style.top = '';
            
            // Restore scroll position
            if (this.scrollY !== undefined) {
                window.scrollTo(0, this.scrollY);
                this.scrollY = undefined;
            }
        }
    }

    isTabletLandscape() {
        const isLandscape = window.innerWidth > window.innerHeight;
        const isTabletSize = window.innerWidth >= 768 && window.innerWidth <= 1366;
        const isShortHeight = window.innerHeight <= 900;
        return isLandscape && isTabletSize && isShortHeight;
    }

    initViewportFix() {
        // Fix for iOS Safari viewport height issues
        const setViewportHeight = () => {
            // Get the actual viewport height
            let vh = window.innerHeight * 0.01;
            
            // For iOS Safari, use screen height as fallback
            if (this.isIOS() && window.screen && window.screen.height) {
                const screenHeight = window.screen.height;
                const actualHeight = Math.max(window.innerHeight, screenHeight * 0.85);
                vh = actualHeight * 0.01;
            }
            
            document.documentElement.style.setProperty('--vh', `${vh}px`);
            
            // Force recalculation on landing pages
            if (document.body.classList.contains('landing-page')) {
                document.documentElement.style.setProperty('--real-vh', `${vh}px`);
            }
        };

        // Set initial value
        setViewportHeight();

        // Update on resize and orientation change
        window.addEventListener('resize', () => {
            setTimeout(setViewportHeight, 50);
        });
        
        window.addEventListener('orientationchange', () => {
            // Multiple delays to catch iOS Safari's quirks
            setTimeout(setViewportHeight, 100);
            setTimeout(setViewportHeight, 300);
            setTimeout(setViewportHeight, 500);
        });
        
        // Set on page load as well
        window.addEventListener('load', setViewportHeight);
    }

    isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    }
}

// Initialize mobile navigation when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new MobileNavigation();
});