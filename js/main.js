// js/main.js

/**
 * This function contains all the global logic that needs to run on every page,
 * such as initializing the sidebar tooltips and the theme toggle.
 */
function initializeGlobalScripts() {
    // 1. Initialize Bootstrap Tooltips
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
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);
}

/**
 * This function repeatedly checks if the Bootstrap library has been loaded.
 * This prevents "race condition" errors.
 */
function waitForBootstrap() {
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip === 'function') {
        initializeGlobalScripts();
    } else {
        setTimeout(waitForBootstrap, 50);
    }
}

// Start the process only after the document is ready.
$(document).ready(function() {
    // --- NEW: DESKTOP APP LOGIC ---
    // This block is for the Electron app. It checks if the `electronAPI` is available
    // (meaning it's running in Electron) and sends the API token to the app's backend.
    if (window.electronAPI && typeof window.electronAPI.sendToken === 'function') {
        const mainContent = document.querySelector('main[data-api-token]');
        if (mainContent) {
            const token = mainContent.getAttribute('data-api-token');
            if (token) {
                console.log('Found API token, sending to desktop app backend...');
                window.electronAPI.sendToken(token);
            }
        }
    }
    // --- END: DESKTOP APP LOGIC ---

    // Continue with existing initialization
    waitForBootstrap();
});