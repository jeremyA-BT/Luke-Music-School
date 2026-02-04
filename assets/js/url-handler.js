// URL Handler for HostAfrica & Development
// This handles clean URLs and ensures proper navigation

(function() {
    // Get current path
    const path = window.location.pathname;
    const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    
    // For local development (Live Server), handle routing
    if (isLocal) {
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
    // This works for both local development and production hosting
    if (window.location.pathname.endsWith('.html')) {
        let cleanPath = window.location.pathname;
        
        // All index pages should show as root '/' 
        // BUT don't interfere with index.html while it's redirecting (since it's now the random selector)
        if (path.includes('index-v1.html') || path.includes('index-v2.html')) {
            cleanPath = '/';
        } else if (path.includes('index.html') || path.includes('index-random.html') || path.includes('random-landing.html')) {
            // Let random landing pages handle their own redirect first
            return;
        } else {
            // Other pages get clean URLs
            cleanPath = cleanPath
                .replace('/bio.html', '/Bio')
                .replace('/lessons.html', '/Lessons')
                .replace('/contact.html', '/Contact');
        }
        
        if (cleanPath !== window.location.pathname) {
            window.history.replaceState({}, '', cleanPath);
        }
    }
})();

// Handle navigation clicks to use clean URLs
document.addEventListener('DOMContentLoaded', function() {
    const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    
    // For local development, convert clean URLs to actual file paths
    if (isLocal) {
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
            } else if (href === '/') {
                link.href = 'index.html';
            }
        });
    }
    
    // For production hosting, clean URLs are handled by .htaccess
});
