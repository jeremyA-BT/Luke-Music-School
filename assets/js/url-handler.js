// URL Handler for Development & Netlify
// This handles clean URLs and ensures proper navigation

(function() {
    // Get current path
    const path = window.location.pathname;
    const isNetlify = window.location.hostname.includes('netlify') || window.location.hostname.includes('app');
    
    // For local development (Live Server), handle routing
    if (!isNetlify && window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        const currentFile = window.location.href.split('/').pop();
        
        // Handle clean URL routing for Live Server
        if (path.endsWith('/Bio') || path.endsWith('/Bio/')) {
            if (!currentFile.includes('bio.html')) {
                window.location.replace('bio.html');
            }
        } else if (path.endsWith('/Lessons') || path.endsWith('/Lessons/')) {
            if (!currentFile.includes('lessons.html')) {
                window.location.replace('lessons.html');
            }
        } else if (path.endsWith('/Contact') || path.endsWith('/Contact/')) {
            if (!currentFile.includes('contact.html')) {
                window.location.replace('contact.html');
            }
        }
    }
    
    // Update browser history to show clean URLs (for display purposes)
    // This works for both local development and Netlify
    if (window.location.pathname.endsWith('.html') && !path.includes('index-random')) {
        const cleanPath = window.location.pathname
            .replace('/bio.html', '/Bio')
            .replace('/lessons.html', '/Lessons')
            .replace('/contact.html', '/Contact');
        
        if (cleanPath !== window.location.pathname) {
            window.history.replaceState({}, '', cleanPath);
        }
    }
})();

// Handle navigation clicks to use clean URLs
document.addEventListener('DOMContentLoaded', function() {
    const isNetlify = window.location.hostname.includes('netlify') || window.location.hostname.includes('app');
    const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    
    // Only update links for local development - Netlify handles routing natively
    if (isLocal && !isNetlify) {
        const navLinks = document.querySelectorAll('a[href^="/"]');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            
            // Convert clean URLs to actual file paths for Live Server only
            if (href === '/Bio') {
                link.href = 'bio.html';
            } else if (href === '/Lessons') {
                link.href = 'lessons.html';
            } else if (href === '/Contact') {
                link.href = 'contact.html';
            } else if (href === '/' || href === '/index-random.html') {
                link.href = 'index-random.html';
            }
        });
    }
    
    // For Netlify, the links stay as clean URLs and routing is handled by _redirects
});
