// assets/js/main.js
// Common JS logic for Sidebar toggle, Password visibility toggler and UI scripts

document.addEventListener('DOMContentLoaded', () => {
    // 1. Sidebar Toggler for Mobile Layouts
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });

        // Close sidebar if user clicks outside of it on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }

    // 2. Password Visibility Toggle
    const passwordToggle = document.getElementById('password-toggle');
    const passwordField = document.getElementById('password');
    
    if (passwordToggle && passwordField) {
        passwordToggle.addEventListener('click', () => {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            // Toggle Eye Icon
            const icon = passwordToggle.querySelector('i');
            if (icon) {
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        });
    }

    // 3. Navbar scroll effect for Landing Page
    const landingNavbar = document.querySelector('.landing-navbar');
    if (landingNavbar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                landingNavbar.classList.add('scrolled');
            } else {
                landingNavbar.classList.remove('scrolled');
            }
        });
    }

    // 4. Auto-dismiss Alert messages after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 5000);
    });

    // 5. Light/Dark Theme Switcher Logic
    const currentTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    const updateThemeIcons = (theme) => {
        const icons = document.querySelectorAll('.theme-toggle-btn i');
        icons.forEach(i => {
            if (theme === 'light') {
                i.className = 'fa-solid fa-moon fs-5';
            } else {
                i.className = 'fa-solid fa-sun fs-5';
            }
        });
    };
    
    // Set initial icons state
    updateThemeIcons(currentTheme);

    const themeToggleBtns = document.querySelectorAll('.theme-toggle-btn');
    themeToggleBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const nextTheme = current === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', nextTheme);
            localStorage.setItem('theme', nextTheme);
            updateThemeIcons(nextTheme);
        });
    });
});
