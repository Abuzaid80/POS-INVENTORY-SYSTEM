// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    // Get current page URL
    const currentPage = window.location.pathname.split('/').pop();
    
    // Remove active class from all sidebar items
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to current page's sidebar item
    document.querySelectorAll('.sidebar-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.parentElement.classList.add('active');
        }
    });

    // Mobile menu toggle functionality
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Initialize mobile menu state
    function initializeMobileMenu() {
        if (window.innerWidth <= 768) {
            if (sidebar) sidebar.classList.remove('active');
            if (mainContent) mainContent.classList.remove('active');
            if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            if (mobileMenuToggle) mobileMenuToggle.style.display = 'flex';
        } else {
            if (mobileMenuToggle) mobileMenuToggle.style.display = 'none';
        }
    }

    // Call initialization
    initializeMobileMenu();

    function toggleSidebar() {
        if (!sidebar || !mainContent || !sidebarOverlay) return;
        
        sidebar.classList.toggle('active');
        mainContent.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        
        // Prevent body scrolling when sidebar is open
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }

    // Ensure elements exist before adding event listeners
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }

    // Close sidebar when clicking a link on mobile
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        });
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            initializeMobileMenu();
        }, 250);
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && 
            sidebar && 
            sidebar.classList.contains('active') && 
            !sidebar.contains(e.target) && 
            !mobileMenuToggle.contains(e.target)) {
            toggleSidebar();
        }
    });
}); 