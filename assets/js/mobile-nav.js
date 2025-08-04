// Mobile Navigation Handler
class MobileNavigation {
    constructor() {
        this.initMobileNav();
    }

    initMobileNav() {
        // Mobile nav toggle for landing page
        const landingToggle = document.querySelector('.landing-page .mobile-nav-toggle');
        const landingNav = document.querySelector('.landing-page .main-nav');

        if (landingToggle && landingNav) {
            landingToggle.addEventListener('click', () => {
                landingNav.classList.toggle('active');
                this.updateToggleIcon(landingToggle, landingNav.classList.contains('active'));
            });

            // Close nav when clicking on links
            const landingLinks = landingNav.querySelectorAll('a');
            landingLinks.forEach(link => {
                link.addEventListener('click', () => {
                    landingNav.classList.remove('active');
                    this.updateToggleIcon(landingToggle, false);
                });
            });
        }

        // Mobile nav toggle for other pages
        const pageToggle = document.querySelector('.page-header .mobile-nav-toggle');
        const pageNavLinks = document.querySelector('.page-header .nav-links');

        if (pageToggle && pageNavLinks) {
            pageToggle.addEventListener('click', () => {
                pageNavLinks.classList.toggle('active');
                this.updateToggleIcon(pageToggle, pageNavLinks.classList.contains('active'));
            });

            // Close nav when clicking on links
            const pageLinks = pageNavLinks.querySelectorAll('a');
            pageLinks.forEach(link => {
                link.addEventListener('click', () => {
                    pageNavLinks.classList.remove('active');
                    this.updateToggleIcon(pageToggle, false);
                });
            });
        }

        // Close nav when clicking outside
        document.addEventListener('click', (e) => {
            if (landingNav && !landingNav.contains(e.target) && !landingToggle?.contains(e.target)) {
                landingNav.classList.remove('active');
                if (landingToggle) this.updateToggleIcon(landingToggle, false);
            }

            if (pageNavLinks && !pageNavLinks.contains(e.target) && !pageToggle?.contains(e.target)) {
                pageNavLinks.classList.remove('active');
                if (pageToggle) this.updateToggleIcon(pageToggle, false);
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
            }
        });
    }

    updateToggleIcon(toggle, isOpen) {
        if (toggle) {
            toggle.textContent = isOpen ? '✕' : '☰';
            toggle.setAttribute('aria-expanded', isOpen.toString());
        }
    }
}

// Initialize mobile navigation when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new MobileNavigation();
});