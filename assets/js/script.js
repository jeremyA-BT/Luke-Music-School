// Configuration for feature toggling
const CONFIG = {
    showGallery: true,
    showAudio: true,
    showVideo: true,
    showTestimonials: true
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    handleFeatureToggling();
    addPageAnimations();
    handleContactForm();
    addPlaceholderInteractions();
    addLessonsInteractions();
});

// Feature toggling based on config
function handleFeatureToggling() {
    const sections = [
        { id: 'gallery', config: CONFIG.showGallery },
        { id: 'media', config: CONFIG.showAudio && CONFIG.showVideo },
        { id: 'testimonials', config: CONFIG.showTestimonials }
    ];

    sections.forEach(section => {
        const element = document.getElementById(section.id);
        if (element) {
            element.style.display = section.config ? 'block' : 'none';
        }
    });
    
    // Hide individual audio/video sections if needed
    if (!CONFIG.showAudio) {
        const audioSection = document.querySelector('.audio-section');
        if (audioSection) audioSection.style.display = 'none';
    }
    
    if (!CONFIG.showVideo) {
        const videoSection = document.querySelector('.video-section');
        if (videoSection) videoSection.style.display = 'none';
    }
}

// Page animations and interactions
function addPageAnimations() {
    // Fade in elements on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    // Observe elements for fade-in animation
    const elementsToAnimate = document.querySelectorAll('.bio-section, .lesson-card, .testimonial, .contact-method');
    elementsToAnimate.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(element);
    });

    // Stagger lesson card animations
    const lessonCards = document.querySelectorAll('.lesson-card');
    lessonCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
}

// Contact form handling
function handleContactForm() {
    const form = document.querySelector('.message-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Basic validation
            if (!data.name || !data.email || !data.message) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Simulate form submission
            const button = form.querySelector('.submit-btn');
            const originalText = button.textContent;
            
            button.textContent = 'Sending...';
            button.disabled = true;
            button.style.opacity = '0.7';
            
            setTimeout(() => {
                button.textContent = 'Message Sent!';
                button.style.background = 'var(--color-warm-teal)';
                button.style.opacity = '1';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.disabled = false;
                    button.style.background = '';
                    form.reset();
                }, 2000);
            }, 1500);
        });
    }
}

// Add interactions to placeholder content
function addPlaceholderInteractions() {
    // Gallery items
    document.querySelectorAll('.gallery-item').forEach(item => {
        item.addEventListener('click', () => {
            const overlay = item.querySelector('.gallery-overlay');
            if (overlay) {
                overlay.style.background = 'linear-gradient(transparent, var(--color-primary))';
                overlay.innerHTML = '<span>📷 Click to replace image</span>';
                
                setTimeout(() => {
                    overlay.style.background = '';
                    overlay.innerHTML = '<span>' + overlay.getAttribute('data-original') + '</span>';
                }, 2000);
            }
        });
    });

    // Gallery placeholders (for any remaining placeholders)
    document.querySelectorAll('.gallery-item .image-placeholder').forEach(placeholder => {
        placeholder.addEventListener('click', () => {
            placeholder.style.background = 'var(--color-primary)';
            placeholder.style.color = 'white';
            placeholder.innerHTML = '<span>📷 Upload Image</span>';
            
            setTimeout(() => {
                placeholder.style.background = '';
                placeholder.style.color = '';
                placeholder.innerHTML = '<span>Performance Photo</span>';
            }, 2000);
        });
    });

    // Audio placeholders
    document.querySelectorAll('.audio-placeholder').forEach(placeholder => {
        let isPlaying = false;
        placeholder.addEventListener('click', () => {
            const icon = placeholder.querySelector('.audio-icon');
            if (icon) {
                isPlaying = !isPlaying;
                icon.innerHTML = isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
                placeholder.style.background = isPlaying ? 'var(--color-warm-orange)' : '';
                placeholder.style.color = isPlaying ? 'white' : '';
            }
        });
    });

    // Video placeholders
    document.querySelectorAll('.video-placeholder').forEach(placeholder => {
        let isPlaying = false;
        placeholder.addEventListener('click', () => {
            const icon = placeholder.querySelector('.play-icon');
            if (icon) {
                isPlaying = !isPlaying;
                icon.innerHTML = isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
                placeholder.style.background = isPlaying ? 'var(--color-warm-pink)' : '';
                placeholder.style.color = isPlaying ? 'white' : '';
            }
        });
    });

    // Bio profile photo
    const profilePhoto = document.querySelector('.profile-photo');
    if (profilePhoto) {
        profilePhoto.addEventListener('click', () => {
            profilePhoto.style.filter = 'sepia(100%) hue-rotate(40deg) saturate(2)';
            
            setTimeout(() => {
                profilePhoto.style.filter = '';
            }, 2000);
        });
    }

    // Bio image placeholder (fallback)
    const bioPlaceholder = document.querySelector('.bio-image .image-placeholder');
    if (bioPlaceholder) {
        bioPlaceholder.addEventListener('click', () => {
            bioPlaceholder.style.background = 'var(--color-primary)';
            bioPlaceholder.style.color = 'white';
            bioPlaceholder.innerHTML = '<span>📷 Upload Profile Photo</span>';
            
            setTimeout(() => {
                bioPlaceholder.style.background = '';
                bioPlaceholder.style.color = '';
                bioPlaceholder.innerHTML = '<span>Profile Photo</span>';
            }, 2000);
        });
    }
}

// Lessons page specific interactions
function addLessonsInteractions() {
    // Lesson card hover effects
    document.querySelectorAll('.lesson-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            const accent = card.querySelector('.card-color-accent');
            if (accent) {
                accent.style.height = '8px';
            }
        });
        
        card.addEventListener('mouseleave', () => {
            const accent = card.querySelector('.card-color-accent');
            if (accent) {
                accent.style.height = '4px';
            }
        });
    });

    // Trial lesson CTA click
    const trialCta = document.querySelector('.trial-cta');
    if (trialCta) {
        trialCta.addEventListener('click', (e) => {
            // If we're not on the contact page, allow normal navigation
            if (!window.location.pathname.includes('contact.html')) {
                return;
            }
            
            // If we're on contact page, scroll to form and pre-fill
            e.preventDefault();
            const form = document.querySelector('.message-form');
            const instrumentSelect = document.getElementById('instrument');
            const messageTextarea = document.getElementById('message');
            
            if (form) {
                form.scrollIntoView({ behavior: 'smooth' });
                
                if (instrumentSelect) {
                    instrumentSelect.value = 'other';
                }
                
                if (messageTextarea) {
                    messageTextarea.value = 'Hi Luke, I\'m interested in booking a trial lesson. ';
                    messageTextarea.focus();
                }
            }
        });
    }

    // Color splash animation trigger
    const colorSplashes = document.querySelectorAll('.color-splash');
    if (colorSplashes.length > 0) {
        // Add random movement to color splashes
        setInterval(() => {
            colorSplashes.forEach(splash => {
                const randomX = Math.random() * 20 - 10; // -10 to +10
                const randomY = Math.random() * 20 - 10; // -10 to +10
                splash.style.transform = `translate(${randomX}px, ${randomY}px)`;
            });
        }, 3000);
    }
}