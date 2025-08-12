// Studio Gallery Carousel
class StudioGallery {
    constructor() {
        this.currentSlide = 0;
        this.photos = [
            // Studio photos from assets/media - selecting a curated set
            {
                src: 'assets/media/IMG_2438.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2443.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2444.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2450.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2452.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2455.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2457.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2460.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2469.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2470.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2471.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2478.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2480.JPG',
                caption: ''
            },
            {
                src: 'assets/media/IMG_2492.JPG',
                caption: ''
            },
        ];
        
        this.track = null;
        this.indicators = null;
        this.prevBtn = null;
        this.nextBtn = null;
        this.isLoading = false;
        
        // Lightbox elements
        this.lightbox = null;
        this.lightboxImage = null;
        this.lightboxCaption = null;
        this.lightboxClose = null;
        
        this.init();
    }
    
    init() {
        // Wait for DOM to be fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupCarousel());
        } else {
            this.setupCarousel();
        }
    }
    
    setupCarousel() {
        this.track = document.getElementById('studioCarouselTrack');
        this.indicators = document.getElementById('carouselIndicators');
        
        if (!this.track || !this.indicators) {
            console.log('Studio gallery elements not found on this page');
            return;
        }
        
        this.prevBtn = document.querySelector('.studio-gallery-carousel .prev-btn');
        this.nextBtn = document.querySelector('.studio-gallery-carousel .next-btn');
        
        // Setup lightbox elements
        this.lightbox = document.getElementById('galleryLightbox');
        this.lightboxImage = document.getElementById('lightboxImage');
        this.lightboxCaption = document.getElementById('lightboxCaption');
        this.lightboxClose = document.getElementById('lightboxClose');
        
        this.createSlides();
        this.createIndicators();
        this.bindEvents();
        this.bindLightboxEvents();
        this.showSlide(0);
        
        // Auto-play functionality (optional)
        this.startAutoPlay();
    }
    
    createSlides() {
        this.track.innerHTML = '';
        
        this.photos.forEach((photo, index) => {
            const slide = document.createElement('div');
            slide.className = 'carousel-slide';
            slide.innerHTML = `
                <img src="${photo.src}" alt="${photo.caption}" loading="${index === 0 ? 'eager' : 'lazy'}" style="cursor: pointer;">
                <div class="photo-overlay">
                    <p>${photo.caption}</p>
                    <small style="opacity: 0.8; font-size: 0.85em; margin-top: 5px; display: block;">Click to view full size</small>
                </div>
            `;
            
            // Add click event for lightbox
            const img = slide.querySelector('img');
            img.addEventListener('click', () => this.openLightbox(index));
            
            this.track.appendChild(slide);
        });
    }
    
    createIndicators() {
        this.indicators.innerHTML = '';
        
        this.photos.forEach((_, index) => {
            const indicator = document.createElement('button');
            indicator.className = 'indicator';
            indicator.setAttribute('aria-label', `Go to slide ${index + 1}`);
            indicator.addEventListener('click', () => this.goToSlide(index));
            this.indicators.appendChild(indicator);
        });
    }
    
    bindEvents() {
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.previousSlide());
        }
        
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.nextSlide());
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (this.track && this.isCarouselVisible()) {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.previousSlide();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.nextSlide();
                }
            }
        });
        
        // Touch/swipe support
        this.addTouchSupport();
    }
    
    addTouchSupport() {
        let startX = 0;
        let currentX = 0;
        let isDragging = false;
        
        this.track.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isDragging = true;
            this.pauseAutoPlay();
        });
        
        this.track.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            currentX = e.touches[0].clientX;
        });
        
        this.track.addEventListener('touchend', () => {
            if (!isDragging) return;
            isDragging = false;
            
            const diff = startX - currentX;
            const threshold = 50;
            
            if (Math.abs(diff) > threshold) {
                if (diff > 0) {
                    this.nextSlide();
                } else {
                    this.previousSlide();
                }
            }
            
            this.startAutoPlay();
        });
    }
    
    showSlide(index) {
        if (this.isLoading || !this.track) return;
        
        const slides = this.track.children;
        const indicators = this.indicators.children;
        
        // Update current slide index
        this.currentSlide = index;
        
        // Move track to show current slide
        const translateX = -index * 100;
        this.track.style.transform = `translateX(${translateX}%)`;
        
        // Update indicators
        Array.from(indicators).forEach((indicator, i) => {
            indicator.classList.toggle('active', i === index);
        });
        
        // Update button states
        if (this.prevBtn) {
            this.prevBtn.style.opacity = index === 0 ? '0.5' : '1';
        }
        if (this.nextBtn) {
            this.nextBtn.style.opacity = index === slides.length - 1 ? '0.5' : '1';
        }
    }
    
    nextSlide() {
        const nextIndex = (this.currentSlide + 1) % this.photos.length;
        this.goToSlide(nextIndex);
    }
    
    previousSlide() {
        const prevIndex = this.currentSlide === 0 ? this.photos.length - 1 : this.currentSlide - 1;
        this.goToSlide(prevIndex);
    }
    
    goToSlide(index) {
        if (index >= 0 && index < this.photos.length) {
            this.showSlide(index);
            this.restartAutoPlay();
        }
    }
    
    isCarouselVisible() {
        if (!this.track) return false;
        const rect = this.track.getBoundingClientRect();
        return rect.top < window.innerHeight && rect.bottom > 0;
    }
    
    startAutoPlay() {
        this.autoPlayInterval = setInterval(() => {
            if (this.isCarouselVisible()) {
                this.nextSlide();
            }
        }, 5000); // Change slide every 5 seconds
    }
    
    pauseAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
        }
    }
    
    restartAutoPlay() {
        this.pauseAutoPlay();
        this.startAutoPlay();
    }
    
    // Public method to add more photos dynamically
    addPhoto(src, caption) {
        this.photos.push({ src, caption });
        this.createSlides();
        this.createIndicators();
        this.bindEvents();
    }
    
    // Public method to remove auto-play (for accessibility)
    disableAutoPlay() {
        this.pauseAutoPlay();
    }
    
    // Lightbox functionality
    bindLightboxEvents() {
        if (!this.lightbox) return;
        
        // Close lightbox when clicking the close button
        if (this.lightboxClose) {
            this.lightboxClose.addEventListener('click', () => this.closeLightbox());
        }
        
        // Close lightbox when clicking outside the image
        this.lightbox.addEventListener('click', (e) => {
            if (e.target === this.lightbox) {
                this.closeLightbox();
            }
        });
        
        // Close lightbox with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.lightbox.classList.contains('active')) {
                this.closeLightbox();
            }
        });
    }
    
    openLightbox(index) {
        if (!this.lightbox || !this.photos[index]) return;
        
        const photo = this.photos[index];
        
        // Set image and caption
        if (this.lightboxImage) {
            this.lightboxImage.src = photo.src;
            this.lightboxImage.alt = photo.caption;
        }
        
        if (this.lightboxCaption) {
            this.lightboxCaption.textContent = photo.caption;
        }
        
        // Show lightbox
        this.lightbox.classList.add('active');
        
        // Pause auto-play while lightbox is open
        this.pauseAutoPlay();
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
    
    closeLightbox() {
        if (!this.lightbox) return;
        
        this.lightbox.classList.remove('active');
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Resume auto-play
        this.startAutoPlay();
    }
}

// Initialize the studio gallery when DOM is ready
let studioGallery;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        studioGallery = new StudioGallery();
    });
} else {
    studioGallery = new StudioGallery();
}

// Export for potential external use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StudioGallery;
}
