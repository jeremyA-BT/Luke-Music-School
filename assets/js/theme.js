// Theme management for Luke Higgins website
class ThemeManager {
    constructor() {
        this.init();
    }

    init() {
        // Get saved theme from localStorage or default to dark
        const savedTheme = localStorage.getItem('theme') || 'dark';
        this.setTheme(savedTheme);
        
        // Set up theme toggle listeners
        this.setupToggleListeners();
        
        // Update toggle button text
        this.updateToggleButton();
    }

    setTheme(theme) {
        // Apply theme to document
        document.documentElement.setAttribute('data-theme', theme);
        
        // Save to localStorage
        localStorage.setItem('theme', theme);
        
        // Update toggle button
        this.updateToggleButton();
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }

    updateToggleButton() {
        const toggleButtons = document.querySelectorAll('.theme-toggle');
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        
        toggleButtons.forEach(button => {
            const icon = button.querySelector('.theme-toggle-icon');
            const text = button.querySelector('.theme-toggle-text');
            
            if (currentTheme === 'light') {
                if (icon) icon.textContent = '♪';
                if (text) text.textContent = 'Dark';
            } else {
                if (icon) icon.textContent = '♫';
                if (text) text.textContent = 'Light';
            }
        });
    }

    setupToggleListeners() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.theme-toggle')) {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }
}

// Initialize theme manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
});

// Export for potential external use
window.ThemeManager = ThemeManager;