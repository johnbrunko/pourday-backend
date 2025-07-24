// js/main.js

/**
 * This function contains all the global logic that needs to run on every page,
 * such as initializing the sidebar tooltips and the theme toggle.
 */
function initializeGlobalScripts() {
    // 1. Initialize Bootstrap Tooltips
    // This is now safe because it's only called after we confirm Bootstrap is ready.
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 2. Dark/Light Mode Toggle Logic
    const themeToggleButton = document.getElementById('theme-toggle');
    const setTheme = (theme) => {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-bs-theme');
        }
        localStorage.setItem('theme', theme);
    };

    if (themeToggleButton) {
        themeToggleButton.addEventListener('click', (e) => {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            setTheme(currentTheme === 'dark' ? 'light' : 'dark');
        });
    }

    // Apply saved theme on initial page load, defaulting to 'light'
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);
}

/**
 * This function repeatedly checks if the Bootstrap library has been loaded.
 * Once it confirms Bootstrap is ready, it calls our main initialization function.
 * This is a very robust way to prevent script loading "race condition" errors.
 */
function waitForBootstrap() {
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip === 'function') {
        // Bootstrap is ready, run our scripts.
        initializeGlobalScripts();
    } else {
        // If not ready, wait 50 milliseconds and check again.
        setTimeout(waitForBootstrap, 50);
    }
}

// Start the process only after the document is ready.
$(document).ready(function() {
    waitForBootstrap();
});
